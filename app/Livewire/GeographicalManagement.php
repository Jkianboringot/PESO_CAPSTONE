<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class GeographicalManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $barangayName = '';
    public string $municipalityID = '';

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
        $this->reset(['barangayName', 'municipalityID', 'editingId']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id)
    {
        $barangay = Barangay::findOrFail($id);

        $this->editingId = $barangay->id;
        $this->barangayName = $barangay->name;
        $this->municipalityID = (string) $barangay->municipality_id;
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
            'municipalityID' => 'required|exists:municipalities,id',
            'barangayName' => [
                'required',
                'string',
                'max:100',
                // barangay name must be unique inside the chosen municipality
                Rule::unique('barangays', 'name')
                    ->where('municipality_id', $this->municipalityID)
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
                    'name' => trim($this->barangayName),
                    'municipality_id' => $this->municipalityID,
                ];

                if ($isEdit) {
                    $barangay = Barangay::findOrFail($this->editingId);
                    $barangay->update($data);
                    $audit->log('BARANGAY_UPDATED', $barangay);
                } else {
                    $barangay = Barangay::create($data);
                    $audit->log('BARANGAY_CREATED', $barangay);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not save the barangay. Please try again.');
            return;
        }

        $this->closeModal();
        $this->notify('success', $isEdit ? 'Barangay updated successfully.' : 'Barangay created successfully.');
    }

    public function delete(int $id, AuditLogService $audit)
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $barangay = Barangay::withCount('applicants')->findOrFail($id);

        // don't orphan applicant records
        if ($barangay->applicants_count > 0) {
            $this->notify('error', "Cannot delete: {$barangay->applicants_count} applicant(s) are linked to this barangay.");
            return;
        }

        try {
            DB::transaction(function () use ($barangay, $audit) {
                $audit->log('BARANGAY_DELETED', $barangay);
                $barangay->delete();
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not delete the barangay. Please try again.');
            return;
        }

        $this->notify('success', 'Barangay deleted.');
    }

    public function render()
    {
        abort_if(!auth()->user()->hasRole('admin'), 403);

        $term = trim($this->search);

        $viewBarangays = Barangay::with('municipality')
            ->withCount('applicants')
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhereHas('municipality', fn ($m) => $m
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('province', 'like', "%{$term}%"));
                });
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.geographical-management', [
            'viewBarangays' => $viewBarangays,
            'selectMunicipality' => Municipality::orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}