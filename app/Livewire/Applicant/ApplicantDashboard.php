<?php

namespace App\Livewire\Applicant;

use App\Models\Applicant;
use App\Models\DuplicateFlag;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.applicant')] // change to whatever layout your login pages use

class ApplicantDashboard extends Component
{
    public function render()
    {
        $stats = [
            'total_applicants' => Applicant::active()->count(),
            'this_month' => Applicant::active()
                ->whereMonth('created_at', now()->month)
                ->count(),
            'pending_duplicates' => DuplicateFlag::pending()->count(),
            'flagged' => Applicant::where('status', 'Flagged')->count(),
        ];

        return view('livewire.applicant.applicant-dashboard', ['stats' => $stats]);
    }
}
