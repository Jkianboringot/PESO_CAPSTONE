<?php

namespace Tests\Feature;

use App\Livewire\Applicant\ChangePassword;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Applicant "Change Password" page.
 *
 * WHY: this is the only place an applicant can rotate the credential that guards
 * their personal record. It must only ever change the LOGGED-IN applicant's
 * password, must prove knowledge of the current password first, must enforce the
 * new-password rules, and must never store anything but a hash.
 *
 * NOTE ON ASSUMPTIONS (change these to match your app)
 * ────────────────────────────────────────────────────
 *  - Route names: 'applicant.password' (this page) and 'applicant.login' (guest redirect).
 *  - Applicants log in through the 'applicant' guard. Admin and staff use the 'web' guard.
 *  - The applicants table has a bcrypt/argon hashed 'password' column and 'updated_at'.
 *    Applicant::factory() accepts a 'password' override. Hash::make() is safe even if
 *    the model has a 'hashed' cast, because Laravel does not re-hash an already hashed value.
 *  - The view shows session('status') like the other applicant pages, and shows
 *    validation errors from the component.
 *  - SetsUpAdminAndStaff provides $this->admin and $this->staff via setUpUsers().
 *
 * NOTE ON FLASH MESSAGES
 * ──────────────────────
 * Flash messages are checked with assertSee() / assertDontSee() on the rendered
 * page, not with session('status'), because the session is not readable from the
 * test after a Livewire call.
 *
 * NOTE ON KNOWN BUGS AND GAPS
 * ───────────────────────────
 * Tests marked KNOWN GAP are expected to FAIL until the app code is fixed. Do not
 * weaken their assertions. Tests marked REGRESSION pass today and guard behaviour
 * that comes from the framework rather than from this component.
 *
 *   APP-PW-1  no guard check in the component   (2 tests)
 */
class ApplicantChangePasswordTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private const OLD = 'OldPassword1';
    private const NEW = 'NewPassword2';

    private Applicant $applicant;
    private Applicant $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $municipality = Municipality::firstOrCreate(['name' => 'Virac', 'province' => 'Catanduanes']);
        $barangay     = Barangay::firstOrCreate(['name' => 'San Jose', 'municipality_id' => $municipality->id]);

        $this->applicant = Applicant::factory()->create([
            'barangay_id' => $barangay->id,
            'password'    => Hash::make(self::OLD),
        ]);
        $this->other = Applicant::factory()->create([
            'barangay_id' => $barangay->id,
            'password'    => Hash::make('OtherPassword9'),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function page(?Applicant $as = null)
    {
        return Livewire::actingAs($as ?? $this->applicant, 'applicant')->test(ChangePassword::class);
    }

    /** Fill the three fields. Confirmation follows the new password unless overridden. */
    private function fill($c, array $over = [])
    {
        $new = $over['password'] ?? self::NEW;

        $v = array_merge([
            'current_password'      => self::OLD,
            'password'              => $new,
            'password_confirmation' => $new,
        ], $over);

        foreach ($v as $k => $val) {
            $c->set($k, $val);
        }

        return $c;
    }

    private function storedHash(?Applicant $a = null): string
    {
        return ($a ?? $this->applicant)->fresh()->getRawOriginal('password');
    }

    private function assertPasswordUnchanged(string $plain = self::OLD, ?Applicant $a = null): void
    {
        $this->assertTrue(
            Hash::check($plain, $this->storedHash($a)),
            'The stored password should not have changed.'
        );
    }

    /** True only when the callable throws an HttpException with status 403. */
    private function refusedWith403(callable $action): bool
    {
        try {
            $action();
            return false;
        } catch (HttpException $e) {
            return $e->getStatusCode() === 403;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ── ACCESS ───────────────────────────────────────────────────────

    public function test_applicant_can_open_page(): void
    {
        $this->actingAs($this->applicant, 'applicant')->get(route('applicant.password'))->assertOk();
    }

    public function test_guest_is_redirected_to_applicant_login(): void
    {
        $this->get(route('applicant.password'))->assertRedirect(route('applicant.login'));
    }

    public function test_admin_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('applicant.password'))
            ->assertRedirect(route('applicant.login'));
    }

    public function test_staff_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->staff)
            ->get(route('applicant.password'))
            ->assertRedirect(route('applicant.login'));
    }

    /**
     * KNOWN GAP — expected to FAIL until the component checks the guard (APP-PW-1).
     *
     * The route blocks guests, so this only matters if the component is reached
     * another way. Today the component has no mount(), and save() crashes on a
     * null user instead of refusing the request.
     *
     * FIX: add to the component:
     *   public function mount(): void
     *   {
     *       abort_unless(Auth::guard('applicant')->check(), 403);
     *   }
     */
    public function test_component_itself_rejects_a_guest(): void
    {
        $this->assertTrue(
            $this->refusedWith403(fn () => (new ChangePassword())->mount()),
            'Guest mount() should be refused with 403.'
        );
    }

    /** KNOWN GAP — expected to FAIL until mount() checks the guard (APP-PW-1). */
    public function test_admin_on_web_guard_cannot_mount_component(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new ChangePassword())->mount()),
            'Admin (web guard) mount() should be refused with 403.'
        );
    }

    /** Passes whether the app rejects the call or crashes; either way no password may change. */
    public function test_save_without_applicant_login_changes_nothing(): void
    {
        try {
            $component = Livewire::actingAs($this->admin)->test(ChangePassword::class);
            $this->fill($component)->call('save');
        } catch (\Throwable $e) {
            // refusing is fine
        }

        $this->assertPasswordUnchanged(self::OLD, $this->applicant);
        $this->assertPasswordUnchanged('OtherPassword9', $this->other);
    }

    // ── RENDER ───────────────────────────────────────────────────────

    public function test_form_starts_empty(): void
    {
        $this->page()
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');
    }

    public function test_viewing_the_page_never_changes_the_password(): void
    {
        $this->page();

        $this->assertPasswordUnchanged();
    }

    // ── SUCCESS ──────────────────────────────────────────────────────

    public function test_correct_current_password_changes_the_password(): void
    {
        $this->fill($this->page())->call('save')->assertHasNoErrors();

        $this->assertTrue(Hash::check(self::NEW, $this->storedHash()));
        $this->assertFalse(Hash::check(self::OLD, $this->storedHash()));
    }

    public function test_success_shows_the_status_message(): void
    {
        $this->fill($this->page())->call('save')->assertSee('Password updated.');
    }

    public function test_new_password_is_stored_hashed_never_as_plain_text(): void
    {
        $this->fill($this->page())->call('save');

        $stored = $this->storedHash();

        $this->assertNotSame(self::NEW, $stored);
        $this->assertStringNotContainsString(self::NEW, $stored);
        $this->assertFalse(Hash::needsRehash($stored), 'Stored value should be a current-format hash.');
    }

    public function test_success_clears_all_three_fields(): void
    {
        $this->fill($this->page())
            ->call('save')
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');
    }

    public function test_success_updates_the_updated_at_timestamp(): void
    {
        $before = $this->applicant->fresh()->updated_at;

        $this->travelTo(now()->addDay()->startOfSecond());
        $this->fill($this->page())->call('save');

        $after = $this->applicant->fresh()->updated_at;

        $this->assertTrue($after->greaterThan($before));
        $this->assertTrue($after->isSameSecond(now()));
    }

    public function test_only_the_logged_in_applicants_password_changes(): void
    {
        $otherHashBefore = $this->storedHash($this->other);

        $this->fill($this->page())->call('save');

        $this->assertSame($otherHashBefore, $this->storedHash($this->other));
        $this->assertPasswordUnchanged('OtherPassword9', $this->other);
    }

    public function test_each_applicant_changes_only_their_own_password(): void
    {
        $this->fill($this->page($this->applicant))->call('save');
        $this->fill($this->page($this->other), [
            'current_password' => 'OtherPassword9',
            'password'         => 'AnotherNew3',
        ])->call('save');

        $this->assertTrue(Hash::check(self::NEW, $this->storedHash($this->applicant)));
        $this->assertTrue(Hash::check('AnotherNew3', $this->storedHash($this->other)));
        $this->assertFalse(Hash::check('AnotherNew3', $this->storedHash($this->applicant)));
    }

    public function test_password_can_be_changed_again_using_the_new_password_as_current(): void
    {
        $this->fill($this->page())->call('save');

        // A real second request would reload the user; mimic that here.
        $this->applicant->refresh();

        $this->fill($this->page(), [
            'current_password' => self::NEW,
            'password'         => 'ThirdPassword3',
        ])->call('save')->assertHasNoErrors();

        $this->assertTrue(Hash::check('ThirdPassword3', $this->storedHash()));
    }

    // ── CURRENT PASSWORD ─────────────────────────────────────────────

    public function test_current_password_is_required(): void
    {
        $this->fill($this->page(), ['current_password' => ''])
            ->call('save')
            ->assertHasErrors(['current_password' => 'required']);

        $this->assertPasswordUnchanged();
    }

    public function test_wrong_current_password_is_refused_with_a_message(): void
    {
        $component = $this->fill($this->page(), ['current_password' => 'WrongPassword1'])
            ->call('save')
            ->assertHasErrors(['current_password'])
            ->assertDontSee('Password updated.');

        $this->assertSame('Your current password is incorrect.', $component->errors()->first('current_password'));
        $this->assertPasswordUnchanged();
    }

    public function test_current_password_check_is_case_sensitive(): void
    {
        $this->fill($this->page(), ['current_password' => strtolower(self::OLD)])
            ->call('save')
            ->assertHasErrors(['current_password']);

        $this->assertPasswordUnchanged();
    }

    public function test_current_password_with_extra_whitespace_is_refused(): void
    {
        $this->fill($this->page(), ['current_password' => ' ' . self::OLD . ' '])
            ->call('save')
            ->assertHasErrors(['current_password']);

        $this->assertPasswordUnchanged();
    }

    public function test_a_new_password_equal_to_the_stored_hash_is_not_accepted_as_current(): void
    {
        // Someone who can read the hash must not be able to use it as the current password.
        $this->fill($this->page(), ['current_password' => $this->storedHash()])
            ->call('save')
            ->assertHasErrors(['current_password']);

        $this->assertPasswordUnchanged();
    }

    public function test_wrong_current_password_keeps_the_typed_values_and_shows_no_success(): void
    {
        $this->fill($this->page(), ['current_password' => 'WrongPassword1'])
            ->call('save')
            ->assertSet('password', self::NEW)
            ->assertSet('password_confirmation', self::NEW)
            ->assertDontSee('Password updated.');
    }

    public function test_repeated_wrong_attempts_never_change_the_password(): void
    {
        $component = $this->page();

        foreach (['Wrong1111', 'Wrong2222', 'Wrong3333'] as $guess) {
            $this->fill($component, ['current_password' => $guess])->call('save');
        }

        $this->assertPasswordUnchanged();
    }

    // ── NEW PASSWORD ─────────────────────────────────────────────────

    public function test_new_password_is_required(): void
    {
        $this->fill($this->page(), ['password' => '', 'password_confirmation' => ''])
            ->call('save')
            ->assertHasErrors(['password' => 'required']);

        $this->assertPasswordUnchanged();
    }

    public function test_new_password_length_boundaries(): void
    {
        $this->fill($this->page(), ['password' => 'Abcdef1'])   // 7 chars
            ->call('save')
            ->assertHasErrors(['password' => 'min']);

        $this->assertPasswordUnchanged();

        $this->fill($this->page(), ['password' => 'Abcdef12'])  // 8 chars
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('Abcdef12', $this->storedHash()));
    }

    public function test_confirmation_is_required(): void
    {
        $this->fill($this->page(), ['password_confirmation' => ''])
            ->call('save')
            ->assertHasErrors(['password' => 'confirmed']);

        $this->assertPasswordUnchanged();
    }

    public function test_confirmation_must_match(): void
    {
        $this->fill($this->page(), ['password_confirmation' => 'SomethingElse9'])
            ->call('save')
            ->assertHasErrors(['password' => 'confirmed']);

        $this->assertPasswordUnchanged();
    }

    public function test_confirmation_is_case_sensitive(): void
    {
        $this->fill($this->page(), ['password_confirmation' => strtolower(self::NEW)])
            ->call('save')
            ->assertHasErrors(['password' => 'confirmed']);

        $this->assertPasswordUnchanged();
    }

    public function test_new_password_must_differ_from_the_current_one(): void
    {
        $this->fill($this->page(), ['password' => self::OLD])
            ->call('save')
            ->assertHasErrors(['password' => 'different']);

        $this->assertPasswordUnchanged();
    }

    public function test_new_password_differing_only_by_case_is_allowed(): void
    {
        $this->fill($this->page(), ['password' => strtolower(self::OLD)])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check(strtolower(self::OLD), $this->storedHash()));
    }

    public function test_new_password_may_contain_spaces_and_symbols(): void
    {
        $this->fill($this->page(), ['password' => 'my new p@ss w0rd!'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('my new p@ss w0rd!', $this->storedHash()));
    }

    public function test_failed_validation_keeps_the_typed_values_and_shows_no_success(): void
    {
        $this->fill($this->page(), ['password_confirmation' => 'Mismatch99'])
            ->call('save')
            ->assertSet('current_password', self::OLD)
            ->assertSet('password', self::NEW)
            ->assertDontSee('Password updated.');
    }

    // ── ORDER OF CHECKS ──────────────────────────────────────────────

    public function test_validation_runs_before_the_current_password_check(): void
    {
        // Wrong current password AND a too-short new password: the rule error wins,
        // and the "incorrect" message is not shown yet.
        $component = $this->fill($this->page(), [
            'current_password' => 'WrongPassword1',
            'password'         => 'short',
        ])->call('save')->assertHasErrors(['password' => 'min']);

        $this->assertNotSame(
            'Your current password is incorrect.',
            $component->errors()->first('current_password')
        );
        $this->assertPasswordUnchanged();
    }

    public function test_all_empty_form_reports_both_required_errors_and_changes_nothing(): void
    {
        $this->page()
            ->call('save')
            ->assertHasErrors(['current_password' => 'required', 'password' => 'required']);

        $this->assertPasswordUnchanged();
    }

    // ── EDGE CASES ───────────────────────────────────────────────────

    public function test_other_applicants_stay_logged_in_with_their_own_password_after_a_change(): void
    {
        $this->fill($this->page())->call('save');

        $this->assertTrue(Hash::check('OtherPassword9', $this->storedHash($this->other)));
    }

    public function test_the_change_touches_only_the_password_and_updated_at_columns(): void
    {
        $before = $this->applicant->fresh()->only(['first_name', 'last_name', 'email', 'barangay_id']);

        $this->fill($this->page())->call('save');

        $after = $this->applicant->fresh()->only(['first_name', 'last_name', 'email', 'barangay_id']);

        $this->assertSame($before, $after);
    }
}