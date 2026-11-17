<?php

namespace Tests\Feature;

use App\Livewire\Applicant\Credentials;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Applicant "Experience & Credentials" page: work experience, trainings,
 * seminars and certifications on the applicant's own record.
 *
 * WHY: these lists are what PESO staff read when matching an applicant to a job.
 * An applicant must only ever read or change THEIR OWN rows, must not exceed the
 * per-list limits, and must not save impossible years or an expiry before the
 * issue date.
 *
 * NOTE ON ASSUMPTIONS (change these to match your app)
 * ────────────────────────────────────────────────────
 *  - Route names: 'applicant.credentials' (this page) and 'applicant.login' (guest redirect).
 *  - Applicants log in through the 'applicant' guard. Admin and staff use the 'web' guard.
 *  - Applicant has the relations workExperiences(), trainings(), seminars() and
 *    certifications(), and their models allow mass assignment of the fields the
 *    component saves (the component itself calls ->create()).
 *  - The view shows session('status') like the other applicant pages.
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
 * guard behaviour that comes from the framework rather than from this component.
 *
 *   APP-CRED-1  no guard check in the component          (2 tests)
 *   APP-CRED-2  remove says "removed" when nothing was   (4 tests)
 */
class ApplicantCredentialsTest extends TestCase
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
        return Livewire::actingAs($as ?? $this->applicant, 'applicant')->test(Credentials::class);
    }

    private function fillWork($c, array $over = [])
    {
        $v = array_merge([
            'company_name' => 'Virac Steel Works',
            'position'     => 'Welder',
            'duration'     => '2 years',
            'description'  => 'Fabricated steel frames.',
        ], $over);

        foreach ($v as $k => $val) {
            $c->set("work.$k", $val);
        }

        return $c;
    }

    private function fillTraining($c, array $over = [])
    {
        $v = array_merge(['title' => 'NC II Welding', 'provider' => 'TESDA', 'year' => '2019'], $over);

        foreach ($v as $k => $val) {
            $c->set("training.$k", $val);
        }

        return $c;
    }

    private function fillSeminar($c, array $over = [])
    {
        $v = array_merge(['title' => 'Job Search Skills', 'organizer' => 'PESO', 'year' => '2021'], $over);

        foreach ($v as $k => $val) {
            $c->set("seminar.$k", $val);
        }

        return $c;
    }

    private function fillCert($c, array $over = [])
    {
        $v = array_merge([
            'name'         => 'Welding Certificate',
            'issuing_body' => 'TESDA',
            'year_issued'  => '2019',
            'expiry_year'  => '2029',
        ], $over);

        foreach ($v as $k => $val) {
            $c->set("cert.$k", $val);
        }

        return $c;
    }

    /** Create $n rows on one of the applicant's lists. */
    private function seedApplicantCredentials(string $relation, int $n, ?Applicant $for = null): void
    {
        $owner = $for ?? $this->applicant;

        foreach (range(1, $n) as $i) {
            $owner->{$relation}()->create(match ($relation) {
                'workExperiences' => ['company_name' => "Company $i", 'position' => 'Worker'],
                'trainings'       => ['title' => "Training $i"],
                'seminars'        => ['title' => "Seminar $i"],
                'certifications'  => ['name' => "Cert $i"],
            });
        }
    }

    private function rows(string $relation, ?Applicant $a = null): int
    {
        return ($a ?? $this->applicant)->{$relation}()->count();
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
        $this->actingAs($this->applicant, 'applicant')->get(route('applicant.credentials'))->assertOk();
    }

    public function test_guest_is_redirected_to_applicant_login(): void
    {
        $this->get(route('applicant.credentials'))->assertRedirect(route('applicant.login'));
    }

    public function test_admin_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('applicant.credentials'))
            ->assertRedirect(route('applicant.login'));
    }

    public function test_staff_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->staff)
            ->get(route('applicant.credentials'))
            ->assertRedirect(route('applicant.login'));
    }

    /**
     * KNOWN GAP — expected to FAIL until the component checks the guard (APP-CRED-1).
     *
     * The route blocks guests, so this only matters if the component is reached
     * another way. Today the component has no mount() at all and render() crashes
     * on a null user.
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
            $this->refusedWith403(fn () => (new Credentials())->mount()),
            'Guest mount() should be refused with 403.'
        );
    }

    /** KNOWN GAP — expected to FAIL until mount() checks the guard (APP-CRED-1). */
    public function test_admin_on_web_guard_cannot_mount_component(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new Credentials())->mount()),
            'Admin (web guard) mount() should be refused with 403.'
        );
    }

    /** Passes whether the app rejects the call or crashes; either way nothing may be written. */
    public function test_add_without_applicant_login_writes_nothing(): void
    {
        try {
            $component = Livewire::actingAs($this->admin)->test(Credentials::class);
            $this->fillWork($component)->call('addWork');
            $this->fillTraining($component)->call('addTraining');
        } catch (\Throwable $e) {
            // refusing is fine
        }

        foreach ([$this->applicant, $this->other] as $a) {
            foreach (['workExperiences', 'trainings', 'seminars', 'certifications'] as $rel) {
                $this->assertSame(0, $this->rows($rel, $a));
            }
        }
    }

    // ── LIST ─────────────────────────────────────────────────────────

    public function test_new_applicant_has_four_empty_lists(): void
    {
        $component = $this->page();

        foreach (['works', 'trainings', 'seminars', 'certs'] as $key) {
            $this->assertCount(0, $component->viewData($key));
        }
    }

    public function test_lists_show_only_the_logged_in_applicants_rows(): void
    {
        $this->seedApplicantCredentials('workExperiences', 2);
        $this->seedApplicantCredentials('trainings', 1);
        $this->seedApplicantCredentials('seminars', 3);
        $this->seedApplicantCredentials('certifications', 1);

        $this->seedApplicantCredentials('workExperiences', 4, $this->other);
        $this->seedApplicantCredentials('trainings', 4, $this->other);
        $this->seedApplicantCredentials('seminars', 4, $this->other);
        $this->seedApplicantCredentials('certifications', 4, $this->other);

        $component = $this->page();

        $this->assertCount(2, $component->viewData('works'));
        $this->assertCount(1, $component->viewData('trainings'));
        $this->assertCount(3, $component->viewData('seminars'));
        $this->assertCount(1, $component->viewData('certs'));
    }

    public function test_work_experiences_are_listed_newest_first(): void
    {
        $old = $this->applicant->workExperiences()->create(['company_name' => 'Old Co', 'position' => 'A']);
        $new = $this->applicant->workExperiences()->create(['company_name' => 'New Co', 'position' => 'B']);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $new->forceFill(['created_at' => now()->subDay()])->save();

        $names = $this->page()->viewData('works')->pluck('company_name')->all();

        $this->assertSame(['New Co', 'Old Co'], $names);
    }

    public function test_trainings_are_listed_by_year_newest_first(): void
    {
        $this->applicant->trainings()->create(['title' => 'T2015', 'year' => 2015]);
        $this->applicant->trainings()->create(['title' => 'T2020', 'year' => 2020]);
        $this->applicant->trainings()->create(['title' => 'T2018', 'year' => 2018]);

        $this->assertSame(['T2020', 'T2018', 'T2015'], $this->page()->viewData('trainings')->pluck('title')->all());
    }

    public function test_seminars_are_listed_by_year_newest_first(): void
    {
        $this->applicant->seminars()->create(['title' => 'S2015', 'year' => 2015]);
        $this->applicant->seminars()->create(['title' => 'S2022', 'year' => 2022]);

        $this->assertSame(['S2022', 'S2015'], $this->page()->viewData('seminars')->pluck('title')->all());
    }

    public function test_certifications_are_listed_by_year_issued_newest_first(): void
    {
        $this->applicant->certifications()->create(['name' => 'C2010', 'year_issued' => 2010]);
        $this->applicant->certifications()->create(['name' => 'C2021', 'year_issued' => 2021]);

        $this->assertSame(['C2021', 'C2010'], $this->page()->viewData('certs')->pluck('name')->all());
    }

    // ── WORK EXPERIENCE: add ─────────────────────────────────────────

    public function test_add_work_saves_the_row_for_the_logged_in_applicant(): void
    {
        $this->fillWork($this->page())->call('addWork')->assertHasNoErrors();

        $row = $this->applicant->workExperiences()->first();
        $this->assertSame('Virac Steel Works', $row->company_name);
        $this->assertSame('Welder', $row->position);
        $this->assertSame('2 years', $row->duration);
        $this->assertSame('Fabricated steel frames.', $row->description);
        $this->assertSame(0, $this->rows('workExperiences', $this->other));
    }

    public function test_add_work_shows_success_message(): void
    {
        $this->fillWork($this->page())->call('addWork')->assertSee('Work experience added.');
    }

    public function test_add_work_clears_the_work_form_only(): void
    {
        $this->fillWork($this->page());
        $component = $this->fillTraining($this->page());

        $component = $this->fillWork($component)->call('addWork');

        $component
            ->assertSet('work', ['company_name' => '', 'position' => '', 'duration' => '', 'description' => ''])
            ->assertSet('training.title', 'NC II Welding');
    }

    public function test_work_company_and_position_are_required(): void
    {
        $component = $this->fillWork($this->page(), ['company_name' => '', 'position' => ''])
            ->call('addWork')
            ->assertHasErrors(['work.company_name' => 'required', 'work.position' => 'required']);

        $this->assertSame('Please enter the company name.', $component->errors()->first('work.company_name'));
        $this->assertSame('Please enter your position.', $component->errors()->first('work.position'));
        $this->assertSame(0, $this->rows('workExperiences'));
    }

    public function test_work_whitespace_only_company_is_rejected(): void
    {
        $this->fillWork($this->page(), ['company_name' => '     '])
            ->call('addWork')
            ->assertHasErrors(['work.company_name' => 'required']);
    }

    public function test_work_duration_and_description_are_optional_and_stored_as_null(): void
    {
        $this->fillWork($this->page(), ['duration' => '', 'description' => ''])->call('addWork')->assertHasNoErrors();

        $row = $this->applicant->workExperiences()->first();
        $this->assertNull($row->duration);
        $this->assertNull($row->description);
    }

    public function test_work_field_length_boundaries(): void
    {
        $this->fillWork($this->page(), [
            'company_name' => str_repeat('a', 255),
            'position'     => str_repeat('b', 255),
            'duration'     => str_repeat('c', 50),
            'description'  => str_repeat('d', 1000),
        ])->call('addWork')->assertHasNoErrors();

        $this->assertSame(1, $this->rows('workExperiences'));
    }

    public function test_work_fields_over_the_limit_are_rejected(): void
    {
        $this->fillWork($this->page(), [
            'company_name' => str_repeat('a', 256),
            'position'     => str_repeat('b', 256),
            'duration'     => str_repeat('c', 51),
            'description'  => str_repeat('d', 1001),
        ])->call('addWork')->assertHasErrors([
            'work.company_name' => 'max',
            'work.position'     => 'max',
            'work.duration'     => 'max',
            'work.description'  => 'max',
        ]);

        $this->assertSame(0, $this->rows('workExperiences'));
    }

    public function test_failed_add_work_keeps_the_typed_values_and_shows_no_success(): void
    {
        $this->fillWork($this->page(), ['position' => ''])
            ->call('addWork')
            ->assertSet('work.company_name', 'Virac Steel Works')
            ->assertDontSee('Work experience added.');
    }

    public function test_work_text_with_html_is_not_shown_as_markup(): void
    {
        $this->fillWork($this->page(), ['company_name' => '<script>alert(1)</script>'])->call('addWork');

        $this->page()->assertDontSee('<script>alert(1)</script>', false);
    }

    // ── WORK EXPERIENCE: limit ───────────────────────────────────────

    public function test_max_constants_are_as_documented(): void
    {
        $this->assertSame(10, Credentials::MAX_WORK);
        $this->assertSame(15, Credentials::MAX_TRAININGS);
        $this->assertSame(15, Credentials::MAX_SEMINARS);
        $this->assertSame(15, Credentials::MAX_CERTS);
    }

    public function test_last_allowed_work_experience_is_accepted(): void
    {
        $this->seedApplicantCredentials('workExperiences', Credentials::MAX_WORK - 1);

        $this->fillWork($this->page())->call('addWork')->assertHasNoErrors();

        $this->assertSame(Credentials::MAX_WORK, $this->rows('workExperiences'));
    }

    public function test_work_experience_beyond_the_limit_is_refused(): void
    {
        $this->seedApplicantCredentials('workExperiences', Credentials::MAX_WORK);

        $component = $this->fillWork($this->page(), ['company_name' => 'Extra'])
            ->call('addWork')
            ->assertHasErrors(['work.company_name'])
            ->assertSet('work.company_name', 'Extra')
            ->assertDontSee('Work experience added.');

        $this->assertSame('You can add up to 10 work experiences.', $component->errors()->first('work.company_name'));
        $this->assertSame(Credentials::MAX_WORK, $this->rows('workExperiences'));
    }

    public function test_work_limit_is_per_applicant(): void
    {
        $this->seedApplicantCredentials('workExperiences', Credentials::MAX_WORK, $this->other);

        $this->fillWork($this->page())->call('addWork')->assertHasNoErrors();

        $this->assertSame(1, $this->rows('workExperiences'));
    }

    public function test_removing_a_work_experience_frees_a_slot_at_the_limit(): void
    {
        $this->seedApplicantCredentials('workExperiences', Credentials::MAX_WORK);
        $first = $this->applicant->workExperiences()->first();

        $component = $this->page();
        $component->call('removeWork', $first->id);

        $this->fillWork($component)->call('addWork')->assertHasNoErrors();

        $this->assertSame(Credentials::MAX_WORK, $this->rows('workExperiences'));
    }

    // ── WORK EXPERIENCE: remove ──────────────────────────────────────

    public function test_remove_work_deletes_only_that_row(): void
    {
        $this->seedApplicantCredentials('workExperiences', 2);
        [$gone, $kept] = $this->applicant->workExperiences()->orderBy('id')->get()->all();

        $this->page()->call('removeWork', $gone->id)->assertSee('Work experience removed.');

        $this->assertNull($this->applicant->workExperiences()->whereKey($gone->id)->first());
        $this->assertNotNull($this->applicant->workExperiences()->whereKey($kept->id)->first());
    }

    public function test_cannot_remove_another_applicants_work_experience(): void
    {
        $this->seedApplicantCredentials('workExperiences', 1, $this->other);
        $theirs = $this->other->workExperiences()->first();

        $this->page()->call('removeWork', $theirs->id);

        $this->assertSame(1, $this->rows('workExperiences', $this->other));
    }

    public function test_remove_work_with_an_unknown_id_does_not_crash(): void
    {
        $this->page()->call('removeWork', 99999)->assertOk();
    }

    /**
     * KNOWN BUG — expected to FAIL (APP-CRED-2).
     *
     * removeWork() always flashes "Work experience removed." even when nothing was
     * deleted (stale page, double click, or a forged id). The same applies to
     * removeTraining(), removeSeminar() and removeCert().
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Open the page in two tabs with the same work experience listed.
     *   2. Remove it in tab 1, then in tab 2.
     *   3. BUG: tab 2 also says "Work experience removed." Expected: no success message.
     *
     * FIX: use the return value of ->delete() and flash only when it is greater than 0:
     *   $deleted = $this->applicant()->workExperiences()->whereKey($id)->delete();
     *   if ($deleted > 0) { session()->flash('status', 'Work experience removed.'); }
     */
    public function test_remove_work_that_does_not_exist_does_not_claim_success(): void
    {
        $this->page()->call('removeWork', 99999)->assertDontSee('Work experience removed.');
    }

    // ── TRAININGS ────────────────────────────────────────────────────

    public function test_add_training_saves_the_row_for_the_logged_in_applicant(): void
    {
        $this->fillTraining($this->page())->call('addTraining')->assertHasNoErrors()->assertSee('Training added.');

        $row = $this->applicant->trainings()->first();
        $this->assertSame('NC II Welding', $row->title);
        $this->assertSame('TESDA', $row->provider);
        $this->assertEquals(2019, $row->year);
        $this->assertSame(0, $this->rows('trainings', $this->other));
    }

    public function test_add_training_clears_the_training_form(): void
    {
        $this->fillTraining($this->page())
            ->call('addTraining')
            ->assertSet('training', ['title' => '', 'provider' => '', 'year' => '']);
    }

    public function test_training_title_is_required(): void
    {
        $component = $this->fillTraining($this->page(), ['title' => ''])
            ->call('addTraining')
            ->assertHasErrors(['training.title' => 'required']);

        $this->assertSame('Please enter the training title.', $component->errors()->first('training.title'));
        $this->assertSame(0, $this->rows('trainings'));
    }

    public function test_training_provider_and_year_are_optional_and_stored_as_null(): void
    {
        $this->fillTraining($this->page(), ['provider' => '', 'year' => ''])->call('addTraining')->assertHasNoErrors();

        $row = $this->applicant->trainings()->first();
        $this->assertNull($row->provider);
        $this->assertNull($row->year);
    }

    public function test_training_text_length_boundaries(): void
    {
        $this->fillTraining($this->page(), ['title' => str_repeat('a', 255), 'provider' => str_repeat('b', 255)])
            ->call('addTraining')
            ->assertHasNoErrors();

        $this->fillTraining($this->page(), ['title' => str_repeat('a', 256), 'provider' => str_repeat('b', 256)])
            ->call('addTraining')
            ->assertHasErrors(['training.title' => 'max', 'training.provider' => 'max']);

        $this->assertSame(1, $this->rows('trainings'));
    }

    public function test_training_year_boundaries(): void
    {
        foreach (['1950', (string) now()->year] as $ok) {
            $this->fillTraining($this->page(), ['year' => $ok])->call('addTraining')->assertHasNoErrors();
        }

        foreach (['1949', (string) (now()->year + 1)] as $bad) {
            $this->fillTraining($this->page(), ['year' => $bad])->call('addTraining')->assertHasErrors(['training.year' => 'between']);
        }

        $this->assertSame(2, $this->rows('trainings'));
    }

    public function test_training_year_rejects_letters_decimals_and_negatives(): void
    {
        foreach (['abc', '2019.5', '-2019', '20x9', '0'] as $bad) {
            $this->fillTraining($this->page(), ['year' => $bad])->call('addTraining')->assertHasErrors(['training.year']);
        }

        $this->assertSame(0, $this->rows('trainings'));
    }

    public function test_training_limit(): void
    {
        $this->seedApplicantCredentials('trainings', Credentials::MAX_TRAININGS - 1);
        $this->fillTraining($this->page())->call('addTraining')->assertHasNoErrors();
        $this->assertSame(Credentials::MAX_TRAININGS, $this->rows('trainings'));

        $component = $this->fillTraining($this->page(), ['title' => 'Extra'])
            ->call('addTraining')
            ->assertHasErrors(['training.title'])
            ->assertDontSee('Training added.');

        $this->assertSame('You can add up to 15 trainings.', $component->errors()->first('training.title'));
        $this->assertSame(Credentials::MAX_TRAININGS, $this->rows('trainings'));
    }

    public function test_training_limit_is_per_applicant(): void
    {
        $this->seedApplicantCredentials('trainings', Credentials::MAX_TRAININGS, $this->other);

        $this->fillTraining($this->page())->call('addTraining')->assertHasNoErrors();
    }

    public function test_remove_training_deletes_only_that_row_and_only_own(): void
    {
        $this->seedApplicantCredentials('trainings', 2);
        $this->seedApplicantCredentials('trainings', 1, $this->other);
        [$gone, $kept] = $this->applicant->trainings()->orderBy('id')->get()->all();
        $theirs = $this->other->trainings()->first();

        $component = $this->page();
        $component->call('removeTraining', $gone->id)->assertSee('Training removed.');
        $component->call('removeTraining', $theirs->id);

        $this->assertNull($this->applicant->trainings()->whereKey($gone->id)->first());
        $this->assertNotNull($this->applicant->trainings()->whereKey($kept->id)->first());
        $this->assertSame(1, $this->rows('trainings', $this->other));
    }

    /** KNOWN BUG — expected to FAIL (APP-CRED-2). Same cause and fix as removeWork(). */
    public function test_remove_training_that_does_not_exist_does_not_claim_success(): void
    {
        $this->page()->call('removeTraining', 99999)->assertDontSee('Training removed.');
    }

    // ── SEMINARS ─────────────────────────────────────────────────────

    public function test_add_seminar_saves_the_row_for_the_logged_in_applicant(): void
    {
        $this->fillSeminar($this->page())->call('addSeminar')->assertHasNoErrors()->assertSee('Seminar added.');

        $row = $this->applicant->seminars()->first();
        $this->assertSame('Job Search Skills', $row->title);
        $this->assertSame('PESO', $row->organizer);
        $this->assertEquals(2021, $row->year);
        $this->assertSame(0, $this->rows('seminars', $this->other));
    }

    public function test_add_seminar_clears_the_seminar_form(): void
    {
        $this->fillSeminar($this->page())
            ->call('addSeminar')
            ->assertSet('seminar', ['title' => '', 'organizer' => '', 'year' => '']);
    }

    public function test_seminar_title_is_required(): void
    {
        $component = $this->fillSeminar($this->page(), ['title' => ''])
            ->call('addSeminar')
            ->assertHasErrors(['seminar.title' => 'required']);

        $this->assertSame('Please enter the seminar title.', $component->errors()->first('seminar.title'));
        $this->assertSame(0, $this->rows('seminars'));
    }

    public function test_seminar_organizer_and_year_are_optional_and_stored_as_null(): void
    {
        $this->fillSeminar($this->page(), ['organizer' => '', 'year' => ''])->call('addSeminar')->assertHasNoErrors();

        $row = $this->applicant->seminars()->first();
        $this->assertNull($row->organizer);
        $this->assertNull($row->year);
    }

    public function test_seminar_text_length_boundaries(): void
    {
        $this->fillSeminar($this->page(), ['title' => str_repeat('a', 255), 'organizer' => str_repeat('b', 255)])
            ->call('addSeminar')
            ->assertHasNoErrors();

        $this->fillSeminar($this->page(), ['title' => str_repeat('a', 256), 'organizer' => str_repeat('b', 256)])
            ->call('addSeminar')
            ->assertHasErrors(['seminar.title' => 'max', 'seminar.organizer' => 'max']);

        $this->assertSame(1, $this->rows('seminars'));
    }

    public function test_seminar_year_boundaries(): void
    {
        foreach (['1950', (string) now()->year] as $ok) {
            $this->fillSeminar($this->page(), ['year' => $ok])->call('addSeminar')->assertHasNoErrors();
        }

        foreach (['1949', (string) (now()->year + 1), 'abc', '2019.5'] as $bad) {
            $this->fillSeminar($this->page(), ['year' => $bad])->call('addSeminar')->assertHasErrors(['seminar.year']);
        }

        $this->assertSame(2, $this->rows('seminars'));
    }

    public function test_seminar_limit(): void
    {
        $this->seedApplicantCredentials('seminars', Credentials::MAX_SEMINARS - 1);
        $this->fillSeminar($this->page())->call('addSeminar')->assertHasNoErrors();
        $this->assertSame(Credentials::MAX_SEMINARS, $this->rows('seminars'));

        $component = $this->fillSeminar($this->page(), ['title' => 'Extra'])
            ->call('addSeminar')
            ->assertHasErrors(['seminar.title'])
            ->assertDontSee('Seminar added.');

        $this->assertSame('You can add up to 15 seminars.', $component->errors()->first('seminar.title'));
        $this->assertSame(Credentials::MAX_SEMINARS, $this->rows('seminars'));
    }

    public function test_seminar_limit_is_per_applicant(): void
    {
        $this->seedApplicantCredentials('seminars', Credentials::MAX_SEMINARS, $this->other);

        $this->fillSeminar($this->page())->call('addSeminar')->assertHasNoErrors();
    }

    public function test_remove_seminar_deletes_only_that_row_and_only_own(): void
    {
        $this->seedApplicantCredentials('seminars', 2);
        $this->seedApplicantCredentials('seminars', 1, $this->other);
        [$gone, $kept] = $this->applicant->seminars()->orderBy('id')->get()->all();
        $theirs = $this->other->seminars()->first();

        $component = $this->page();
        $component->call('removeSeminar', $gone->id)->assertSee('Seminar removed.');
        $component->call('removeSeminar', $theirs->id);

        $this->assertNull($this->applicant->seminars()->whereKey($gone->id)->first());
        $this->assertNotNull($this->applicant->seminars()->whereKey($kept->id)->first());
        $this->assertSame(1, $this->rows('seminars', $this->other));
    }

    /** KNOWN BUG — expected to FAIL (APP-CRED-2). Same cause and fix as removeWork(). */
    public function test_remove_seminar_that_does_not_exist_does_not_claim_success(): void
    {
        $this->page()->call('removeSeminar', 99999)->assertDontSee('Seminar removed.');
    }

    // ── CERTIFICATIONS ───────────────────────────────────────────────

    public function test_add_cert_saves_the_row_for_the_logged_in_applicant(): void
    {
        $this->fillCert($this->page())->call('addCert')->assertHasNoErrors()->assertSee('Certification added.');

        $row = $this->applicant->certifications()->first();
        $this->assertSame('Welding Certificate', $row->name);
        $this->assertSame('TESDA', $row->issuing_body);
        $this->assertEquals(2019, $row->year_issued);
        $this->assertEquals(2029, $row->expiry_year);
        $this->assertSame(0, $this->rows('certifications', $this->other));
    }

    public function test_add_cert_clears_the_cert_form(): void
    {
        $this->fillCert($this->page())
            ->call('addCert')
            ->assertSet('cert', ['name' => '', 'issuing_body' => '', 'year_issued' => '', 'expiry_year' => '']);
    }

    public function test_cert_name_is_required(): void
    {
        $component = $this->fillCert($this->page(), ['name' => ''])
            ->call('addCert')
            ->assertHasErrors(['cert.name' => 'required']);

        $this->assertSame('Please enter the certification name.', $component->errors()->first('cert.name'));
        $this->assertSame(0, $this->rows('certifications'));
    }

    public function test_cert_optional_fields_are_stored_as_null(): void
    {
        $this->fillCert($this->page(), ['issuing_body' => '', 'year_issued' => '', 'expiry_year' => ''])
            ->call('addCert')
            ->assertHasNoErrors();

        $row = $this->applicant->certifications()->first();
        $this->assertNull($row->issuing_body);
        $this->assertNull($row->year_issued);
        $this->assertNull($row->expiry_year);
    }

    public function test_cert_text_length_boundaries(): void
    {
        $this->fillCert($this->page(), ['name' => str_repeat('a', 255), 'issuing_body' => str_repeat('b', 255), 'year_issued' => '', 'expiry_year' => ''])
            ->call('addCert')
            ->assertHasNoErrors();

        $this->fillCert($this->page(), ['name' => str_repeat('a', 256), 'issuing_body' => str_repeat('b', 256), 'year_issued' => '', 'expiry_year' => ''])
            ->call('addCert')
            ->assertHasErrors(['cert.name' => 'max', 'cert.issuing_body' => 'max']);

        $this->assertSame(1, $this->rows('certifications'));
    }

    public function test_cert_year_issued_boundaries(): void
    {
        foreach (['1950', (string) now()->year] as $ok) {
            $this->fillCert($this->page(), ['year_issued' => $ok, 'expiry_year' => ''])->call('addCert')->assertHasNoErrors();
        }

        foreach (['1949', (string) (now()->year + 1), 'abc', '2019.5'] as $bad) {
            $this->fillCert($this->page(), ['year_issued' => $bad, 'expiry_year' => ''])->call('addCert')->assertHasErrors(['cert.year_issued']);
        }

        $this->assertSame(2, $this->rows('certifications'));
    }

    public function test_cert_expiry_year_boundaries(): void
    {
        foreach (['1950', '2100'] as $ok) {
            $this->fillCert($this->page(), ['year_issued' => '', 'expiry_year' => $ok])->call('addCert')->assertHasNoErrors();
        }

        foreach (['1949', '2101', 'abc', '2030.5'] as $bad) {
            $this->fillCert($this->page(), ['year_issued' => '', 'expiry_year' => $bad])->call('addCert')->assertHasErrors(['cert.expiry_year']);
        }

        $this->assertSame(2, $this->rows('certifications'));
    }

    public function test_cert_expiry_in_the_future_is_allowed(): void
    {
        $this->fillCert($this->page(), ['year_issued' => (string) now()->year, 'expiry_year' => '2100'])
            ->call('addCert')
            ->assertHasNoErrors();
    }

    public function test_cert_expiry_before_issue_year_is_rejected(): void
    {
        $component = $this->fillCert($this->page(), ['year_issued' => '2020', 'expiry_year' => '2019'])
            ->call('addCert')
            ->assertHasErrors(['cert.expiry_year']);

        $this->assertSame('Expiry year cannot be earlier than the year issued.', $component->errors()->first('cert.expiry_year'));
        $this->assertSame(0, $this->rows('certifications'));
    }

    public function test_cert_expiry_in_the_same_year_as_issue_is_allowed(): void
    {
        $this->fillCert($this->page(), ['year_issued' => '2020', 'expiry_year' => '2020'])
            ->call('addCert')
            ->assertHasNoErrors();
    }

    public function test_cert_expiry_without_issue_year_is_allowed(): void
    {
        $this->fillCert($this->page(), ['year_issued' => '', 'expiry_year' => '2025'])->call('addCert')->assertHasNoErrors();
    }

    public function test_cert_issue_year_without_expiry_is_allowed(): void
    {
        $this->fillCert($this->page(), ['year_issued' => '2020', 'expiry_year' => ''])->call('addCert')->assertHasNoErrors();
    }

    public function test_failed_cert_check_keeps_the_typed_values(): void
    {
        $this->fillCert($this->page(), ['year_issued' => '2020', 'expiry_year' => '2019'])
            ->call('addCert')
            ->assertSet('cert.name', 'Welding Certificate')
            ->assertDontSee('Certification added.');
    }

    public function test_cert_limit(): void
    {
        $this->seedApplicantCredentials('certifications', Credentials::MAX_CERTS - 1);
        $this->fillCert($this->page())->call('addCert')->assertHasNoErrors();
        $this->assertSame(Credentials::MAX_CERTS, $this->rows('certifications'));

        $component = $this->fillCert($this->page(), ['name' => 'Extra'])
            ->call('addCert')
            ->assertHasErrors(['cert.name'])
            ->assertDontSee('Certification added.');

        $this->assertSame('You can add up to 15 certifications.', $component->errors()->first('cert.name'));
        $this->assertSame(Credentials::MAX_CERTS, $this->rows('certifications'));
    }

    public function test_cert_limit_is_per_applicant(): void
    {
        $this->seedApplicantCredentials('certifications', Credentials::MAX_CERTS, $this->other);

        $this->fillCert($this->page())->call('addCert')->assertHasNoErrors();
    }

    public function test_remove_cert_deletes_only_that_row_and_only_own(): void
    {
        $this->seedApplicantCredentials('certifications', 2);
        $this->seedApplicantCredentials('certifications', 1, $this->other);
        [$gone, $kept] = $this->applicant->certifications()->orderBy('id')->get()->all();
        $theirs = $this->other->certifications()->first();

        $component = $this->page();
        $component->call('removeCert', $gone->id)->assertSee('Certification removed.');
        $component->call('removeCert', $theirs->id);

        $this->assertNull($this->applicant->certifications()->whereKey($gone->id)->first());
        $this->assertNotNull($this->applicant->certifications()->whereKey($kept->id)->first());
        $this->assertSame(1, $this->rows('certifications', $this->other));
    }

    /** KNOWN BUG — expected to FAIL (APP-CRED-2). Same cause and fix as removeWork(). */
    public function test_remove_cert_that_does_not_exist_does_not_claim_success(): void
    {
        $this->page()->call('removeCert', 99999)->assertDontSee('Certification removed.');
    }

    // ── EDGE CASES ACROSS SECTIONS ───────────────────────────────────

    public function test_an_id_from_one_list_cannot_delete_a_row_in_another_list(): void
    {
        $work     = $this->applicant->workExperiences()->create(['company_name' => 'Keep Me', 'position' => 'A']);
        $training = $this->applicant->trainings()->create(['title' => 'Keep Me Too']);
        $seminar  = $this->applicant->seminars()->create(['title' => 'Keep Me Three']);
        $cert     = $this->applicant->certifications()->create(['name' => 'Keep Me Four']);

        $component = $this->page();
        $component->call('removeTraining', $work->id);
        $component->call('removeSeminar', $work->id);
        $component->call('removeCert', $work->id);
        $component->call('removeWork', $cert->id + 1000);

        $this->assertSame(1, $this->rows('workExperiences'));
        $this->assertNotNull($this->applicant->trainings()->whereKey($training->id)->first());
        $this->assertNotNull($this->applicant->seminars()->whereKey($seminar->id)->first());
        $this->assertNotNull($this->applicant->certifications()->whereKey($cert->id)->first());
    }

    public function test_a_failed_add_in_one_section_does_not_affect_the_others(): void
    {
        $component = $this->fillWork($this->page(), ['position' => '']);
        $this->fillTraining($component);

        $component->call('addWork')->assertHasErrors(['work.position']);
        $component->call('addTraining')->assertHasNoErrors();

        $this->assertSame(0, $this->rows('workExperiences'));
        $this->assertSame(1, $this->rows('trainings'));
    }

    public function test_extra_keys_in_the_form_cannot_assign_a_row_to_another_applicant(): void
    {
        // A tampered request can add keys the form never sends.
        try {
            $this->fillWork($this->page())
                ->set('work.applicant_id', $this->other->id)
                ->call('addWork');
        } catch (\Throwable $e) {
            // refusing is fine
        }

        $this->assertSame(0, $this->rows('workExperiences', $this->other));
    }

    public function test_each_applicant_adds_to_their_own_lists_only(): void
    {
        $this->fillWork($this->page($this->applicant), ['company_name' => 'Company A'])->call('addWork');
        $this->fillWork($this->page($this->other), ['company_name' => 'Company B'])->call('addWork');

        $this->assertSame(['Company A'], $this->applicant->workExperiences()->pluck('company_name')->all());
        $this->assertSame(['Company B'], $this->other->workExperiences()->pluck('company_name')->all());
    }

    public function test_viewing_the_page_never_writes_to_the_database(): void
    {
        $this->seedApplicantCredentials('workExperiences', 1);

        $this->page();

        $this->assertSame(1, $this->rows('workExperiences'));
        $this->assertSame(0, $this->rows('trainings'));
    }
}