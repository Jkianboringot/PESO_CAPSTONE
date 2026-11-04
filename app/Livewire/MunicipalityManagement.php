<?php

namespace App\Livewire;

use App\Models\Municipality;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class MunicipalityManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $municipalityName = '';
    public string $province = 'Catanduanes';

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
        $this->reset(['municipalityName', 'editingId']);
        $this->province = 'Catanduanes';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id)
    {
        $municipality = Municipality::findOrFail($id);

        $this->editingId = $municipality->id;
        $this->municipalityName = $municipality->name;
        $this->province = $municipality->province ?? '';
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
            'province' => 'required|string|max:100',
            'municipalityName' => [
                'required',
                'string',
                'max:100',
                // municipality name must be unique inside the province
                Rule::unique('municipalities', 'name')
                    ->where('province', $this->province)
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
                    'name' => trim($this->municipalityName),
                    'province' => trim($this->province),
                ];

                if ($isEdit) {
                    $municipality = Municipality::findOrFail($this->editingId);
                    $municipality->update($data);
                    $audit->log('MUNICIPALITY_UPDATED', $municipality);
                } else {
                    $municipality = Municipality::create($data);
                    $audit->log('MUNICIPALITY_CREATED', $municipality);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not save the municipality. Please try again.');
            return;
        }

        $this->closeModal();
        $this->notify('success', $isEdit ? 'Municipality updated successfully.' : 'Municipality created successfully.');
    }

    public function delete(int $id, AuditLogService $audit)
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $municipality = Municipality::withCount('barangays')->findOrFail($id);

        // don't orphan barangays
        if ($municipality->barangays_count > 0) {
            $this->notify('error', "Cannot delete: {$municipality->barangays_count} barangay(s) belong to this municipality.");
            return;
        }

        try {
            DB::transaction(function () use ($municipality, $audit) {
                $audit->log('MUNICIPALITY_DELETED', $municipality);
                $municipality->delete();
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not delete the municipality. Please try again.');
            return;
        }

        $this->notify('success', 'Municipality deleted.');
    }

    public function render()
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $term = trim($this->search);

        $viewMunicipalities = Municipality::withCount('barangays')
            ->when($term !== '', fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('province', 'like', "%{$term}%"))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.municipality-management', [
            'viewMunicipalities' => $viewMunicipalities,
        ])->layout('layouts.app');
    }
}
