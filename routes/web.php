<?php

use App\Livewire\ApplicantManagement;
use App\Livewire\AuditLog;
use App\Livewire\Dashboard;
use App\Livewire\DuplicateReview;
use App\Livewire\RegistrationForm;
use App\Livewire\ReportGenerator;
use App\Livewire\SkillsGapAnalysis;
use App\Livewire\UserManagement;
use App\Livewire\WorkforceAnalyticsDashboard;
use App\Models\ActivityLog;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

/*
|--------------------------------------------------------------------------
| Public: job portal + QR
|--------------------------------------------------------------------------
*/
// Add 'throttle:60,1' here later if the public form gets abused.
Route::middleware(['log.jobportal'])->group(function () {
    Route::get('/job-portal', RegistrationForm::class)->name('job-portal.register');
});

Route::get('/qr/job-portal', function () {
    $renderer = new ImageRenderer(new RendererStyle(300, 1), new SvgImageBackEnd());
    $svg = (new Writer($renderer))->writeString(url('/job-portal'));

    return response($svg, 200)->header('Content-Type', 'image/svg+xml');
})->name('qr.job-portal');

/*
|--------------------------------------------------------------------------
| Authenticated (all logged-in users, throttled)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'throttle:60,1'])->group(function () {

    // ── Admin AND staff ──────────────────────────────────────────────
    Route::middleware('role:admin|staff')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/applicants', ApplicantManagement::class)->name('applicants');
        Route::get('/duplicates', DuplicateReview::class)->name('duplicates');
        Route::get('/analytics', WorkforceAnalyticsDashboard::class)->name('analytics');
        Route::get('/reports', ReportGenerator::class)->name('reports');
        Route::get('/skills-gap', SkillsGapAnalysis::class)->name('skills-gap');
    });

    // ── Admin ONLY ───────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', UserManagement::class)->name('admin.users');
        Route::get('/audit-logs', AuditLog::class)->name('admin.audit-logs');

        Route::get('/activity-stats', function () {
            $jobPortalViews = ActivityLog::where('event', 'job_portal_view');

            return view('activity-stats', [
                'total_job_portal_views' => (clone $jobPortalViews)->count(),
                'unique_visitors'        => (clone $jobPortalViews)->distinct('ip_address')->count('ip_address'),
                'views_today'            => (clone $jobPortalViews)->whereDate('created_at', today())->count(),
                'login_success'          => ActivityLog::where('event', 'login_success')->count(),
                'login_failed'           => ActivityLog::where('event', 'login_failed')->count(),
                'logs'                   => ActivityLog::with('user')->latest()->take(50)->get(),
            ]);
        })->name('admin.activity-stats');
    });
});

require __DIR__ . '/auth.php';