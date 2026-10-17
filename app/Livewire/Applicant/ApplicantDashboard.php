<?php

namespace App\Livewire\Applicant;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.applicant-app')]
#[Title('Dashboard')]
class ApplicantDashboard extends Component
{
    public function render()
    {
        $a = Auth::guard('applicant')->user();

        $skillCount = DB::table('applicant_skill')->where('applicant_id', $a->id)->count();
        $education  = DB::table('education')->where('applicant_id', $a->id)->first();

        $checks = [
            filled($a->contact_number),
            filled($a->email),
            filled($a->address),
            $education !== null && filled($education->highest_level),
            $skillCount > 0,
        ];
        $completion = (int) round(count(array_filter($checks)) / count($checks) * 100);

        return view('livewire.applicant.applicant-dashboard', [
            'applicant'  => $a,
            'skillCount' => $skillCount,
            'education'  => $education,
            'completion' => $completion,
        ]);
    }
}