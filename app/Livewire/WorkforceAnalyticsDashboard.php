<?php

namespace App\Livewire;

use App\Models\DuplicateFlag;
use Livewire\Component;
use Livewire\WithPagination;

class WorkforceAnalyticsDashboard extends Component
{
    
          use WithPagination;
 
    public ?int  $reviewingFlagId = null;
    public ?DuplicateFlag $activeFlag = null;
    public string $resolutionNotes = '';
 
    public function openFlag(int $flagId) {
        $this->reviewingFlagId = $flagId;
        $this->activeFlag = DuplicateFlag::with([
            'newApplicant.barangay',
            'newApplicant.education',
            'newApplicant.skills.category',
            'existingApplicant.barangay',
            'existingApplicant.education',
            'existingApplicant.skills.category',
        ])->findOrFail($flagId);
        $this->resolutionNotes = '';
    }
 
    public function resolve(string $action, AuditLogService $audit) {
        $allowed = ['Merged', 'Retained Both', 'Deleted'];
        if (!in_array($action, $allowed)) abort(422, 'Invalid action');
 
        $flag = DuplicateFlag::findOrFail($this->reviewingFlagId);
 
        if ($action === 'Merged') {
            // Mark newer applicant as inactive; keep existing
            $flag->newApplicant->update(['is_active' => false, 'status' => 'Inactive']);
        } elseif ($action === 'Deleted') {
            // Soft-delete the newer applicant
            $flag->newApplicant->update(['is_active' => false, 'status' => 'Inactive']);
        }
        // "Retained Both" — no record change, just resolve the flag
 
        $flag->update([
            'resolution_status' => $action,
            'resolved_by'       => auth()->id(),
            'resolution_notes'  => $this->resolutionNotes ?: null,
            'resolved_at'       => now(),
        ]);
 
        $audit->logDuplicateResolved($flag, $action);
 
        $this->reviewingFlagId = null;
        $this->activeFlag = null;
        session()->flash('success', "Flag resolved: {$action}");
    }
 
    public function render() {
        return view('livewire.duplicate-review', [
            'flags' => DuplicateFlag::pending()
                ->with(['newApplicant', 'existingApplicant'])
                ->orderByDesc('created_at')
                ->paginate(15),
        ])->layout('layouts.app');
    }
    
}
