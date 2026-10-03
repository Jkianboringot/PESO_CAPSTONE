<?php

namespace App\Livewire;

use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\SkillCategory;
use App\Services\AuditLogService;
use Livewire\Component;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;

class ApplicantManagement extends Component
{

    use WithPagination;

    // Filter properties (bound to filter form)
    public string $search = '';
    public string $filterStatus = '';
    public string $filterBarangay = '';
    public string $filterEdLevel = '';
    public string $filterCategory = '';
    public string $filterFrom = '';
    public string $filterTo = '';

    // Editing state
    public ?int $editingId = null;
    public array $editData = [];
    public bool $showModal = false;

    // Which records the table shows: active | inactive | all
    public string $viewStatus = 'active';

    // Read-only view state
    public ?int $viewingId = null;
    public bool $showView = false;

    // Reset pagination when any filter changes
    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function updatingFilterStatus()
    {
        $this->resetPage();
    }
    public function updatingFilterBarangay()
    {
        $this->resetPage();
    }

    // ---------- ACTIVE / INACTIVE TABS ----------
    public function setViewStatus(string $value)
    {
        $this->viewStatus = in_array($value, ['active', 'inactive', 'all']) ? $value : 'active';
        $this->resetPage();
    }

    // ---------- FILTER BUTTONS ----------
    // inputs are deferred, so clicking Filter syncs them and re-renders
    public function applyFilters()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset([
            'search',
            'filterStatus',
            'filterBarangay',
            'filterEdLevel',
            'filterCategory',
            'filterFrom',
            'filterTo',
        ]);
        $this->resetPage();
    }

    // ---------- READ-ONLY VIEW ----------
    public function openView(int $id)
    {
        abort_if(
            !auth()->user()->hasRole(['staff', 'admin']),
            403
        );

        $this->viewingId = $id;
        $this->showView = true;
    }

    public function closeView()
    {
        $this->showView = false;
        $this->viewingId = null;
    }

    // jump from the read-only view straight into edit
    public function editFromView(int $id)
    {
        $this->closeView();
        $this->openEdit($id);
    }

    public function openEdit(int $id)
    { //safe id
        //no // AUTHORIZE check, contemplate this, am only worried about the query part honestly, since save is has authoriza check already

        $a = Applicant::findOrFail($id);
        $this->editingId = $id;
        $this->editData = $a->only([
            'last_name',
            'first_name',
            'middle_name',
            'contact_number',
            'email',
            'status',
            'address',
            'barangay_id',
        ]);
        $this->showModal = true;
    }

    // ---------- TOAST HELPERS ----------
    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }

    private function canManage(): bool
    {
        return auth()->user()->hasRole(['staff', 'admin']);
    }

    public function saveEdit(AuditLogService $audit)
    {
        if (!$this->canManage()) {
            $this->notify('error', 'You do not have permission to edit applicants.');
            return;
        }

        try {
            $this->validate([
                'editData.last_name' => 'required|string|max:100',
                'editData.first_name' => 'required|string|max:100',
                'editData.contact_number' => 'required|string|max:20',
                'editData.status' => 'required|in:Pending,Verified,Flagged,Inactive',
            ]);
        } catch (ValidationException $e) {
            $this->notify('error', 'Please fix the highlighted fields.');
            throw $e; // keeps the inline field errors
        }

        try {
            $applicant = Applicant::findOrFail($this->editingId);
            $before = $applicant->only(array_keys($this->editData)); // REVIEW
            $applicant->update($this->editData);

            $audit->logApplicantUpdated($applicant, [
                'before' => $before,
                'after' => $this->editData,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not update the record. Please try again.');
            return;
        }

        $this->showModal = false;
        $this->editingId = null;
        $this->notify('success', 'Record updated successfully.');
    }

    //this is good if they want for thier info to be remove, or not active for new job but they 
    // are still in the system this save storage and comply with rule of data concern
    public function deactivate(int $id, AuditLogService $audit)
    {
        if (!$this->canManage()) {
            $this->notify('error', 'You do not have permission to deactivate applicants.');
            return;
        }

        try {
            $a = Applicant::findOrFail($id);
            $a->update(['is_active' => false, 'status' => 'Inactive']);
            $audit->logDeactivate($a);
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not deactivate the applicant. Please try again.');
            return;
        }

        $this->notify('success', 'Applicant deactivated.');
    }

    // bring a deactivated applicant back; goes to Pending so staff can re-verify
    public function activate(int $id, AuditLogService $audit)
    {
        if (!$this->canManage()) {
            $this->notify('error', 'You do not have permission to activate applicants.');
            return;
        }

        try {
            $a = Applicant::findOrFail($id);
            $before = $a->only(['is_active', 'status']);
            $a->update(['is_active' => true, 'status' => 'Pending']);

            $audit->logApplicantUpdated($a, [
                'before' => $before,
                'after' => ['is_active' => true, 'status' => 'Pending'],
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not activate the applicant. Please try again.');
            return;
        }

        $this->notify('success', 'Applicant activated and set to Pending for re-verification.');
    }

    public function render()
    {
        //no // AUTHORIZE check

        $query = Applicant::with(['barangay.municipality', 'education', 'skills.category'])
            ->when($this->viewStatus === 'active', fn($q) => $q->active())
            ->when($this->viewStatus === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($this->search, fn($q) => $q->where(function ($q) { // REVIEW this always confuse me 
                $q->where('last_name', 'like', "%{$this->search}%")
                    ->orWhere('first_name', 'like', "%{$this->search}%")
                    ->orWhere('reference_id', 'like', "%{$this->search}%");
            }))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterBarangay, fn($q) => $q->byBarangay($this->filterBarangay))
            ->when($this->filterEdLevel, fn($q) => $q->byEducation($this->filterEdLevel))
            ->when($this->filterCategory, fn($q) => $q->bySkillCategory($this->filterCategory))
            // ->byDateRange($this->filterFrom, $this->filterTo)
            ->orderByDesc('created_at');

        // only loaded while the read-only modal is open
        $viewing = $this->showView && $this->viewingId
            ? Applicant::with(['barangay.municipality', 'education', 'skills.category'])->find($this->viewingId)
            : null;

        return view('livewire.applicant-management', [
            'applicants' => $query->paginate(20),
            'viewing' => $viewing,
            'barangays' => Barangay::orderBy('name')->pluck('name', 'id'),
            'categories' => SkillCategory::orderBy('name')->pluck('name', 'id'),
            'edLevels' => [
                'Elementary',
                'High School',
                'Senior High School',
                'Vocational/Technical',
                'College Undergraduate',
                'College Graduate',
                'Post-Graduate',
            ],
            'statuses' => ['Pending', 'Verified', 'Flagged', 'Inactive'],
        ])->layout('layouts.app');
    }


}