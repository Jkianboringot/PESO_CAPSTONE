<?php

namespace App\Livewire;

use App\Models\Applicant;
use App\Models\DuplicateFlag;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        // DSH-2: only admin and staff may see the dashboard figures.
        abort_if(!auth()->user()?->hasRole(['admin', 'staff']), 403);

        $stats = [
            'total_applicants' => Applicant::active()->count(),

            // DSH-1: the full current month, so last year's November is excluded.
            'this_month' => Applicant::active()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),

            'pending_duplicates' => DuplicateFlag::pending()->count(),

            // DSH-3: only active applicants, matching the other cards.
            'flagged' => Applicant::active()->where('status', 'Flagged')->count(),
        ];

        return view('livewire.dashboard', ['stats' => $stats]);
    }
}