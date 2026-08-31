<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\AuditLog;
use App\Livewire\Dashboard;
use App\Livewire\Geogrophical;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Route;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;


Route::get('/', function () {
  return redirect('dashboard');
});
// limit to 5 since its public but maybe 60 is better, idk it something that can be fix later
// Route::middleware(["throttle:30,1"])->get('/job-portal', \App\Livewire\RegistrationForm::class)->name('job-portal.register');

Route::middleware(['log.jobportal'])->group(function () {
  Route::get('/job-portal', \App\Livewire\RegistrationForm::class);
  // ...other job portal routes
});


Route::middleware(["throttle:30,1"])->get('/qr/job-portal', function () {
  $qrCode = new QrCode(route('job-portal.register'));
  $writer = new PngWriter();
  $result = $writer->write($qrCode);

  return response($result->getString(), 200)
    ->header('Content-Type', $result->getMimeType());
})->name('qr.job-portal');


Route::middleware(['auth', 'role:staff|admin', "throttle:60,1"])->group(function () {

  // TODO will deactivate 
  // Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
  // Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
  // Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

  
  Route::get('/admin/activity-stats', function () {
    $stats = [
      'total_job_portal_views' => ActivityLog::where('event', 'job_portal_view')->count(),
      'unique_visitors' => ActivityLog::where('event', 'job_portal_view')->distinct('ip_address')->count('ip_address'),
      'views_today' => ActivityLog::where('event', 'job_portal_view')->whereDate('created_at', today())->count(),
      'login_success' => ActivityLog::where('event', 'login_success')->count(),
      'login_failed' => ActivityLog::where('event', 'login_failed')->count(),
      'logs' => ActivityLog::with('user')->latest()->take(50)->get(),
    ];

    return view('activity-stats', $stats);
  })->middleware(['auth', 'role:admin']);



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
  // Route::get('/geogrophical', Geogrophical::class)->name('geogrophical');
});


//Admin 
Route::middleware(['auth', 'role:admin'])->group(function () {
  Route::get('/admin/users', \App\Livewire\UserManagement::class)->name('admin.users');
  route::get('admin/audit-logs', AuditLog::class)->name('admin.audit-logs');
});

require __DIR__ . '/auth.php';


