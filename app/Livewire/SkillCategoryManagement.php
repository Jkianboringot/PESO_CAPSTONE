<?php

namespace App\Livewire;

use App\Models\SkillCategory;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class SkillCategoryManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $pqf_level = '';
    public string $description = '';

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

    public function openCreate()
    {
        $this->reset(['name', 'pqf_level', 'description', 'editingId']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id)
    {
        $category = SkillCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->pqf_level = (string) ($category->pqf_level ?? '');
        $this->description = (string) ($category->description ?? '');
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
            'name' => ['required', 'string', 'max:255', Rule::unique('skill_categories', 'name')->ignore($this->editingId)],
            'pqf_level' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
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
                    'pqf_level' => trim($this->pqf_level) ?: null,
                    'description' => trim($this->description) ?: null,
                ];

                if ($isEdit) {
                    $category = SkillCategory::findOrFail($this->editingId);
                    $category->update($data);
                    $audit->log('SKILL_CATEGORY_UPDATED', $category);
                } else {
                    $category = SkillCategory::create($data);
                    $audit->log('SKILL_CATEGORY_CREATED', $category);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not save the category. Please try again.');
            return;
        }

        $this->closeModal();
        $this->notify('success', $isEdit ? 'Category updated successfully.' : 'Category created successfully.');
    }

    public function delete(int $id, AuditLogService $audit)
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $category = SkillCategory::withCount('skills')->findOrFail($id);

        // skills.skill_category_id is restrictOnDelete
        if ($category->skills_count > 0) {
            $this->notify('error', "Cannot delete: {$category->skills_count} skill(s) belong to this category.");
            return;
        }

        try {
            DB::transaction(function () use ($category, $audit) {
                $audit->log('SKILL_CATEGORY_DELETED', $category);
                $category->delete();
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not delete the category. Please try again.');
            return;
        }

        $this->notify('success', 'Category deleted.');
    }

    public function render()
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $term = trim($this->search);

        $categories = SkillCategory::withCount('skills')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('pqf_level', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.skill-category-management', [
            'categories' => $categories,
        ])->layout('layouts.app');
    }
}
