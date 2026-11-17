<?php

namespace Tests\Feature;

use App\Livewire\Applicant\Login;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Applicant login page: an applicant signs in with their reference ID, contact
 * number or email plus a password, on the 'applicant' guard.
 *
 * WHY: this is the only door into the applicant area. It must accept the three
 * identifiers, refuse everyone else (inactive, no password, wrong password, staff
 * accounts), say the same thing for every failure so the form cannot be used to
 * find out which IDs exist, and slow down password guessing.
 *
 * NOTE ON ASSUMPTIONS (change these to match your app)
 * ────────────────────────────────────────────────────
 *  - Route names: 'applicant.login' (this page) and 'applicant.profile' (where a
 *    successful login lands).
 *  - Applicants use the 'applicant' guard. Admin and staff use the 'web' guard and
 *    live in a different table, so they can never log in here.
 *  - Email matching is case-insensitive because MySQL's default collation is. On a
 *    case-sensitive database, test_email_login_ignores_letter_case will fail.
 *  - The cache store is shared across a test (array store in phpunit.xml). It is
 *    flushed in setUp() so throttle counters never leak between tests.
 *  - SetsUpAdminAndStaff provides $this->admin and $this->staff via setUpUsers().
 *
 * NOTE ON KNOWN BUGS AND GAPS
 * ───────────────────────────
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions. Tests marked REGRESSION pass today and
 * guard behaviour that comes from the framework rather than from this component.
 *
 *   APP-LOGIN-1  throttle can be bypassed by switching identifiers   (1 test)
 */
class ApplicantLoginTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private const PASSWORD = 'secret-pass-123';
    private const GENERIC  = 'These credentials do not match our records.';

    private Applicant $applicant;
    private Applicant $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        // throttle counters live in the cache; start every test from zero
        Cache::flush();

        $municipality = Municipality::firstOrCreate(['name' => 'Virac', 'province' => 'Catanduanes']);
        $barangay     = Barangay::firstOrCreate(['name' => 'San Jose', 'municipality_id' => $municipality->id]);

        $this->applicant = Applicant::factory()->create([
            'barangay_id'    => $barangay->id,
            'contact_number' => '09171234567',
            'email'          => 'juan@example.com',
            'password'       => Hash::make(self::PASSWORD),
        ]);

        $this->other = Applicant::factory()->create([
            'barangay_id'    => $barangay->id,
            'contact_number' => '09177654321',
            'email'          => 'maria@example.com',
            'password'       => Hash::make('another-secret-456'),
        ]);

        // reference_id is generated when the row is created
        $this->applicant->refresh();
        $this->other->refresh();
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function attempt(string $login, string $password, bool $remember = false)
    {
        return Livewire::test(Login::class)
            ->set('login', $login)
            ->set('password', $password)
            ->set('remember', $remember)
            ->call('authenticate');
    }

    /** Make $n failed attempts with the given login. */
    private function failTimes(string $login, int $n): void
    {
        foreach (range(1, $n) as $i) {
            $this->attempt($login, 'wrong-password');
        }
    }

    // ── PAGE ─────────────────────────────────────────────────────────

    public function test_login_page_opens_for_a_guest(): void
    {
        $this->get(route('applicant.login'))->assertOk()->assertSeeLivewire(Login::class);
    }

    public function test_form_starts_empty(): void
    {
        Livewire::test(Login::class)
            ->assertSet('login', '')
            ->assertSet('password', '')
            ->assertSet('remember', false);
    }

    // ── SUCCESSFUL LOGIN ─────────────────────────────────────────────

    public function test_login_with_contact_number(): void
    {
        $this->attempt('09171234567', self::PASSWORD)
            ->assertHasNoErrors()
            ->assertRedirect(route('applicant.profile'));

        $this->assertAuthenticatedAs($this->applicant, 'applicant');
    }

    public function test_login_with_email(): void
    {
        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));

        $this->assertAuthenticatedAs($this->applicant, 'applicant');
    }

    public function test_login_with_reference_id(): void
    {
        $this->assertNotEmpty($this->applicant->reference_id);

        $this->attempt($this->applicant->reference_id, self::PASSWORD)->assertRedirect(route('applicant.profile'));

        $this->assertAuthenticatedAs($this->applicant, 'applicant');
    }

    public function test_login_signs_in_the_right_applicant_not_another_one(): void
    {
        $this->attempt('maria@example.com', 'another-secret-456');

        $this->assertAuthenticatedAs($this->other, 'applicant');
        $this->assertNotEquals($this->applicant->id, Auth::guard('applicant')->id());
    }

    public function test_successful_login_leaves_the_web_guard_alone(): void
    {
        $this->attempt('juan@example.com', self::PASSWORD);

        $this->assertGuest('web');
    }

    public function test_login_with_remember_me_works(): void
    {
        $this->attempt('juan@example.com', self::PASSWORD, true)->assertRedirect(route('applicant.profile'));

        $this->assertAuthenticated('applicant');
    }

    public function test_login_ignores_a_stale_intended_url(): void
    {
        session(['url.intended' => url('/dashboard')]);

        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));
    }

    /** Email matching follows the database collation; MySQL is case-insensitive. */
    public function test_email_login_ignores_letter_case(): void
    {
        $this->attempt('JUAN@Example.COM', self::PASSWORD)->assertRedirect(route('applicant.profile'));

        $this->assertAuthenticatedAs($this->applicant, 'applicant');
    }

    /** REGRESSION — passes today. Livewire trims the text, so stray spaces do not break login. */
    public function test_login_ignores_spaces_around_the_identifier(): void
    {
        $this->attempt('  juan@example.com  ', self::PASSWORD)->assertRedirect(route('applicant.profile'));
    }

    public function test_applicant_waiting_for_verification_can_still_log_in(): void
    {
        $this->applicant->forceFill(['status' => 'Pending'])->save();

        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));
    }

    public function test_flagged_applicant_can_still_log_in(): void
    {
        $this->applicant->forceFill(['status' => 'Flagged'])->save();

        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));
    }

    // ── FAILED LOGIN ─────────────────────────────────────────────────

    public function test_wrong_password_is_refused_with_the_generic_message(): void
    {
        $component = $this->attempt('juan@example.com', 'wrong-password')
            ->assertHasErrors(['login'])
            ->assertNoRedirect();

        $this->assertSame(self::GENERIC, $component->errors()->first('login'));
        $this->assertGuest('applicant');
    }

    public function test_wrong_password_is_refused_for_every_identifier_type(): void
    {
        foreach (['09171234567', 'juan@example.com', $this->applicant->reference_id] as $login) {
            $this->attempt($login, 'wrong-password')->assertHasErrors(['login']);
        }

        $this->assertGuest('applicant');
    }

    public function test_password_is_case_sensitive(): void
    {
        $this->attempt('juan@example.com', strtoupper(self::PASSWORD))->assertHasErrors(['login']);

        $this->assertGuest('applicant');
    }

    public function test_one_applicants_password_does_not_open_another_account(): void
    {
        $this->attempt('juan@example.com', 'another-secret-456')->assertHasErrors(['login']);

        $this->assertGuest('applicant');
    }

    public function test_unknown_login_is_refused_with_the_same_message(): void
    {
        $component = $this->attempt('nobody@example.com', self::PASSWORD)->assertHasErrors(['login']);

        $this->assertSame(self::GENERIC, $component->errors()->first('login'));
        $this->assertGuest('applicant');
    }

    public function test_inactive_applicant_is_refused_with_the_same_message(): void
    {
        $this->applicant->forceFill(['is_active' => false, 'status' => 'Inactive'])->save();

        $component = $this->attempt('juan@example.com', self::PASSWORD)->assertHasErrors(['login']);

        $this->assertSame(self::GENERIC, $component->errors()->first('login'));
        $this->assertGuest('applicant');
    }

    public function test_applicant_with_no_password_is_refused_with_the_same_message(): void
    {
        DB::table('applicants')->where('id', $this->applicant->id)->update(['password' => '']);

        $component = $this->attempt('juan@example.com', '')->assertHasErrors();

        $this->assertGuest('applicant');

        $component = $this->attempt('juan@example.com', 'anything');
        $this->assertSame(self::GENERIC, $component->errors()->first('login'));
        $this->assertGuest('applicant');
    }

    public function test_every_failure_gives_the_same_message(): void
    {
        $this->other->forceFill(['is_active' => false])->save();

        $messages = [
            $this->attempt('juan@example.com', 'wrong-password')->errors()->first('login'),
            $this->attempt('nobody@example.com', 'wrong-password')->errors()->first('login'),
            $this->attempt('maria@example.com', 'another-secret-456')->errors()->first('login'),
        ];

        $this->assertSame([self::GENERIC, self::GENERIC, self::GENERIC], $messages);
    }

    public function test_failed_login_clears_the_password_but_keeps_the_login(): void
    {
        $this->attempt('juan@example.com', 'wrong-password')
            ->assertSet('password', '')
            ->assertSet('login', 'juan@example.com');
    }

    public function test_a_failed_login_does_not_log_out_or_in_the_web_guard(): void
    {
        $this->attempt('juan@example.com', 'wrong-password');

        $this->assertGuest('web');
        $this->assertGuest('applicant');
    }

    // ── PERMISSIONS: STAFF AND ADMIN ─────────────────────────────────

    public function test_admin_account_cannot_log_in_on_the_applicant_form(): void
    {
        $this->attempt($this->admin->email, 'password')->assertHasErrors(['login']);

        $this->assertGuest('applicant');
        $this->assertGuest('web');
    }

    public function test_staff_account_cannot_log_in_on_the_applicant_form(): void
    {
        $this->attempt($this->staff->email, 'password')->assertHasErrors(['login']);

        $this->assertGuest('applicant');
        $this->assertGuest('web');
    }

    public function test_applicant_login_does_not_sign_in_a_staff_user_with_a_matching_email(): void
    {
        $this->applicant->forceFill(['email' => $this->staff->email])->save();

        $this->attempt($this->staff->email, self::PASSWORD);

        $this->assertAuthenticatedAs($this->applicant, 'applicant');
        $this->assertGuest('web');
    }

    // ── VALIDATION ───────────────────────────────────────────────────

    public function test_login_and_password_are_required(): void
    {
        Livewire::test(Login::class)
            ->call('authenticate')
            ->assertHasErrors(['login' => 'required', 'password' => 'required'])
            ->assertNoRedirect();

        $this->assertGuest('applicant');
    }

    public function test_login_is_required_when_only_a_password_is_given(): void
    {
        Livewire::test(Login::class)
            ->set('password', self::PASSWORD)
            ->call('authenticate')
            ->assertHasErrors(['login' => 'required']);
    }

    public function test_password_is_required_when_only_a_login_is_given(): void
    {
        Livewire::test(Login::class)
            ->set('login', 'juan@example.com')
            ->call('authenticate')
            ->assertHasErrors(['password' => 'required']);
    }

    public function test_whitespace_only_login_is_rejected(): void
    {
        Livewire::test(Login::class)
            ->set('login', '     ')
            ->set('password', self::PASSWORD)
            ->call('authenticate')
            ->assertHasErrors(['login' => 'required']);
    }

    public function test_empty_fields_do_not_count_as_failed_attempts(): void
    {
        foreach (range(1, 10) as $i) {
            Livewire::test(Login::class)->call('authenticate')->assertHasErrors(['login', 'password']);
        }

        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));
    }

    public function test_sql_like_text_is_just_a_wrong_login(): void
    {
        $component = $this->attempt("' OR '1'='1", "' OR '1'='1")->assertHasErrors(['login']);

        $this->assertSame(self::GENERIC, $component->errors()->first('login'));
        $this->assertGuest('applicant');
    }

    public function test_wildcard_characters_do_not_match_every_applicant(): void
    {
        foreach (['%', '_', '%@%', '0917%'] as $login) {
            $this->attempt($login, self::PASSWORD)->assertHasErrors(['login']);
        }

        $this->assertGuest('applicant');
    }

    public function test_very_long_login_does_not_crash(): void
    {
        $this->attempt(str_repeat('a', 5000), self::PASSWORD)->assertHasErrors(['login']);

        $this->assertGuest('applicant');
    }

    // ── THROTTLING ───────────────────────────────────────────────────

    public function test_four_failures_do_not_lock_the_account(): void
    {
        $this->failTimes('juan@example.com', 4);

        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));
    }

    public function test_five_failures_lock_the_login_even_for_the_right_password(): void
    {
        $this->failTimes('juan@example.com', 5);

        $component = $this->attempt('juan@example.com', self::PASSWORD)->assertHasErrors(['login'])->assertNoRedirect();

        $this->assertMatchesRegularExpression(
            '/^Too many attempts\. Try again in \d+ seconds\.$/',
            $component->errors()->first('login')
        );
        $this->assertGuest('applicant');
    }

    public function test_lockout_wait_time_is_no_longer_than_a_minute(): void
    {
        $this->failTimes('juan@example.com', 5);

        $message = $this->attempt('juan@example.com', 'wrong-password')->errors()->first('login');

        preg_match('/(\d+) seconds/', $message, $m);
        $this->assertGreaterThan(0, (int) $m[1]);
        $this->assertLessThanOrEqual(60, (int) $m[1]);
    }

    public function test_lockout_ends_after_the_wait_time(): void
    {
        $this->failTimes('juan@example.com', 5);
        $this->attempt('juan@example.com', self::PASSWORD)->assertHasErrors(['login']);

        $this->travel(2)->minutes();

        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));
    }

    public function test_lockout_is_per_login_so_other_applicants_can_still_sign_in(): void
    {
        $this->failTimes('juan@example.com', 5);

        $this->attempt('maria@example.com', 'another-secret-456')->assertRedirect(route('applicant.profile'));

        $this->assertAuthenticatedAs($this->other, 'applicant');
    }

    public function test_throttle_ignores_letter_case_of_the_login(): void
    {
        $this->failTimes('JUAN@EXAMPLE.COM', 3);
        $this->failTimes('juan@example.com', 2);

        $this->attempt('Juan@Example.com', self::PASSWORD)
            ->assertHasErrors(['login'])
            ->assertNoRedirect();

        $this->assertGuest('applicant');
    }

    public function test_unknown_logins_are_locked_the_same_way(): void
    {
        $this->failTimes('nobody@example.com', 5);

        $message = $this->attempt('nobody@example.com', 'wrong-password')->errors()->first('login');

        $this->assertStringStartsWith('Too many attempts.', $message);
    }

    public function test_successful_login_clears_the_failure_count(): void
    {
        $this->failTimes('juan@example.com', 4);
        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));
        Auth::guard('applicant')->logout();

        // without the reset these four would push the total to eight and lock the account
        $this->failTimes('juan@example.com', 4);

        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));
    }

    public function test_locked_attempt_with_empty_fields_shows_validation_not_lockout(): void
    {
        $this->failTimes('juan@example.com', 5);

        Livewire::test(Login::class)
            ->set('login', 'juan@example.com')
            ->call('authenticate')
            ->assertHasErrors(['password' => 'required']);
    }

    /**
     * KNOWN BUG — expected to FAIL (APP-LOGIN-1).
     *
     * The throttle key is built from whatever the user typed, so one account has
     * three separate counters: reference ID, contact number and email. Someone
     * guessing passwords gets 5 tries on each identifier instead of 5 in total.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Try 5 wrong passwords with the applicant's contact number until it says "Too many attempts".
     *   2. Type the same applicant's email instead and try another wrong password.
     *   3. BUG: it is checked normally. Expected: "Too many attempts" as well.
     *
     * FIX: find the applicant first and key the throttle on the account, not the text:
     *   protected function throttleKey(?Applicant $applicant = null): string
     *   {
     *       $who = $applicant ? 'applicant:' . $applicant->id : Str::lower($this->login);
     *       return Str::transliterate($who . '|' . request()->ip());
     *   }
     * (look the applicant up before the tooManyAttempts() check, and pass it to throttleKey()).
     */
    public function test_switching_identifier_does_not_reset_the_throttle(): void
    {
        $this->failTimes('09171234567', 5);

        $message = $this->attempt('juan@example.com', 'wrong-password')->errors()->first('login');

        $this->assertStringStartsWith('Too many attempts.', $message);
    }

    // ── SESSION ──────────────────────────────────────────────────────

    public function test_login_does_not_log_in_anyone_until_authenticate_is_called(): void
    {
        Livewire::test(Login::class)
            ->set('login', 'juan@example.com')
            ->set('password', self::PASSWORD);

        $this->assertGuest('applicant');
    }

    public function test_logging_in_twice_stays_signed_in_as_the_same_applicant(): void
    {
        $this->attempt('juan@example.com', self::PASSWORD);
        $this->attempt('juan@example.com', self::PASSWORD)->assertRedirect(route('applicant.profile'));

        $this->assertAuthenticatedAs($this->applicant, 'applicant');
    }
}