<?php

namespace Tests\Feature;

use App\Livewire\Applicant\Login as ApplicantLogin;
use App\Livewire\RegistrationForm;
use App\Models\ActivityLog;
use App\Models\Applicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RegistersApplicants;
use Tests\Feature\Concerns\RegistersApplicants as ConcernsRegistersApplicants;
use Tests\TestCase;

/**
 * Written against the CURRENT routes/web.php:
 *   /            -> redirects to the staff dashboard (and then to /login)
 *   /job-portal  -> the registration form
 *
 * Part A  = behaviour that must keep working AFTER the change ("/" becomes the
 *           registration page, "/job-portal" becomes a redirect to "/").
 *           These use followingRedirects(), so they pass before AND after.
 * Part B  = "test_old_*" = behaviour that is EXPECTED TO CHANGE. When the new
 *           web.php goes in, these fail on purpose. That failure is the proof
 *           the change is in. Delete or flip them afterwards.
 */
class JobPortalRouteTest extends TestCase
{
    use RefreshDatabase, ConcernsRegistersApplicants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // the layouts call @vite, which needs a built manifest
    }

    // ═════════════════════════════════════════════════════════════════
    // PART A: must pass before AND after the change
    // ═════════════════════════════════════════════════════════════════

    /** TEST 1 (access): a guest can open the registration page. */
    public function test_guest_can_open_the_registration_page(): void
    {
        $this->followingRedirects()
            ->get('/job-portal')
            ->assertOk()
            ->assertSeeLivewire(RegistrationForm::class)
            ->assertSee('PESO Skills Registry');
    }

    /** Opening the registration page is counted in the admin activity stats. */
    public function test_opening_the_registration_page_is_logged_once(): void
    {
        $before = ActivityLog::where('event', 'job_portal_view')->count();

        $this->followingRedirects()->get('/job-portal')->assertOk();

        $this->assertSame(
            $before + 1,
            ActivityLog::where('event', 'job_portal_view')->count(),
            'Each visit must write exactly one job_portal_view row.'
        );
    }

    /** The staff login page still offers a link to registration. */
    public function test_staff_login_page_links_to_registration(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('job-portal.register'))
            ->assertSee(route('applicant.login'));
    }

    /** The applicant login page loads and offers Register. */
    public function test_applicant_login_page_loads_with_register_button(): void
    {
        $this->get('/applicant/login')
            ->assertOk()
            ->assertSeeLivewire(ApplicantLogin::class)
            ->assertSee('Register');
    }

    /** The QR route still returns an SVG image. */
    public function test_qr_route_returns_an_svg(): void
    {
        $response = $this->get(route('qr.job-portal'))->assertOk();

        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    /** Staff pages still need a login. */
    public function test_staff_dashboard_still_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /** Applicant pages still send guests to the APPLICANT login, not the staff one. */
    public function test_applicant_pages_still_redirect_to_applicant_login(): void
    {
        $this->get('/applicant/profile')->assertRedirect(route('applicant.login'));
    }

    /**
     * TEST 2 (register): open the page, then actually register an applicant
     * and check everything that was saved plus the success screen.
     */
    public function test_new_applicant_can_register_end_to_end(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        // 1. the page is reachable
        $this->followingRedirects()->get('/job-portal')->assertOk();

        // 2. fill the 6 steps and submit
        $component = $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $refId = $component->get('reference_id');
        $this->assertStringStartsWith('PESO-', $refId);

        // 3. the success screen shows the reference ID and the way to log in
        $component
            ->assertSee('Registration Successful!')
            ->assertSee($refId)
            ->assertSee(route('applicant.login'));

        // 4. the database has the full record
        $this->assertDatabaseCount('applicants', 1);
        $applicant = Applicant::first();
        $this->assertSame($refId, $applicant->reference_id);
        $this->assertSame('Santos', $applicant->last_name);
        $this->assertSame('09171234567', $applicant->contact_number);
        $this->assertSame($barangay->id, (int) $applicant->barangay_id);
        $this->assertSame('Pending', $applicant->status);
        $this->assertTrue(Hash::check($this->testPassword, $applicant->password));
        $this->assertDatabaseCount('education', 1);
        $this->assertDatabaseCount('applicant_skill', 2);
        $this->assertSame(1, $applicant->workExperiences()->count());

        // 5. the password is cleared from the component after submit
        $component->assertSet('password', '')->assertSet('password_confirmation', '');
    }

    // ═════════════════════════════════════════════════════════════════
    // PART B: OLD behaviour. These FAIL after the change, on purpose.
    // ═════════════════════════════════════════════════════════════════

    /** OLD: "/" sends a guest to the staff dashboard, which then sends them to login. */
    public function test_old_root_redirects_guest_to_dashboard_then_login(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));

        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertSee('STAFF PORTAL');
    }

    /** OLD: the registration page really lives at /job-portal (no redirect). */
    public function test_old_registration_page_lives_at_job_portal(): void
    {
        $this->assertSame(url('/job-portal'), route('job-portal.register'));

        $this->get('/job-portal')
            ->assertOk()
            ->assertSeeLivewire(RegistrationForm::class);
    }
}