<?php

namespace Tests\Feature;

use App\Livewire\Applicant\EducationEmployment;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Applicant "Education & Employment" page: an applicant records their highest
 * educational attainment. One education row per applicant, created on first save
 * and updated after that.
 *
 * WHY: the education level feeds PESO's applicant matching and reports. The page
 * must only ever read and write the logged-in applicant's own row, never create
 * a second row for the same applicant, and never accept a level or year the
 * system does not recognise.
 *
 * NOTE ON ASSUMPTIONS (change these to match your app)
 * ────────────────────────────────────────────────────
 *  - Route names: 'applicant.employment' (this page) and 'applicant.login' (guest redirect).
 *  - Applicants log in through the 'applicant' guard. Admin and staff use the 'web' guard.
 *  - Table `education` has: applicant_id, highest_level, course_program, school_name,
 *    year_graduated (nullable), created_at, updated_at.
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
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions. Tests marked REGRESSION pass today and
 * guard behaviour that came from the framework rather than from this component.
 *
 *   APP-EDU-1  mount() has no guard check   (2 tests)
 */
class ApplicantEducationTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private Applicant $applicant;
    private Applicant $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $municipality = Municipality::firstOrCreate(['name' => 'Virac', 'province' => 'Catanduanes']);
        $barangay     = Barangay::firstOrCreate(['name' => 'San Jose', 'municipality_id' => $municipality->id]);

        $this->applicant = Applicant::factory()->create(['barangay_id' => $barangay->id]);
        $this->other     = Applicant::factory()->create(['barangay_id' => $barangay->id]);
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function page(?Applicant $as = null)
    {
        return Livewire::actingAs($as ?? $this->applicant, 'applicant')->test(EducationEmployment::class);
    }

    /** Fill the form with valid values; override any field. */
    private function fill($component, array $over = [])
    {
        $v = array_merge([
            'highest_level'  => 'College Graduate',
            'course_program' => 'BS Information Technology',
            'school_name'    => 'Catanduanes State University',
            'year_graduated' => '2019',
        ], $over);

        return $component
            ->set('highest_level', $v['highest_level'])
            ->set('course_program', $v['course_program'])
            ->set('school_name', $v['school_name'])
            ->set('year_graduated', $v['year_graduated']);
    }

    private function giveEducation(Applicant $applicant, array $over = []): void
    {
        DB::table('education')->insert(array_merge([
            'applicant_id'   => $applicant->id,
            'highest_level'  => 'High School',
            'course_program' => null,
            'school_name'    => 'Virac National High School',
            'year_graduated' => 2010,
            'created_at'     => now(),
            'updated_at'     => now(),
        ], $over));
    }

    private function eduRow(?Applicant $a = null): ?object
    {
        return DB::table('education')->where('applicant_id', ($a ?? $this->applicant)->id)->first();
    }

    private function eduCount(?Applicant $a = null): int
    {
        return DB::table('education')->where('applicant_id', ($a ?? $this->applicant)->id)->count();
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
        $this->actingAs($this->applicant, 'applicant')->get(route('applicant.employment'))->assertOk();
    }

    public function test_guest_is_redirected_to_applicant_login(): void
    {
        $this->get(route('applicant.employment'))->assertRedirect(route('applicant.login'));
    }

    public function test_admin_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('applicant.employment'))
            ->assertRedirect(route('applicant.login'));
    }

    public function test_staff_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->staff)
            ->get(route('applicant.employment'))
            ->assertRedirect(route('applicant.login'));
    }

    /**
     * KNOWN GAP — expected to FAIL until mount() checks the guard (APP-EDU-1).
     *
     * The route blocks guests, so this only matters if the component is reached
     * another way. Today a guest gets a working form with no owner.
     *
     * FIX: first line of mount():
     *   abort_unless(Auth::guard('applicant')->check(), 403);
     */
    public function test_component_itself_rejects_a_guest(): void
    {
        $this->assertTrue(
            $this->refusedWith403(fn () => (new EducationEmployment())->mount()),
            'Guest mount() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until mount() checks the guard (APP-EDU-1).
     * Same fix as above: an admin logged in on the web guard is not an applicant.
     */
    public function test_admin_on_web_guard_cannot_mount_component(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new EducationEmployment())->mount()),
            'Admin (web guard) mount() should be refused with 403.'
        );
    }

    /**
     * A guest (or an admin on the web guard) calling save() must never write a row.
     * Passes whether the app rejects the call or the database refuses the null applicant_id.
     */
    public function test_save_without_applicant_login_writes_nothing(): void
    {
        try {
            $this->fill(Livewire::actingAs($this->admin)->test(EducationEmployment::class))->call('save');
        } catch (\Throwable $e) {
            // refusing is fine
        }

        $this->assertDatabaseCount('education', 0);
    }

    // ── LOAD ─────────────────────────────────────────────────────────

    public function test_new_applicant_sees_an_empty_form(): void
    {
        $this->page()
            ->assertSet('highest_level', '')
            ->assertSet('course_program', '')
            ->assertSet('school_name', '')
            ->assertSet('year_graduated', '');
    }

    public function test_existing_education_is_loaded_into_the_form(): void
    {
        $this->giveEducation($this->applicant, [
            'highest_level'  => 'College Graduate',
            'course_program' => 'BS Nursing',
            'school_name'    => 'Bicol University',
            'year_graduated' => 2018,
        ]);

        $this->page()
            ->assertSet('highest_level', 'College Graduate')
            ->assertSet('course_program', 'BS Nursing')
            ->assertSet('school_name', 'Bicol University')
            ->assertSet('year_graduated', '2018');
    }

    public function test_null_columns_load_as_empty_strings(): void
    {
        $this->giveEducation($this->applicant, ['course_program' => null, 'school_name' => null, 'year_graduated' => null]);

        $this->page()
            ->assertSet('course_program', '')
            ->assertSet('school_name', '')
            ->assertSet('year_graduated', '');
    }

    public function test_form_never_loads_another_applicants_education(): void
    {
        $this->giveEducation($this->other, ['school_name' => 'Secret Academy', 'highest_level' => 'Post-Graduate']);

        $this->page()
            ->assertSet('highest_level', '')
            ->assertSet('school_name', '')
            ->assertDontSee('Secret Academy');
    }

    public function test_render_passes_the_level_options(): void
    {
        $this->assertSame(EducationEmployment::LEVELS, $this->page()->viewData('levels'));
    }

    public function test_level_dropdown_shows_every_level(): void
    {
        $component = $this->page();

        foreach (EducationEmployment::LEVELS as $level) {
            $component->assertSee($level);
        }
    }

    // ── SAVE: first save (insert) ────────────────────────────────────

    public function test_first_save_creates_the_education_row(): void
    {
        $this->fill($this->page())->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('education', [
            'applicant_id'   => $this->applicant->id,
            'highest_level'  => 'College Graduate',
            'course_program' => 'BS Information Technology',
            'school_name'    => 'Catanduanes State University',
            'year_graduated' => 2019,
        ]);
    }

    public function test_save_shows_success_message(): void
    {
        $this->fill($this->page())->call('save')->assertSee('Education details saved.');
    }

    public function test_first_save_sets_both_timestamps(): void
    {
        $this->fill($this->page())->call('save');

        $row = $this->eduRow();
        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
    }

    public function test_first_save_only_creates_a_row_for_the_logged_in_applicant(): void
    {
        $this->fill($this->page())->call('save');

        $this->assertSame(1, $this->eduCount($this->applicant));
        $this->assertSame(0, $this->eduCount($this->other));
        $this->assertDatabaseCount('education', 1);
    }

    public function test_year_graduated_is_stored_as_a_number(): void
    {
        $this->fill($this->page(), ['year_graduated' => '2019'])->call('save');

        $this->assertEquals(2019, $this->eduRow()->year_graduated);
    }

    // ── SAVE: later saves (update) ───────────────────────────────────

    public function test_second_save_updates_the_same_row(): void
    {
        $component = $this->fill($this->page())->call('save');

        $this->fill($component, ['highest_level' => 'Post-Graduate', 'school_name' => 'Ateneo'])->call('save');

        $this->assertSame(1, $this->eduCount());
        $this->assertSame('Post-Graduate', $this->eduRow()->highest_level);
        $this->assertSame('Ateneo', $this->eduRow()->school_name);
    }

    public function test_saving_again_from_a_fresh_page_updates_instead_of_inserting(): void
    {
        $this->giveEducation($this->applicant);

        $this->fill($this->page())->call('save');

        $this->assertSame(1, $this->eduCount());
        $this->assertSame('College Graduate', $this->eduRow()->highest_level);
    }

    public function test_update_keeps_created_at_and_refreshes_updated_at(): void
    {
        $this->fill($this->page())->call('save');
        $first = $this->eduRow();

        $this->travel(2)->hours();
        $this->fill($this->page(), ['school_name' => 'Changed School'])->call('save');
        $second = $this->eduRow();

        $this->assertTrue(Carbon::parse($second->created_at)->equalTo(Carbon::parse($first->created_at)));
        $this->assertTrue(Carbon::parse($second->updated_at)->gt(Carbon::parse($first->updated_at)));
    }

    public function test_saving_without_changes_succeeds(): void
    {
        $this->giveEducation($this->applicant, ['highest_level' => 'College Graduate', 'year_graduated' => 2015]);

        $this->page()->call('save')->assertHasNoErrors()->assertSee('Education details saved.');

        $this->assertSame(1, $this->eduCount());
    }

    public function test_row_created_by_another_session_meanwhile_is_updated_not_duplicated(): void
    {
        $component = $this->fill($this->page());

        // a second tab saves first
        $this->giveEducation($this->applicant, ['highest_level' => 'Elementary']);

        $component->call('save')->assertHasNoErrors();

        $this->assertSame(1, $this->eduCount());
        $this->assertSame('College Graduate', $this->eduRow()->highest_level);
    }

    public function test_save_does_not_change_another_applicants_education(): void
    {
        $this->giveEducation($this->other, ['highest_level' => 'Elementary', 'school_name' => 'Other School', 'year_graduated' => 2001]);

        $this->fill($this->page())->call('save');

        $this->assertDatabaseHas('education', [
            'applicant_id'   => $this->other->id,
            'highest_level'  => 'Elementary',
            'school_name'    => 'Other School',
            'year_graduated' => 2001,
        ]);
    }

    public function test_each_applicant_keeps_their_own_education(): void
    {
        $this->fill($this->page($this->applicant), ['school_name' => 'School A'])->call('save');
        $this->fill($this->page($this->other), ['school_name' => 'School B', 'highest_level' => 'Senior High School'])->call('save');

        $this->assertSame('School A', $this->eduRow($this->applicant)->school_name);
        $this->assertSame('School B', $this->eduRow($this->other)->school_name);
        $this->assertSame('Senior High School', $this->eduRow($this->other)->highest_level);
    }

    // ── VALIDATION: highest_level ────────────────────────────────────

    public function test_highest_level_is_required(): void
    {
        $this->fill($this->page(), ['highest_level' => ''])
            ->call('save')
            ->assertHasErrors(['highest_level' => 'required']);

        $this->assertNull($this->eduRow());
    }

    public function test_every_listed_level_is_accepted(): void
    {
        foreach (EducationEmployment::LEVELS as $level) {
            $this->fill($this->page(), ['highest_level' => $level])->call('save')->assertHasNoErrors();

            $this->assertSame($level, $this->eduRow()->highest_level);
        }

        $this->assertSame(1, $this->eduCount());
    }

    public function test_unknown_level_is_rejected(): void
    {
        $this->fill($this->page(), ['highest_level' => 'Doctorate of Everything'])
            ->call('save')
            ->assertHasErrors(['highest_level' => 'in']);

        $this->assertNull($this->eduRow());
    }

    public function test_level_check_is_case_sensitive(): void
    {
        $this->fill($this->page(), ['highest_level' => 'college graduate'])
            ->call('save')
            ->assertHasErrors(['highest_level']);
    }

    public function test_level_with_extra_characters_is_rejected(): void
    {
        foreach (['College Graduate ;', 'College Graduates', '<script>'] as $bad) {
            $this->fill($this->page(), ['highest_level' => $bad])->call('save')->assertHasErrors(['highest_level']);
        }

        $this->assertNull($this->eduRow());
    }

    // ── VALIDATION: year_graduated ───────────────────────────────────

    public function test_year_graduated_is_optional_and_stored_as_null(): void
    {
        $this->fill($this->page(), ['year_graduated' => ''])->call('save')->assertHasNoErrors();

        $this->assertNull($this->eduRow()->year_graduated);
    }

    public function test_year_1950_is_accepted(): void
    {
        $this->fill($this->page(), ['year_graduated' => '1950'])->call('save')->assertHasNoErrors();

        $this->assertEquals(1950, $this->eduRow()->year_graduated);
    }

    public function test_year_1949_is_rejected(): void
    {
        $this->fill($this->page(), ['year_graduated' => '1949'])
            ->call('save')
            ->assertHasErrors(['year_graduated' => 'between']);

        $this->assertNull($this->eduRow());
    }

    public function test_current_year_is_accepted(): void
    {
        $this->fill($this->page(), ['year_graduated' => (string) now()->year])->call('save')->assertHasNoErrors();
    }

    public function test_next_year_is_rejected(): void
    {
        $this->fill($this->page(), ['year_graduated' => (string) (now()->year + 1)])
            ->call('save')
            ->assertHasErrors(['year_graduated' => 'between']);
    }

    public function test_far_future_year_is_rejected(): void
    {
        $this->fill($this->page(), ['year_graduated' => '9999'])->call('save')->assertHasErrors(['year_graduated']);
    }

    public function test_year_rejects_letters_decimals_and_negative_numbers(): void
    {
        foreach (['abc', '2019.5', '-2019', '20x9', '0'] as $bad) {
            $this->fill($this->page(), ['year_graduated' => $bad])->call('save')->assertHasErrors(['year_graduated']);
        }

        $this->assertNull($this->eduRow());
    }

    public function test_two_digit_year_is_rejected(): void
    {
        $this->fill($this->page(), ['year_graduated' => '19'])->call('save')->assertHasErrors(['year_graduated']);
    }

    // ── VALIDATION: course_program and school_name ───────────────────

    public function test_course_and_school_are_optional_and_stored_as_null(): void
    {
        $this->fill($this->page(), ['course_program' => '', 'school_name' => ''])->call('save')->assertHasNoErrors();

        $this->assertNull($this->eduRow()->course_program);
        $this->assertNull($this->eduRow()->school_name);
    }

    public function test_only_the_level_is_needed_to_save(): void
    {
        $this->fill($this->page(), ['course_program' => '', 'school_name' => '', 'year_graduated' => ''])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('College Graduate', $this->eduRow()->highest_level);
    }

    public function test_clearing_optional_fields_on_update_resets_them_to_null(): void
    {
        $this->giveEducation($this->applicant, ['course_program' => 'BS Nursing', 'school_name' => 'Bicol University', 'year_graduated' => 2018]);

        $this->fill($this->page(), ['course_program' => '', 'school_name' => '', 'year_graduated' => ''])->call('save');

        $row = $this->eduRow();
        $this->assertNull($row->course_program);
        $this->assertNull($row->school_name);
        $this->assertNull($row->year_graduated);
    }

    public function test_course_of_exactly_255_characters_is_accepted(): void
    {
        $course = str_repeat('a', 255);

        $this->fill($this->page(), ['course_program' => $course])->call('save')->assertHasNoErrors();

        $this->assertSame($course, $this->eduRow()->course_program);
    }

    public function test_course_longer_than_255_characters_is_rejected(): void
    {
        $this->fill($this->page(), ['course_program' => str_repeat('a', 256)])
            ->call('save')
            ->assertHasErrors(['course_program' => 'max']);
    }

    public function test_school_of_exactly_255_characters_is_accepted(): void
    {
        $school = str_repeat('a', 255);

        $this->fill($this->page(), ['school_name' => $school])->call('save')->assertHasNoErrors();

        $this->assertSame($school, $this->eduRow()->school_name);
    }

    public function test_school_longer_than_255_characters_is_rejected(): void
    {
        $this->fill($this->page(), ['school_name' => str_repeat('a', 256)])
            ->call('save')
            ->assertHasErrors(['school_name' => 'max']);
    }

    /**
     * REGRESSION — passes today. Values reach the component already trimmed, so
     * padded text is saved clean and whitespace-only text is saved as NULL.
     */
    public function test_padded_text_is_saved_trimmed(): void
    {
        $this->fill($this->page(), ['course_program' => '  BS Nursing  ', 'school_name' => '  Bicol University  '])->call('save');

        $this->assertSame('BS Nursing', $this->eduRow()->course_program);
        $this->assertSame('Bicol University', $this->eduRow()->school_name);
    }

    /** REGRESSION — passes today. */
    public function test_whitespace_only_text_is_saved_as_null(): void
    {
        $this->fill($this->page(), ['course_program' => '     ', 'school_name' => '     '])->call('save');

        $this->assertNull($this->eduRow()->course_program);
        $this->assertNull($this->eduRow()->school_name);
    }

    public function test_text_with_html_is_escaped_when_shown_again(): void
    {
        $this->fill($this->page(), ['school_name' => '<script>alert(1)</script>'])->call('save');

        $this->page()->assertDontSee('<script>alert(1)</script>', false);
    }

    // ── VALIDATION: failures ─────────────────────────────────────────

    public function test_all_invalid_fields_report_errors_together(): void
    {
        $this->fill($this->page(), [
            'highest_level'  => '',
            'course_program' => str_repeat('a', 256),
            'school_name'    => str_repeat('a', 256),
            'year_graduated' => 'abc',
        ])->call('save')->assertHasErrors(['highest_level', 'course_program', 'school_name', 'year_graduated']);
    }

    public function test_failed_save_creates_no_row(): void
    {
        $this->fill($this->page(), ['highest_level' => ''])->call('save');

        $this->assertDatabaseCount('education', 0);
    }

    public function test_failed_save_keeps_the_existing_row_unchanged(): void
    {
        $this->giveEducation($this->applicant, ['highest_level' => 'High School', 'school_name' => 'Old School', 'year_graduated' => 2010]);
        $before = $this->eduRow();

        $this->fill($this->page(), ['year_graduated' => 'abc'])->call('save');

        $this->assertEquals($before, $this->eduRow());
    }

    public function test_failed_save_does_not_show_success_message(): void
    {
        $this->fill($this->page(), ['highest_level' => ''])
            ->call('save')
            ->assertDontSee('Education details saved.');
    }

    public function test_errors_clear_after_a_corrected_save(): void
    {
        $component = $this->fill($this->page(), ['year_graduated' => 'abc'])->call('save')->assertHasErrors(['year_graduated']);

        $this->fill($component, ['year_graduated' => '2019'])->call('save')->assertHasNoErrors();

        $this->assertEquals(2019, $this->eduRow()->year_graduated);
    }

    // ── PERMISSIONS: ISOLATION BETWEEN APPLICANTS ────────────────────

    public function test_no_public_property_can_redirect_a_save_to_another_applicant(): void
    {
        // The component exposes no applicant id; the target row always comes from the guard.
        $properties = array_keys(get_class_vars(EducationEmployment::class));

        $this->assertNotContains('id', $properties);
        $this->assertNotContains('applicant_id', $properties);
    }

    public function test_admin_login_does_not_let_the_applicant_page_edit_any_education(): void
    {
        $this->giveEducation($this->other, ['school_name' => 'Other School']);
        $before = $this->eduRow($this->other);

        try {
            $this->fill(Livewire::actingAs($this->admin)->test(EducationEmployment::class))->call('save');
        } catch (\Throwable $e) {
            // refusing is fine
        }

        $this->assertEquals($before, $this->eduRow($this->other));
        $this->assertDatabaseCount('education', 1);
    }

    public function test_viewing_the_page_never_writes_to_the_database(): void
    {
        $this->giveEducation($this->applicant);
        $before = $this->eduRow();

        $this->page();

        $this->assertEquals($before, $this->eduRow());
        $this->assertDatabaseCount('education', 1);
    }
}
