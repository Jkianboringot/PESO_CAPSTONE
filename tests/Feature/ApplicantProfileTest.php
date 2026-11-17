<?php

namespace Tests\Feature;

use App\Livewire\Applicant\Profile;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Applicant "My Registration" page: an applicant edits their OWN contact
 * details and PWD (disability) information.
 *
 * WHY: contact_number is also the applicant's login identifier, so it must stay
 * unique. The page must only ever read and write the logged-in applicant's own
 * row, and bad input must never reach the database.
 *
 * NOTE ON ASSUMPTIONS (change these to match your app)
 * ────────────────────────────────────────────────────
 *  - Route names: 'applicant.profile' (this page) and 'applicant.login' (guest redirect).
 *  - Applicants log in through the 'applicant' guard. Admin and staff use the 'web' guard.
 *  - Applicant::$disabilities is cast to array and is mass-assignable (the component
 *    calls ->update(['disabilities' => ...])).
 *  - SetsUpAdminAndStaff provides $this->admin and $this->staff via setUpUsers().
 *
 * NOTE ON FLASH MESSAGES
 * ──────────────────────
 * Flash messages are checked with assertSee() / assertDontSee() on the rendered
 * page, not with session('status'), because the session is not readable from the
 * test after a Livewire call.
 *
 * NOTE ON actingAs()
 * ──────────────────
 * Livewire::actingAs($applicant, 'applicant') hands the component the model object
 * that is already in memory. It does NOT reload it from the database. If a test
 * changes the database directly, change the in-memory model too (forceFill()->save()
 * or set the property) or the component will still see the old values.
 *
 * NOTE ON KNOWN BUGS AND GAPS
 * ───────────────────────────
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions.
 *
 *   APP-PROF-1  mount() has no guard check               (2 tests)
 *   APP-PROF-2  differently formatted duplicate number   (1 test)
 *   APP-PROF-3  contact number with no digits is valid   (1 test)
 *   APP-PROF-6  save() reports only the first bad field  (1 test)
 */
class ApplicantProfileTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private Applicant $applicant;
    private Applicant $other;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $municipality   = Municipality::firstOrCreate(['name' => 'Virac', 'province' => 'Catanduanes']);
        $this->barangay = Barangay::firstOrCreate(['name' => 'San Jose', 'municipality_id' => $municipality->id]);

        $this->applicant = Applicant::factory()->create([
            'barangay_id'    => $this->barangay->id,
            'contact_number' => '09171111111',
            'email'          => 'me@example.com',
            'address'        => 'Purok 1',
        ]);

        $this->other = Applicant::factory()->create([
            'barangay_id'    => $this->barangay->id,
            'contact_number' => '09172222222',
            'email'          => 'other@example.com',
            'address'        => 'Purok 2',
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function page(?Applicant $as = null)
    {
        return Livewire::actingAs($as ?? $this->applicant, 'applicant')->test(Profile::class);
    }

    /** Fill the contact form with valid values; override any field. */
    private function fillContact($component, array $over = [])
    {
        $v = array_merge([
            'contact_number' => '09179998888',
            'email'          => 'new@example.com',
            'address'        => 'Purok 5',
        ], $over);

        return $component
            ->set('contact_number', $v['contact_number'])
            ->set('email', $v['email'])
            ->set('address', $v['address']);
    }

    private function row(?Applicant $a = null): object
    {
        return DB::table('applicants')->where('id', ($a ?? $this->applicant)->id)->first();
    }

    private function savedDisabilities(?Applicant $a = null): ?array
    {
        $raw = $this->row($a)->disabilities;

        return $raw === null ? null : json_decode($raw, true);
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
        $this->actingAs($this->applicant, 'applicant')->get(route('applicant.profile'))->assertOk();
    }

    public function test_guest_is_redirected_to_applicant_login(): void
    {
        $this->get(route('applicant.profile'))->assertRedirect(route('applicant.login'));
    }

    public function test_admin_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('applicant.profile'))
            ->assertRedirect(route('applicant.login'));
    }

    public function test_staff_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->staff)
            ->get(route('applicant.profile'))
            ->assertRedirect(route('applicant.login'));
    }

    /**
     * KNOWN GAP — expected to FAIL until mount() checks the guard (APP-PROF-1).
     *
     * The route blocks guests, so this only matters if the component is reached
     * another way. Today a guest makes mount() read properties on null and crash
     * with a 500 instead of a clean refusal.
     *
     * FIX: first line of mount():
     *   abort_unless(Auth::guard('applicant')->check(), 403);
     */
    public function test_component_itself_rejects_a_guest(): void
    {
        $this->assertTrue(
            $this->refusedWith403(fn () => (new Profile())->mount()),
            'Guest mount() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until mount() checks the guard (APP-PROF-1).
     * Same fix as above: an admin logged in on the web guard is not an applicant.
     */
    public function test_admin_on_web_guard_cannot_mount_component(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new Profile())->mount()),
            'Admin (web guard) mount() should be refused with 403.'
        );
    }

    // ── LOAD ─────────────────────────────────────────────────────────

    public function test_form_is_filled_with_the_applicants_own_values(): void
    {
        $this->page()
            ->assertSet('contact_number', '09171111111')
            ->assertSet('email', 'me@example.com')
            ->assertSet('address', 'Purok 1');
    }

    public function test_form_never_shows_another_applicants_values(): void
    {
        $this->page()
            ->assertSet('contact_number', '09171111111')
            ->assertDontSee('09172222222')
            ->assertDontSee('other@example.com');
    }

    public function test_null_email_loads_as_empty_string(): void
    {
        // update the in-memory model too: actingAs() does not reload it from the database
        $this->applicant->forceFill(['email' => null])->save();

        $this->page()->assertSet('email', '');
    }

    public function test_null_disabilities_load_as_empty_array(): void
    {
        $this->page()->assertSet('disabilities', []);
    }

    public function test_saved_disabilities_are_preselected(): void
    {
        $this->applicant->update(['disabilities' => ['Visual Disability', 'Other']]);

        $this->page()->assertSet('disabilities', ['Visual Disability', 'Other']);
    }

    public function test_render_passes_barangay_name_and_options(): void
    {
        $component = $this->page();

        $this->assertSame('San Jose', $component->viewData('barangay'));
        $this->assertSame(Profile::DISABILITIES, $component->viewData('disabilityOptions'));
        $this->assertSame($this->applicant->id, $component->viewData('applicant')->id);
    }

    public function test_render_handles_an_applicant_whose_barangay_is_missing(): void
    {
        // barangay_id is NOT NULL in the database, so point the in-memory model at a
        // barangay that does not exist instead (not saved)
        $this->applicant->barangay_id = 999999;

        $this->assertNull($this->page()->viewData('barangay'));
    }

    // ── SAVE CONTACT: happy path ─────────────────────────────────────

    public function test_save_updates_contact_details(): void
    {
        $this->fillContact($this->page())->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('applicants', [
            'id'             => $this->applicant->id,
            'contact_number' => '09179998888',
            'email'          => 'new@example.com',
            'address'        => 'Purok 5',
        ]);
    }

    public function test_save_shows_success_message(): void
    {
        $this->fillContact($this->page())->call('save')->assertSee('Your contact details were updated.');
    }

    public function test_save_only_updates_the_logged_in_applicant(): void
    {
        $this->fillContact($this->page())->call('save');

        $this->assertDatabaseHas('applicants', [
            'id'             => $this->other->id,
            'contact_number' => '09172222222',
            'email'          => 'other@example.com',
            'address'        => 'Purok 2',
        ]);
    }

    public function test_save_does_not_touch_other_columns(): void
    {
        $before = $this->row();

        $this->fillContact($this->page())->call('save');

        $after = $this->row();
        foreach (['first_name', 'last_name', 'birthdate', 'sex', 'status', 'is_active', 'barangay_id', 'password', 'reference_id'] as $col) {
            $this->assertEquals($before->$col, $after->$col, "$col changed unexpectedly");
        }
    }

    public function test_save_does_not_touch_disabilities(): void
    {
        $this->applicant->update(['disabilities' => ['Chronic Illness']]);

        $this->fillContact($this->page())->call('save');

        $this->assertSame(['Chronic Illness'], $this->savedDisabilities());
    }

    public function test_save_refreshes_updated_at(): void
    {
        $old = $this->row()->updated_at;

        $this->travel(2)->hours();
        $this->fillContact($this->page())->call('save');

        $this->assertTrue(\Illuminate\Support\Carbon::parse($this->row()->updated_at)->gt($old));
    }

    public function test_saving_without_changes_is_not_flagged_as_duplicate(): void
    {
        $this->page()->call('save')->assertHasNoErrors()->assertSee('Your contact details were updated.');
    }

    public function test_applicant_can_keep_own_email_while_changing_the_phone(): void
    {
        $this->page()->set('contact_number', '09170001111')->call('save')->assertHasNoErrors();

        $this->assertSame('me@example.com', $this->row()->email);
    }

    // ── SAVE CONTACT: contact_number ─────────────────────────────────

    public function test_contact_number_is_required(): void
    {
        $this->page()->set('contact_number', '')->call('save')->assertHasErrors(['contact_number' => 'required']);

        $this->assertSame('09171111111', $this->row()->contact_number);
    }

    public function test_whitespace_only_contact_number_is_rejected(): void
    {
        $this->page()->set('contact_number', '        ')->call('save')->assertHasErrors(['contact_number' => 'required']);
    }

    public function test_contact_number_rejects_letters(): void
    {
        $this->page()->set('contact_number', '0917abc1234')->call('save')->assertHasErrors(['contact_number' => 'regex']);
    }

    public function test_contact_number_rejects_other_symbols(): void
    {
        foreach (['0917#1234567', '0917.123.4567', '0917/1234567', '<script>'] as $bad) {
            $this->page()->set('contact_number', $bad)->call('save')->assertHasErrors(['contact_number']);
        }

        $this->assertSame('09171111111', $this->row()->contact_number);
    }

    public function test_contact_number_shorter_than_7_characters_is_rejected(): void
    {
        $this->page()->set('contact_number', '123456')->call('save')->assertHasErrors(['contact_number' => 'regex']);
    }

    public function test_contact_number_of_exactly_7_characters_is_accepted(): void
    {
        $this->page()->set('contact_number', '1234567')->call('save')->assertHasNoErrors();

        $this->assertSame('1234567', $this->row()->contact_number);
    }

    public function test_contact_number_of_exactly_20_characters_is_accepted(): void
    {
        $number = str_repeat('1', 20);

        $this->page()->set('contact_number', $number)->call('save')->assertHasNoErrors();

        $this->assertSame($number, $this->row()->contact_number);
    }

    public function test_contact_number_longer_than_20_characters_is_rejected(): void
    {
        $this->page()->set('contact_number', str_repeat('1', 21))->call('save')->assertHasErrors(['contact_number' => 'regex']);
    }

    public function test_contact_number_accepts_common_phone_formats(): void
    {
        foreach (['+63 917 123 4567', '(02) 8123-4567', '0917-123-4567'] as $ok) {
            $this->page()->set('contact_number', $ok)->call('save')->assertHasNoErrors();
        }
    }

    public function test_contact_number_must_be_unique(): void
    {
        $this->fillContact($this->page(), ['contact_number' => '09172222222'])
            ->call('save')
            ->assertHasErrors(['contact_number' => 'unique']);

        $this->assertSame('09171111111', $this->row()->contact_number);
    }

    public function test_duplicate_contact_number_error_blocks_the_whole_save(): void
    {
        $this->fillContact($this->page(), ['contact_number' => '09172222222', 'address' => 'Changed Address'])
            ->call('save');

        $this->assertSame('Purok 1', $this->row()->address);
    }

    /**
     * KNOWN BUG — expected to FAIL (APP-PROF-2).
     *
     * The unique check compares the exact text, so the same number typed with
     * spaces or dashes is treated as a different number. Two applicants can end
     * up with the same login number.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Applicant A has 09172222222.
     *   2. Applicant B opens My Registration and types 0917-222-2222, then saves.
     *   3. BUG: saved. Expected: "The contact number has already been taken."
     *
     * FIX: in save(), right before validate():
     *   $this->contact_number = preg_replace('/[\s\-()]+/', '', $this->contact_number);
     * Clean any existing rows that were saved with dashes or spaces.
     */
    public function test_differently_formatted_duplicate_contact_number_is_rejected(): void
    {
        $this->fillContact($this->page(), ['contact_number' => '0917-222-2222'])
            ->call('save')
            ->assertHasErrors(['contact_number']);

        $this->assertSame('09171111111', $this->row()->contact_number);
    }

    /**
     * KNOWN BUG — expected to FAIL (APP-PROF-3).
     *
     * The regex accepts "-------" because the allowed characters include dashes
     * and spaces and nothing requires digits.
     *
     * FIX: the same normalising line as APP-PROF-2 turns "-------" into an empty
     * string, which then fails "required". (Alternatively require at least 7
     * digits in the regex.)
     */
    public function test_contact_number_must_contain_digits(): void
    {
        $this->page()->set('contact_number', '-------')->call('save')->assertHasErrors(['contact_number']);

        $this->assertSame('09171111111', $this->row()->contact_number);
    }

    /**
     * REGRESSION — passes today. Values reach the component already trimmed, so
     * this guards against that changing.
     */
    public function test_saved_values_are_trimmed(): void
    {
        $this->fillContact($this->page(), [
            'contact_number' => '  09179998888  ',
            'email'          => 'new@example.com',
            'address'        => '  Purok 5  ',
        ])->call('save');

        $this->assertSame('09179998888', $this->row()->contact_number);
        $this->assertSame('Purok 5', $this->row()->address);
    }

    /** REGRESSION — passes today. A padded number must not slip past the unique check. */
    public function test_padded_contact_number_cannot_duplicate_another(): void
    {
        $this->fillContact($this->page(), ['contact_number' => ' 09172222222 '])
            ->call('save')
            ->assertHasErrors(['contact_number' => 'unique']);
    }

    // ── SAVE CONTACT: email ──────────────────────────────────────────

    public function test_email_is_optional_and_stored_as_null_when_empty(): void
    {
        $this->fillContact($this->page(), ['email' => ''])->call('save')->assertHasNoErrors();

        $this->assertNull($this->row()->email);
    }

    public function test_email_must_be_valid(): void
    {
        foreach (['not-an-email', 'a@', '@b.com', 'a b@c.com'] as $bad) {
            $this->fillContact($this->page(), ['email' => $bad])->call('save')->assertHasErrors(['email' => 'email']);
        }

        $this->assertSame('me@example.com', $this->row()->email);
    }

    public function test_email_too_long_is_rejected(): void
    {
        $this->fillContact($this->page(), ['email' => str_repeat('a', 250) . '@x.com'])
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_email_must_be_unique(): void
    {
        $this->fillContact($this->page(), ['email' => 'other@example.com'])
            ->call('save')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertSame('me@example.com', $this->row()->email);
    }

    public function test_applicant_can_resave_their_own_email(): void
    {
        $this->fillContact($this->page(), ['email' => 'me@example.com'])->call('save')->assertHasNoErrors();
    }

    public function test_two_applicants_can_both_have_no_email(): void
    {
        DB::table('applicants')->where('id', $this->other->id)->update(['email' => null]);

        $this->fillContact($this->page(), ['email' => ''])->call('save')->assertHasNoErrors();

        $this->assertNull($this->row()->email);
        $this->assertNull($this->row($this->other)->email);
    }

    /** REGRESSION — passes today. A whitespace-only email must not be saved as blank text. */
    public function test_whitespace_only_email_is_not_saved_as_blank_text(): void
    {
        $this->fillContact($this->page(), ['email' => '   '])->call('save');

        $this->assertNull($this->row()->email);
    }

    // ── SAVE CONTACT: address ────────────────────────────────────────

    public function test_address_is_required(): void
    {
        $this->fillContact($this->page(), ['address' => ''])->call('save')->assertHasErrors(['address' => 'required']);

        $this->assertSame('Purok 1', $this->row()->address);
    }

    public function test_whitespace_only_address_is_rejected(): void
    {
        $this->fillContact($this->page(), ['address' => '     '])->call('save')->assertHasErrors(['address' => 'required']);
    }

    public function test_address_of_exactly_255_characters_is_accepted(): void
    {
        $address = str_repeat('a', 255);

        $this->fillContact($this->page(), ['address' => $address])->call('save')->assertHasNoErrors();

        $this->assertSame($address, $this->row()->address);
    }

    public function test_address_longer_than_255_characters_is_rejected(): void
    {
        $this->fillContact($this->page(), ['address' => str_repeat('a', 256)])
            ->call('save')
            ->assertHasErrors(['address' => 'max']);
    }

    public function test_address_with_html_is_escaped_when_shown_again(): void
    {
        $this->fillContact($this->page(), ['address' => '<script>alert(1)</script>'])->call('save');

        $this->page()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ── SAVE CONTACT: failures ───────────────────────────────────────

    public function test_failed_save_changes_nothing_in_the_database(): void
    {
        $before = $this->row();

        $this->fillContact($this->page(), ['contact_number' => 'bad', 'email' => 'bad', 'address' => ''])->call('save');

        $this->assertEquals($before, $this->row());
    }

    public function test_failed_save_does_not_show_success_message(): void
    {
        $this->fillContact($this->page(), ['address' => ''])
            ->call('save')
            ->assertDontSee('Your contact details were updated.');
    }

    /**
     * KNOWN BUG — expected to FAIL (APP-PROF-6).
     *
     * save() calls validateOnly() three times in a row. The first failure throws,
     * so an applicant with a bad phone number AND a bad email only sees the phone
     * error, fixes it, saves again, and only then sees the email error.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Open My Registration, clear Contact number, type "bad" in Email, clear Address.
     *   2. Click Save.
     *   3. BUG: only the contact number error shows. Expected: all three errors.
     *
     * FIX: in save(), replace the three validateOnly() lines with:
     *   $this->validate();
     * validate() uses rules() and does not touch $disabilities.
     */
    public function test_all_invalid_fields_report_errors_together(): void
    {
        $this->fillContact($this->page(), ['contact_number' => '', 'email' => 'bad', 'address' => ''])
            ->call('save')
            ->assertHasErrors(['contact_number', 'email', 'address']);
    }

    public function test_save_after_the_number_was_taken_meanwhile_is_rejected(): void
    {
        $component = $this->fillContact($this->page(), ['contact_number' => '09175550000']);

        // another applicant registers this number while the form is open
        DB::table('applicants')->where('id', $this->other->id)->update(['contact_number' => '09175550000']);

        $component->call('save')->assertHasErrors(['contact_number' => 'unique']);

        $this->assertSame('09171111111', $this->row()->contact_number);
    }

    // ── DISABILITIES ─────────────────────────────────────────────────

    public function test_save_disabilities_stores_the_selection(): void
    {
        $this->page()
            ->set('disabilities', ['Visual Disability', 'Chronic Illness'])
            ->call('saveDisabilities')
            ->assertHasNoErrors()
            ->assertSee('Your PWD information was saved.');

        $this->assertSame(['Visual Disability', 'Chronic Illness'], $this->savedDisabilities());
    }

    public function test_every_listed_disability_is_accepted(): void
    {
        $this->page()->set('disabilities', Profile::DISABILITIES)->call('saveDisabilities')->assertHasNoErrors();

        $this->assertSame(Profile::DISABILITIES, $this->savedDisabilities());
    }

    public function test_selection_is_saved_in_the_standard_order(): void
    {
        $this->page()
            ->set('disabilities', ['Other', 'Hearing Disability', 'Visual Disability'])
            ->call('saveDisabilities');

        $this->assertSame(['Visual Disability', 'Hearing Disability', 'Other'], $this->savedDisabilities());
    }

    public function test_component_state_is_cleaned_after_saving(): void
    {
        $this->page()
            ->set('disabilities', ['Other', 'Visual Disability'])
            ->call('saveDisabilities')
            ->assertSet('disabilities', ['Visual Disability', 'Other']);
    }

    public function test_duplicate_selections_are_saved_once(): void
    {
        $this->page()
            ->set('disabilities', ['Chronic Illness', 'Chronic Illness'])
            ->call('saveDisabilities')
            ->assertHasNoErrors();

        $this->assertSame(['Chronic Illness'], $this->savedDisabilities());
    }

    public function test_selecting_nothing_stores_null(): void
    {
        $this->page()->set('disabilities', [])->call('saveDisabilities')->assertHasNoErrors();

        $this->assertNull($this->row()->disabilities);
    }

    public function test_clearing_a_previous_selection_resets_it_to_null(): void
    {
        $this->applicant->update(['disabilities' => ['Visual Disability']]);

        $this->page()->set('disabilities', [])->call('saveDisabilities');

        $this->assertNull($this->row()->disabilities);
    }

    public function test_unknown_disability_is_rejected(): void
    {
        $this->page()
            ->set('disabilities', ['Hacked Value'])
            ->call('saveDisabilities')
            ->assertHasErrors(['disabilities.0']);

        $this->assertNull($this->row()->disabilities);
    }

    public function test_one_bad_value_rejects_the_whole_selection(): void
    {
        $this->applicant->update(['disabilities' => ['Other']]);

        $this->page()
            ->set('disabilities', ['Visual Disability', 'Hacked Value'])
            ->call('saveDisabilities')
            ->assertHasErrors(['disabilities.1']);

        $this->assertSame(['Other'], $this->savedDisabilities());
    }

    public function test_disability_check_is_case_sensitive(): void
    {
        $this->page()
            ->set('disabilities', ['visual disability'])
            ->call('saveDisabilities')
            ->assertHasErrors(['disabilities.0']);
    }

    public function test_failed_disability_save_shows_no_success_message(): void
    {
        $this->page()
            ->set('disabilities', ['Hacked Value'])
            ->call('saveDisabilities')
            ->assertDontSee('Your PWD information was saved.');
    }

    public function test_save_disabilities_only_updates_the_logged_in_applicant(): void
    {
        $this->other->update(['disabilities' => ['Speech Impairment']]);

        $this->page()->set('disabilities', ['Visual Disability'])->call('saveDisabilities');

        $this->assertSame(['Visual Disability'], $this->savedDisabilities());
        $this->assertSame(['Speech Impairment'], $this->savedDisabilities($this->other));
    }

    public function test_save_disabilities_does_not_save_unsaved_contact_edits(): void
    {
        $this->page()
            ->set('contact_number', '09170009999')
            ->set('address', 'Unsaved Address')
            ->set('disabilities', ['Visual Disability'])
            ->call('saveDisabilities');

        $this->assertSame('09171111111', $this->row()->contact_number);
        $this->assertSame('Purok 1', $this->row()->address);
    }

    public function test_invalid_contact_fields_do_not_block_saving_disabilities(): void
    {
        $this->page()
            ->set('contact_number', 'bad')
            ->set('disabilities', ['Visual Disability'])
            ->call('saveDisabilities')
            ->assertHasNoErrors();

        $this->assertSame(['Visual Disability'], $this->savedDisabilities());
    }

    public function test_disability_save_survives_a_reload(): void
    {
        $this->page()->set('disabilities', ['Hearing Disability'])->call('saveDisabilities');

        $this->page()->assertSet('disabilities', ['Hearing Disability']);
    }

    // ── PERMISSIONS: ISOLATION BETWEEN APPLICANTS ────────────────────

    public function test_each_applicant_edits_only_their_own_record(): void
    {
        $this->fillContact($this->page($this->applicant), ['contact_number' => '09170000001', 'email' => 'a@example.com', 'address' => 'A Street'])->call('save');
        $this->fillContact($this->page($this->other), ['contact_number' => '09170000002', 'email' => 'b@example.com', 'address' => 'B Street'])->call('save');

        $this->assertSame('09170000001', $this->row($this->applicant)->contact_number);
        $this->assertSame('A Street', $this->row($this->applicant)->address);
        $this->assertSame('09170000002', $this->row($this->other)->contact_number);
        $this->assertSame('B Street', $this->row($this->other)->address);
    }

    public function test_no_public_property_can_redirect_a_save_to_another_applicant(): void
    {
        // The component exposes no applicant id; the target row always comes from the guard.
        $properties = array_keys(get_class_vars(Profile::class));

        $this->assertNotContains('id', $properties);
        $this->assertNotContains('applicant_id', $properties);
    }

    public function test_admin_login_does_not_let_an_applicant_page_edit_any_applicant(): void
    {
        $before = $this->row();

        try {
            $this->fillContact(Livewire::actingAs($this->admin)->test(Profile::class))->call('save');
        } catch (\Throwable $e) {
            // refusing is fine
        }

        $this->assertEquals($before, $this->row());
        $this->assertEquals($this->row($this->other)->contact_number, '09172222222');
    }
}