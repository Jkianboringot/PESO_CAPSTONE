<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;



Route::get('/', function () {
    return redirect('dashboard');
});
Route::middleware(["throttle:5,1"])->get('/job-portal', \App\Livewire\RegistrationForm::class)->name('register');

Route::middleware(['auth', 'role:staff|admin',"throttle:60,1"])->group(function () {

    // Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    // Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');


      // Applicant Management (CRUD, search, filter)
    Route::get('/applicants', \App\Livewire\ApplicantManagement::class)->name('applicants');

    // Duplicate Detection Review Queue
    Route::get('/duplicates', \App\Livewire\DuplicateReview::class)->name('duplicates');

    // Workforce Analytics Dashboard
    Route::get('/analytics', \App\Livewire\WorkforceAnalyticsDashboard::class)->name('analytics');

    // Report Generation
    Route::get('/reports', \App\Livewire\ReportGenerator::class)->name('reports');

    // Skills Gap Analysis
    Route::get('/skills-gap', \App\Livewire\SkillsGapAnalysis::class)->name('skills-gap');
});


  //Admin 
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', \App\Livewire\UserManagement::class)->name('admin.users');
});

require __DIR__.'/auth.php';





// Route::middleware(['auth', 'role:staff,admin'])->group(function () {
//     // Dashboard home
//     Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

//     // Applicant Management (CRUD, search, filter)
//     Route::get('/applicants', \App\Livewire\ApplicantManagement::class)->name('applicants');

//     // Duplicate Detection Review Queue
//     Route::get('/duplicates', \App\Livewire\DuplicateReview::class)->name('duplicates');

//     // Workforce Analytics Dashboard
//     Route::get('/analytics', \App\Livewire\WorkforceAnalyticsDashboard::class)->name('analytics');

//     // Report Generation
//     Route::get('/reports', \App\Livewire\ReportGenerator::class)->name('reports');

//     // Skills Gap Analysis
//     Route::get('/skills-gap', \App\Livewire\SkillsGapAnalysis::class)->name('skills-gap');
// });

// // ── ADMIN-ONLY ROUTES (role: admin) ───────────────────────
// Route::middleware(['auth', 'role:admin'])->group(function () {
//     Route::get('/admin/users', \App\Livewire\UserManagement::class)->name('admin.users');
// });