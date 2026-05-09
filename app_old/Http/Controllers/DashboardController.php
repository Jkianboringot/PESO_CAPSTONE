<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\DuplicateFlag;

class DashboardController extends Controller {

    // Staff/Admin home — shows summary stats cards
    public function index() {
        $stats = [
            'total_applicants'  => Applicant::active()->count(),
            'this_month'        => Applicant::active()
                                    ->whereMonth('created_at', now()->month)
                                    ->count(),
            'pending_duplicates'=> DuplicateFlag::pending()->count(),
            'flagged'           => Applicant::where('status', 'Flagged')->count(),
        ];

        return view('dashboard', compact('stats'));
    }

    // Public landing page with registration CTA
    public function welcome() {
        return view('welcome');
    }
}
