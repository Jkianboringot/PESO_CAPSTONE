<?php

namespace App\Livewire;

use App\Models\Skill;
use App\Models\SkillCategory;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class SkillManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $skill_category_id = '';

    public function mount()
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);
    }

    // ---------- TOAST HELPER ----------
    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function openCreate()
    {
        $this->reset(['name', 'skill_category_id', 'editingId']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id)
    {
        $skill = Skill::findOrFail($id);

        $this->editingId = $skill->id;
        $this->name = $skill->name;
        $this->skill_category_id = (string) $skill->skill_category_id;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(AuditLogService $audit)
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $rules = [
            'skill_category_id' => 'required|exists:skill_categories,id',
            'name' => [
                'required',
                'string',
                'max:255',
                // skill name must be unique inside its category
                Rule::unique('skills', 'name')
                    ->where('skill_category_id', $this->skill_category_id)
                    ->ignore($this->editingId),
            ],
        ];

        try {
            $this->validate($rules);
        } catch (ValidationException $e) {
            $this->notify('error', 'Please fix the highlighted fields.');
            throw $e;
        }

        $isEdit = (bool) $this->editingId;

        try {
            DB::transaction(function () use ($audit, $isEdit) {
                $data = [
                    'name' => trim($this->name),
                    'skill_category_id' => $this->skill_category_id,
                ];

                if ($isEdit) {
                    $skill = Skill::findOrFail($this->editingId);
                    $skill->update($data);
                    $audit->log('SKILL_UPDATED', $skill);
                } else {
                    $skill = Skill::create($data);
                    $audit->log('SKILL_CREATED', $skill);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not save the skill. Please try again.');
            return;
        }

        $this->closeModal();
        $this->notify('success', $isEdit ? 'Skill updated successfully.' : 'Skill created successfully.');
    }

    public function delete(int $id, AuditLogService $audit)
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $skill = Skill::withCount('applicants')->findOrFail($id);

        // don't wipe applicant skill records
        if ($skill->applicants_count > 0) {
            $this->notify('error', "Cannot delete: {$skill->applicants_count} applicant(s) have this skill.");
            return;
        }

        try {
            DB::transaction(function () use ($skill, $audit) {
                $audit->log('SKILL_DELETED', $skill);
                $skill->delete();
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not delete the skill. Please try again.');
            return;
        }

        $this->notify('success', 'Skill deleted.');
    }

    public function render()
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $term = trim($this->search);

        $skills = Skill::with('category')
            ->withCount('applicants')
            ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('skill_category_id', $this->categoryFilter))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.skill-management', [
            'skills' => $skills,
            'categories' => SkillCategory::orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}
