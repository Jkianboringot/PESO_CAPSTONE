<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use Barryvdh\DomPDF\Facade\Pdf;

class ApplicantFormController extends Controller
{
    public function show(Applicant $applicant)
    {
        abort_unless(auth()->user()->hasRole(['staff', 'admin']), 403);

        $applicant->load(['barangay.municipality', 'education', 'skills',
                          'workExperiences', 'trainings', 'certifications']);

        // lowercase skill names, used to tick matching boxes on the paper checklist
        $skillNames = $applicant->skills->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))->all();

        $pdf = Pdf::loadView('reports.nsrp-form', compact('applicant', 'skillNames'))
            ->setPaper('a4');

        return $pdf->stream('NSRP_Form_1_' . $applicant->reference_id . '.pdf');
    }
}
