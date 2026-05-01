<?php

namespace App\Livewire;

use App\Models\Applicant;
use App\Models\DuplicateFlag;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
                $stats = [
            'total_applicants'  => Applicant::active()->count(),
            'this_month'        => Applicant::active()
                                    ->whereMonth('created_at', now()->month)
                                    ->count(),
            'pending_duplicates'=> DuplicateFlag::pending()->count(),
            'flagged'           => Applicant::where('status', 'Flagged')->count(),
        ];

        return view('livewire.dashboard',['stats'=>$stats]);
    }
}
