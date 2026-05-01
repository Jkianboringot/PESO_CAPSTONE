<?php

namespace App\Livewire;

use Livewire\Component;

class ApplicantManagement extends Component
{
   
            use WithPagination;
 
    // Filter properties (bound to filter form)
    public string  $search         = '';
    public string  $filterStatus   = '';
    public string  $filterBarangay = '';
    public string  $filterEdLevel  = '';
    public string  $filterCategory = '';
    public string  $filterFrom     = '';
    public string  $filterTo       = '';
 
    // Editing state
    public ?int    $editingId      = null;
    public array   $editData       = [];
    public bool    $showModal      = false;
 
    // Reset pagination when any filter changes
    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    public function updatingFilterBarangay() { $this->resetPage(); }
 
    public function openEdit(int $id) {
        $a = Applicant::findOrFail($id);
        $this->editingId = $id;
        $this->editData  = $a->only([
            'last_name','first_name','middle_name','contact_number',
            'email','status','address','barangay_id',
        ]);
        $this->showModal = true;
    }
 
    public function saveEdit(AuditLogService $audit) {
        $this->validate([
            'editData.last_name'      => 'required|string|max:100',
            'editData.first_name'     => 'required|string|max:100',
            'editData.contact_number' => 'required|string|max:20',
            'editData.status'         => 'required|in:Pending,Verified,Flagged,Inactive',
        ]);
 
        $applicant = Applicant::findOrFail($this->editingId);
        $before    = $applicant->only(array_keys($this->editData));
        $applicant->update($this->editData);
 
        $audit->logApplicantUpdated($applicant, [
            'before' => $before,
            'after'  => $this->editData,
        ]);
 
        $this->showModal = false;
        $this->editingId = null;
        session()->flash('success', 'Record updated successfully.');
    }
 
    public function deactivate(int $id, AuditLogService $audit) {
        $a = Applicant::findOrFail($id);
        $a->update(['is_active' => false, 'status' => 'Inactive']);
        $audit->log('APPLICANT_DEACTIVATED', $a);
    }
 
    public function render() {
        $query = Applicant::with(['barangay.municipality', 'education', 'skills.category'])
            ->active()
            ->when($this->search, fn($q) => $q->where(function($q) {
                $q->where('last_name', 'like', "%{$this->search}%")
                  ->orWhere('first_name', 'like', "%{$this->search}%")
                  ->orWhere('reference_id', 'like', "%{$this->search}%");
            }))
            ->when($this->filterStatus,   fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterBarangay, fn($q) => $q->byBarangay($this->filterBarangay))
            ->when($this->filterEdLevel,  fn($q) => $q->byEducation($this->filterEdLevel))
            ->when($this->filterCategory, fn($q) => $q->bySkillCategory($this->filterCategory))
            ->byDateRange($this->filterFrom, $this->filterTo)
            ->orderByDesc('created_at');
 
        return view('livewire.applicant-management', [
            'applicants'   => $query->paginate(20),
            'barangays'    => Barangay::orderBy('name')->pluck('name', 'id'),
            'categories'   => SkillCategory::orderBy('name')->pluck('name', 'id'),
            'edLevels'     => [
                'Elementary','High School','Senior High School',
                'Vocational/Technical','College Undergraduate',
                'College Graduate','Post-Graduate',
            ],
            'statuses'     => ['Pending','Verified','Flagged','Inactive'],
        ])->layout('layouts.app');
    }

    
}
