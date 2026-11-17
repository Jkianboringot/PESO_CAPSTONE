<?php

namespace Tests\Feature;

use App\Exports\ApplicantsExport;
use App\Exports\PesoMonthlyExport;
use App\Livewire\ReportGenerator;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Education;
use App\Models\Municipality;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Report generator: filters, validation, downloads, audit, monthly figures.
 *
 * WHY: reports are submitted to DOLE. Wrong filters, the wrong applicants, or a
 * wrong monthly count is a compliance failure, and every export of personal data
 * must be audited under RA 10173.
 *
 * NOTE ON PERMISSIONS
 * ───────────────────
 * Admin and staff may use reports. generate() checks the role. render() does not
 * (RPT-3). Staff and admin are allowed, so the permission tests here are for the
 * outsider (logged in, no role) and the guest. Outsider tests call methods
 * directly and check for a 403 HttpException, then check the audit log.
 *
 * NOTE ON PRIVATE METHODS
 * ───────────────────────
 * The monthly and listing figures are built in private methods. They are tested
 * through callPrivate() (reflection) so the numbers can be checked without
 * rendering a PDF or reading an Excel file.
 *
 * NOTE ON KNOWN BUGS AND GAPS
 * ───────────────────────────
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions.
 *
 * NOT COVERED: the CSV formula guard (RPT-2). It lives in ApplicantsExport,
 * which was not provided. Add a test there once the file is reviewed.
 */
class ReportGeneratorTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private User $outsider;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $this->outsider = User::factory()->create();

        $municipality   = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay = Barangay::factory()->create(['municipality_id' => $municipality->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staffPage()
    {
        return Livewire::actingAs($this->staff)->test(ReportGenerator::class);
    }

    private function makeApplicant(array $attrs = []): Applicant
    {
        return Applicant::factory()->create(array_merge([
            'barangay_id' => $this->barangay->id,
            'is_active'   => true,
        ], $attrs));
    }

    private function makeSkill(string $name, ?SkillCategory $category = null): Skill
    {
        $category ??= SkillCategory::create(['name' => 'Cat ' . $name]);

        return Skill::create(['name' => $name, 'skill_category_id' => $category->id]);
    }

    /** An unsaved applicant with relations preset, for the figure builders. */
    private function memApplicant(array $attrs = [], array $skillNames = []): Applicant
    {
        $a = (new Applicant())->forceFill(array_merge([
            'first_name' => 'Ana', 'last_name' => 'Cruz', 'middle_name' => null,
            'sex' => 'Female', 'civil_status' => 'Single', 'status' => 'Pending',
            'birthdate' => null, 'created_at' => Carbon::parse('2026-03-10 09:00:00'),
        ], $attrs));

        $a->setRelation('skills', collect(array_map(fn ($n) => new Skill(['name' => $n]), $skillNames)));
        $a->setRelation('education', null);
        $a->setRelation('barangay', null);

        return $a;
    }

    private function callPrivate(ReportGenerator $component, string $method, array $args = [])
    {
        $m = new \ReflectionMethod($component, $method);
        $m->setAccessible(true);

        return $m->invokeArgs($component, $args);
    }

    private function newComponent(array $props = []): ReportGenerator
    {
        $c = new ReportGenerator();
        foreach ($props as $k => $v) {
            $c->{$k} = $v;
        }

        return $c;
    }

    private function onScreenIds($component): array
    {
        return $component->viewData('applicants')->pluck('id')->all();
    }

    /**
     * Returns true only when the callable throws an HttpException with status 403.
     */
    private function refusedWith403(callable $action): bool
    {
        try {
            $action();
            return false;
        } catch (HttpException $e) {
            return $e->getStatusCode() === 403;
        }
    }

    // ── ACCESS ───────────────────────────────────────────────────────

    public function test_admin_can_open_page(): void
    {
        $this->actingAs($this->admin)->get(route('reports'))->assertOk();
    }

    public function test_staff_can_open_page(): void
    {
        $this->actingAs($this->staff)->get(route('reports'))->assertOk();
    }

    public function test_user_without_role_cannot_open_page(): void
    {
        $this->actingAs($this->outsider)->get(route('reports'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports'))->assertRedirect(route('login'));
    }

    public function test_default_state(): void
    {
        $this->staffPage()
            ->assertSet('format', 'xlsx')
            ->assertSet('reportType', 'listing')
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '')
            ->assertSet('sex', '');
    }

    // ── ON-SCREEN LIST ───────────────────────────────────────────────

    public function test_lists_active_applicants(): void
    {
        $a = $this->makeApplicant();

        $this->assertContains($a->id, $this->onScreenIds($this->staffPage()));
    }

    public function test_excludes_inactive_applicants(): void
    {
        $active   = $this->makeApplicant();
        $inactive = $this->makeApplicant(['is_active' => false]);

        $ids = $this->onScreenIds($this->staffPage());

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_list_is_ordered_by_last_name_then_first_name(): void
    {
        $c  = $this->makeApplicant(['last_name' => 'Cruz', 'first_name' => 'Ana']);
        $a2 = $this->makeApplicant(['last_name' => 'Abad', 'first_name' => 'Zoe']);
        $a1 = $this->makeApplicant(['last_name' => 'Abad', 'first_name' => 'Ben']);

        $this->assertSame([$a1->id, $a2->id, $c->id], $this->onScreenIds($this->staffPage()));
    }

    public function test_filter_by_sex(): void
    {
        $m = $this->makeApplicant(['sex' => 'Male']);
        $f = $this->makeApplicant(['sex' => 'Female']);

        $ids = $this->onScreenIds($this->staffPage()->set('sex', 'Female'));

        $this->assertContains($f->id, $ids);
        $this->assertNotContains($m->id, $ids);
    }

    public function test_filter_by_unknown_sex_returns_nothing_without_error(): void
    {
        $this->makeApplicant(['sex' => 'Male']);

        $this->assertSame([], $this->onScreenIds($this->staffPage()->set('sex', 'Unicorn')));
    }

    public function test_filter_by_barangay(): void
    {
        $other = Barangay::factory()->create(['municipality_id' => $this->barangay->municipality_id]);
        $here  = $this->makeApplicant();
        $there = $this->makeApplicant(['barangay_id' => $other->id]);

        $ids = $this->onScreenIds($this->staffPage()->set('barangayId', (string) $this->barangay->id));

        $this->assertContains($here->id, $ids);
        $this->assertNotContains($there->id, $ids);
    }

    public function test_filter_by_education_level(): void
    {
        $grad = $this->makeApplicant();
        $hs   = $this->makeApplicant();
        Education::factory()->create(['applicant_id' => $grad->id, 'highest_level' => 'College Graduate']);
        Education::factory()->create(['applicant_id' => $hs->id, 'highest_level' => 'High School']);

        $ids = $this->onScreenIds($this->staffPage()->set('educLevel', 'College Graduate'));

        $this->assertContains($grad->id, $ids);
        $this->assertNotContains($hs->id, $ids);
    }

    public function test_filter_by_skill_category(): void
    {
        $welding = SkillCategory::create(['name' => 'Welding']);
        $cooking = SkillCategory::create(['name' => 'Cooking']);

        $welder = $this->makeApplicant();
        $cook   = $this->makeApplicant();
        $welder->skills()->attach($this->makeSkill('Arc Welding', $welding)->id);
        $cook->skills()->attach($this->makeSkill('Baking', $cooking)->id);

        $ids = $this->onScreenIds($this->staffPage()->set('categoryId', (string) $welding->id));

        $this->assertContains($welder->id, $ids);
        $this->assertNotContains($cook->id, $ids);
    }

    public function test_applicant_with_two_skills_in_one_category_is_listed_once(): void
    {
        $welding = SkillCategory::create(['name' => 'Welding']);
        $a = $this->makeApplicant();
        $a->skills()->attach($this->makeSkill('Arc Welding', $welding)->id);
        $a->skills()->attach($this->makeSkill('MIG Welding', $welding)->id);

        $ids = $this->onScreenIds($this->staffPage()->set('categoryId', (string) $welding->id));

        $this->assertSame([$a->id], $ids);
    }

    public function test_filter_by_date_range(): void
    {
        $inside  = $this->makeApplicant(['created_at' => '2026-03-15 10:00:00']);
        $before  = $this->makeApplicant(['created_at' => '2026-02-01 10:00:00']);
        $after   = $this->makeApplicant(['created_at' => '2026-05-01 10:00:00']);

        $ids = $this->onScreenIds(
            $this->staffPage()->set('dateFrom', '2026-03-01')->set('dateTo', '2026-03-31')
        );

        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($before->id, $ids);
        $this->assertNotContains($after->id, $ids);
    }

    /** "To" is a date, not a time, so someone who registered late that day must be included. */
    public function test_date_to_includes_the_whole_last_day(): void
    {
        $late = $this->makeApplicant(['created_at' => '2026-03-31 23:45:00']);

        $ids = $this->onScreenIds(
            $this->staffPage()->set('dateFrom', '2026-03-01')->set('dateTo', '2026-03-31')
        );

        $this->assertContains($late->id, $ids);
    }

    public function test_date_from_includes_the_whole_first_day(): void
    {
        $early = $this->makeApplicant(['created_at' => '2026-03-01 00:00:30']);

        $ids = $this->onScreenIds(
            $this->staffPage()->set('dateFrom', '2026-03-01')->set('dateTo', '2026-03-31')
        );

        $this->assertContains($early->id, $ids);
    }

    public function test_same_day_range_is_allowed_and_returns_that_day(): void
    {
        $day   = $this->makeApplicant(['created_at' => '2026-03-15 12:00:00']);
        $other = $this->makeApplicant(['created_at' => '2026-03-16 12:00:00']);

        $component = $this->staffPage()->set('dateFrom', '2026-03-15')->set('dateTo', '2026-03-15');

        $this->assertSame([$day->id], array_values(array_intersect($this->onScreenIds($component), [$day->id, $other->id])));
    }

    public function test_combined_filters_all_apply(): void
    {
        $match = $this->makeApplicant(['sex' => 'Female', 'created_at' => '2026-03-15 10:00:00']);
        $wrongSex  = $this->makeApplicant(['sex' => 'Male', 'created_at' => '2026-03-15 10:00:00']);
        $wrongDate = $this->makeApplicant(['sex' => 'Female', 'created_at' => '2026-01-15 10:00:00']);

        $ids = $this->onScreenIds(
            $this->staffPage()
                ->set('sex', 'Female')
                ->set('barangayId', (string) $this->barangay->id)
                ->set('dateFrom', '2026-03-01')
                ->set('dateTo', '2026-03-31')
        );

        $this->assertSame([$match->id], $ids);
        $this->assertNotContains($wrongSex->id, $ids);
        $this->assertNotContains($wrongDate->id, $ids);
    }

    public function test_clearing_a_filter_restores_the_full_list(): void
    {
        $this->makeApplicant(['sex' => 'Male']);
        $this->makeApplicant(['sex' => 'Female']);

        $component = $this->staffPage()->set('sex', 'Male');
        $this->assertCount(1, $component->viewData('applicants'));

        $component->set('sex', '');
        $this->assertCount(2, $component->viewData('applicants'));
    }

    // ── PAGINATION ───────────────────────────────────────────────────

    public function test_list_shows_fifteen_per_page_with_remainder_on_next_page(): void
    {
        foreach (range(1, 16) as $i) {
            $this->makeApplicant();
        }

        $component = $this->staffPage();

        $this->assertCount(15, $component->viewData('applicants'));

        $component->call('gotoPage', 2);

        $this->assertCount(1, $component->viewData('applicants'));
    }

    public function test_changing_a_filter_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 16) as $i) {
            $this->makeApplicant(['sex' => 'Male']);
        }

        $this->staffPage()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('sex', 'Male')
            ->assertSet('paginators.page', 1);
    }

    public function test_changing_format_does_not_reset_pagination(): void
    {
        foreach (range(1, 16) as $i) {
            $this->makeApplicant();
        }

        $this->staffPage()
            ->call('gotoPage', 2)
            ->set('format', 'csv')
            ->assertSet('paginators.page', 2);
    }

    public function test_changing_report_type_does_not_reset_pagination(): void
    {
        foreach (range(1, 16) as $i) {
            $this->makeApplicant();
        }

        $this->staffPage()
            ->call('gotoPage', 2)
            ->set('reportType', 'monthly')
            ->assertSet('paginators.page', 2);
    }

    // ── VALIDATION ───────────────────────────────────────────────────

    public function test_date_to_before_date_from_is_rejected(): void
    {
        $this->staffPage()
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-01-01')
            ->call('generate')
            ->assertHasErrors(['dateTo' => 'after_or_equal']);
    }

    public function test_same_day_date_range_passes_validation(): void
    {
        Excel::fake();

        $this->staffPage()
            ->set('dateFrom', '2026-03-15')
            ->set('dateTo', '2026-03-15')
            ->call('generate')
            ->assertHasNoErrors();
    }

    public function test_invalid_date_from_is_rejected(): void
    {
        $this->staffPage()->set('dateFrom', 'not-a-date')->call('generate')->assertHasErrors(['dateFrom' => 'date']);
    }

    public function test_invalid_date_to_is_rejected(): void
    {
        $this->staffPage()->set('dateTo', '2026-13-45')->call('generate')->assertHasErrors(['dateTo' => 'date']);
    }

    public function test_empty_dates_are_allowed(): void
    {
        Excel::fake();

        $this->staffPage()->call('generate')->assertHasNoErrors();
    }

    public function test_pdf_is_an_accepted_format(): void
    {
        $this->staffPage()->set('format', 'pdf')->call('generate')->assertHasNoErrors(['format']);
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->staffPage()->set('format', 'docx')->call('generate')->assertHasErrors(['format' => 'in']);
    }

    public function test_empty_format_is_rejected(): void
    {
        $this->staffPage()->set('format', '')->call('generate')->assertHasErrors(['format' => 'required']);
    }

    public function test_unknown_report_type_is_rejected(): void
    {
        $this->staffPage()->set('reportType', 'everything')->call('generate')->assertHasErrors(['reportType' => 'in']);
    }

    public function test_failed_validation_starts_no_download(): void
    {
        $this->staffPage()
            ->set('format', 'docx')
            ->call('generate')
            ->assertHasErrors(['format'])
            ->assertNoFileDownloaded();
    }

    // ── DOWNLOADS ────────────────────────────────────────────────────

    public function test_listing_xlsx_uses_applicants_export(): void
    {
        Excel::fake();
        Carbon::setTestNow('2026-11-16 10:00:00');

        $this->staffPage()->set('format', 'xlsx')->call('generate');

        Excel::assertDownloaded(
            'PESO_Catanduanes_Workforce_20261116_100000.xlsx',
            fn ($export) => $export instanceof ApplicantsExport
        );
    }

    public function test_listing_csv_uses_applicants_export(): void
    {
        Excel::fake();
        Carbon::setTestNow('2026-11-16 10:00:00');

        $this->staffPage()->set('format', 'csv')->call('generate');

        Excel::assertDownloaded(
            'PESO_Catanduanes_Workforce_20261116_100000.csv',
            fn ($export) => $export instanceof ApplicantsExport
        );
    }

    public function test_listing_pdf_downloads_a_file(): void
    {
        $this->makeApplicant();

        $this->staffPage()->set('format', 'pdf')->call('generate')->assertFileDownloaded();
    }

    public function test_listing_pdf_with_no_matching_applicants_still_downloads(): void
    {
        $this->staffPage()->set('format', 'pdf')->set('sex', 'Unicorn')->call('generate')->assertFileDownloaded();
    }

    public function test_monthly_pdf_downloads_a_file(): void
    {
        $this->makeApplicant();

        $this->staffPage()->set('reportType', 'monthly')->call('generate')->assertFileDownloaded();
    }

    public function test_monthly_xlsx_uses_peso_monthly_export(): void
    {
        Excel::fake();
        Carbon::setTestNow('2026-11-16 10:00:00');

        $this->staffPage()->set('reportType', 'monthly_xlsx')->call('generate');

        Excel::assertDownloaded(
            'PESO_Monthly_Form_20261116_100000.xlsx',
            fn ($export) => $export instanceof PesoMonthlyExport
        );
    }

    /** Export Format is ignored for the monthly options, even if csv is selected. */
    public function test_monthly_xlsx_ignores_the_format_setting(): void
    {
        Excel::fake();
        Carbon::setTestNow('2026-11-16 10:00:00');

        $this->staffPage()->set('reportType', 'monthly_xlsx')->set('format', 'csv')->call('generate');

        Excel::assertDownloaded('PESO_Monthly_Form_20261116_100000.xlsx');
    }

    public function test_monthly_xlsx_with_no_applicants_still_downloads(): void
    {
        Excel::fake();
        Carbon::setTestNow('2026-11-16 10:00:00');

        $this->staffPage()->set('reportType', 'monthly_xlsx')->call('generate')->assertHasNoErrors();

        Excel::assertDownloaded('PESO_Monthly_Form_20261116_100000.xlsx');
    }

    // ── AUDIT ────────────────────────────────────────────────────────

    public function test_generate_writes_one_audit_log_with_the_user(): void
    {
        Excel::fake();

        $this->staffPage()->set('dateFrom', '2026-01-01')->set('dateTo', '2026-06-30')->call('generate');

        $this->assertDatabaseHas('audit_logs', ['action' => 'REPORT_DOWNLOADED', 'user_id' => $this->staff->id]);
        $this->assertSame(1, \DB::table('audit_logs')->where('action', 'REPORT_DOWNLOADED')->count());
    }

    public function test_every_report_type_is_audited(): void
    {
        Excel::fake();

        $page = $this->staffPage();
        $page->set('reportType', 'listing')->call('generate');
        $page->set('reportType', 'monthly_xlsx')->call('generate');
        $page->set('reportType', 'monthly')->call('generate');

        $this->assertSame(3, \DB::table('audit_logs')->where('action', 'REPORT_DOWNLOADED')->count());
    }

    public function test_failed_validation_writes_no_audit_log(): void
    {
        $this->staffPage()->set('format', 'docx')->call('generate');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'REPORT_DOWNLOADED']);
    }

    public function test_date_range_error_writes_no_audit_log(): void
    {
        $this->staffPage()->set('dateFrom', '2026-06-01')->set('dateTo', '2026-01-01')->call('generate');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'REPORT_DOWNLOADED']);
    }

    // ── PERMISSIONS: ADMIN ───────────────────────────────────────────

    public function test_admin_can_generate(): void
    {
        Excel::fake();

        Livewire::actingAs($this->admin)
            ->test(ReportGenerator::class)
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'REPORT_DOWNLOADED', 'user_id' => $this->admin->id]);
    }

    // ── PERMISSIONS: STAFF ───────────────────────────────────────────

    public function test_staff_can_generate_and_is_audited(): void
    {
        Excel::fake();

        $this->staffPage()->call('generate')->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'REPORT_DOWNLOADED', 'user_id' => $this->staff->id]);
    }

    // ── PERMISSIONS: NO ROLE ─────────────────────────────────────────

    public function test_user_without_role_cannot_generate(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new ReportGenerator())->generate(app(AuditLogService::class))),
            'User without a role: generate() should be refused with 403.'
        );
    }

    public function test_user_without_role_generate_leaves_no_audit_trail_and_no_download(): void
    {
        Excel::fake();
        $this->actingAs($this->outsider);

        $this->refusedWith403(fn () => (new ReportGenerator())->generate(app(AuditLogService::class)));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->outsider->id]);
    }

    /**
     * KNOWN GAP — expected to FAIL until render() has a role check (RPT-3).
     *
     * render() builds the applicant table (names, contact details) with no role
     * check. The route is the only protection, so any other path that mounts the
     * component would show personal data. Same pattern as ACC-1.
     *
     * HOW TO REPRODUCE: call render() on the component as a user with no role.
     * BUG: the view is returned. Expected: 403.
     *
     * FIX: add abort_if(!auth()->user()->hasRole(['staff', 'admin']), 403); as the first line of render().
     */
    public function test_user_without_role_cannot_render_component(): void
    {
        $this->makeApplicant();
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new ReportGenerator())->render()),
            'User without a role: render() should be refused with 403.'
        );
    }

    // ── MONTHLY WINDOW ───────────────────────────────────────────────

    public function test_monthly_window_defaults_to_the_current_month(): void
    {
        Carbon::setTestNow('2026-11-16 10:00:00');

        [$start, $end] = $this->callPrivate($this->newComponent(), 'monthlyWindow');

        $this->assertSame('2026-11-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-11-16 23:59:59', $end->toDateTimeString());
    }

    public function test_monthly_window_with_only_date_to_starts_at_that_months_first_day(): void
    {
        Carbon::setTestNow('2026-11-16 10:00:00');

        [$start, $end] = $this->callPrivate($this->newComponent(['dateTo' => '2026-03-20']), 'monthlyWindow');

        $this->assertSame('2026-03-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-03-20 23:59:59', $end->toDateTimeString());

    }

    public function test_monthly_window_uses_both_dates_when_given(): void
    {
        [$start, $end] = $this->callPrivate(
            $this->newComponent(['dateFrom' => '2026-02-10', 'dateTo' => '2026-04-05']),
            'monthlyWindow'
        );

        $this->assertSame('2026-02-10 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-04-05 23:59:59', $end->toDateTimeString());
    }

    /**
     * KNOWN BUG — expected to FAIL (RPT-4).
     *
     * If Date From is in the future and Date To is blank, the end defaults to
     * today, so the window runs backwards. The report is empty and the heading
     * shows a "to" date earlier than the "from" date. Validation only checks
     * dateTo against dateFrom, not the reverse.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Open /reports, choose "PESO Monthly Employment Report (PDF)".
     *   2. Set Date From to next month, leave Date To blank, click Download.
     *   3. BUG: a report titled with a start date after its end date.
     *      Expected: a validation error, or a window that is never reversed.
     *
     * FIX: require dateTo when dateFrom is after today, or reject a dateFrom in the future.
     */
    public function test_monthly_window_never_runs_backwards(): void
    {
        Carbon::setTestNow('2026-11-16 10:00:00');

        [$start, $end] = $this->callPrivate($this->newComponent(['dateFrom' => '2027-01-01']), 'monthlyWindow');

        $this->assertTrue($start->lte($end), 'The monthly window start must not be after its end.');
    }

    // ── MONTHLY FIGURES ──────────────────────────────────────────────

    private function monthlyData(array $props = []): array
    {
        $this->actingAs($this->staff);

        return $this->callPrivate($this->newComponent(array_merge([
            'dateFrom' => '2026-11-01',
            'dateTo'   => '2026-11-30',
        ], $props)), 'buildMonthlyData');
    }

    private function rowFor(array $data, string $label): ?array
    {
        return collect($data['rows'])->firstWhere('label', $label);
    }

    public function test_monthly_counts_split_period_and_as_of_by_sex(): void
    {
        $this->makeApplicant(['sex' => 'Male',   'created_at' => '2026-11-05 10:00:00']); // in period
        $this->makeApplicant(['sex' => 'Female', 'created_at' => '2026-11-10 10:00:00']); // in period
        $this->makeApplicant(['sex' => 'Male',   'created_at' => '2026-09-01 10:00:00']); // before: as-of only
        $this->makeApplicant(['sex' => 'Male',   'created_at' => '2026-12-05 10:00:00']); // after: excluded

        $row = $this->rowFor($this->monthlyData(), '2.1 Regular Program');

        // period M, F, T, then as-of M, F, T
        $this->assertSame([1, 1, 2, 2, 1, 3], $row['values']);
    }

    public function test_monthly_counts_exclude_inactive_applicants(): void
    {
        $this->makeApplicant(['sex' => 'Male', 'created_at' => '2026-11-05 10:00:00']);
        $this->makeApplicant(['sex' => 'Male', 'created_at' => '2026-11-06 10:00:00', 'is_active' => false]);

        $row = $this->rowFor($this->monthlyData(), '2.1 Regular Program');

        $this->assertSame([1, 0, 1, 1, 0, 1], $row['values']);
    }

    public function test_monthly_counts_include_applicant_registered_at_the_last_second_of_the_period(): void
    {
        $this->makeApplicant(['sex' => 'Female', 'created_at' => '2026-11-30 23:59:59']);

        $row = $this->rowFor($this->monthlyData(), '2.1 Regular Program');

        $this->assertSame(1, $row['values'][2]);
    }

    public function test_monthly_counts_with_no_applicants_are_all_zero(): void
    {
        $row = $this->rowFor($this->monthlyData(), '2.1 Regular Program');

        $this->assertSame([0, 0, 0, 0, 0, 0], $row['values']);
    }

    public function test_monthly_sex_filter_applies_to_both_columns(): void
    {
        $this->makeApplicant(['sex' => 'Male',   'created_at' => '2026-11-05 10:00:00']);
        $this->makeApplicant(['sex' => 'Female', 'created_at' => '2026-11-06 10:00:00']);

        $row = $this->rowFor($this->monthlyData(['sex' => 'Female']), '2.1 Regular Program');

        $this->assertSame([0, 1, 1, 0, 1, 1], $row['values']);
    }

    public function test_monthly_category_filter_keeps_only_applicants_with_that_category(): void
    {
        $welding = SkillCategory::create(['name' => 'Welding']);
        $welder  = $this->makeApplicant(['sex' => 'Male', 'created_at' => '2026-11-05 10:00:00']);
        $other   = $this->makeApplicant(['sex' => 'Male', 'created_at' => '2026-11-06 10:00:00']);
        $welder->skills()->attach($this->makeSkill('Arc Welding', $welding)->id);
        $other->skills()->attach($this->makeSkill('Baking')->id);

        $row = $this->rowFor($this->monthlyData(['categoryId' => (string) $welding->id]), '2.1 Regular Program');

        $this->assertSame(1, $row['values'][2]);
    }

    public function test_monthly_skill_rows_count_each_skill_once_per_applicant_split_by_sex(): void
    {
        $skill = $this->makeSkill('Welding');
        $inPeriod = $this->makeApplicant(['sex' => 'Male', 'created_at' => '2026-11-05 10:00:00']);
        $earlier  = $this->makeApplicant(['sex' => 'Female', 'created_at' => '2026-09-01 10:00:00']);
        $inPeriod->skills()->attach($skill->id);
        $earlier->skills()->attach($skill->id);

        $row = $this->rowFor($this->monthlyData(), 'Welding');

        $this->assertSame([1, 0, 1, 1, 1, 2], $row['values']);
    }

    public function test_monthly_skill_only_in_earlier_registrations_shows_zero_for_the_period(): void
    {
        $earlier = $this->makeApplicant(['sex' => 'Male', 'created_at' => '2026-09-01 10:00:00']);
        $earlier->skills()->attach($this->makeSkill('Carpentry')->id);

        $row = $this->rowFor($this->monthlyData(), 'Carpentry');

        $this->assertSame([0, 0, 0, 1, 0, 1], $row['values']);
    }

    public function test_monthly_skill_rows_are_sorted_by_name(): void
    {
        $a = $this->makeApplicant(['created_at' => '2026-11-05 10:00:00']);
        foreach (['Welding', 'Baking', 'Carpentry'] as $n) {
            $a->skills()->attach($this->makeSkill($n)->id);
        }

        $labels = collect($this->monthlyData()['rows'])
            ->filter(fn ($r) => in_array($r['label'], ['Welding', 'Baking', 'Carpentry']))
            ->pluck('label')->values()->all();

        $this->assertSame(['Baking', 'Carpentry', 'Welding'], $labels);
    }

    public function test_monthly_with_no_skills_shows_placeholder_row_without_figures(): void
    {
        $row = $this->rowFor($this->monthlyData(), 'No skills recorded for these filters');

        $this->assertNotNull($row);
        $this->assertNull($row['values']);
    }

    public function test_monthly_heading_rows_have_no_figures(): void
    {
        $data = $this->monthlyData();

        $this->assertNull($this->rowFor($data, '2. Applicants Registered')['values']);
        $this->assertNull($this->rowFor($data, 'B. Skills of Registered Applicants')['values']);
    }

    public function test_monthly_header_fields(): void
    {
        $data = $this->monthlyData();

        $this->assertSame('November 2026', $data['monthYear']);
        $this->assertSame('Nov 01, 2026 to Nov 30, 2026', $data['periodLabel']);
        $this->assertSame($this->staff->name, $data['preparedBy']);
    }

    // ── FIGURE HELPERS ───────────────────────────────────────────────

    public function test_age_bracket_boundaries(): void
    {
        $c = $this->newComponent();

        $expected = [
            [null, 'Not specified'], [0, 'Under 18'], [17, 'Under 18'],
            [18, '18-24'], [24, '18-24'], [25, '25-34'], [34, '25-34'],
            [35, '35-44'], [44, '35-44'], [45, '45-54'], [54, '45-54'],
            [55, '55-64'], [64, '55-64'], [65, '65 and over'], [99, '65 and over'],
        ];

        foreach ($expected as [$age, $bracket]) {
            $this->assertSame($bracket, $this->callPrivate($c, 'ageBracket', [$age]), "Age " . var_export($age, true));
        }
    }

    public function test_tally_sexes_counts_unspecified_sex_only_in_the_total(): void
    {
        $apps = new Collection([
            $this->memApplicant(['sex' => 'Male']),
            $this->memApplicant(['sex' => 'Female']),
            $this->memApplicant(['sex' => null]),
        ]);

        $this->assertSame(
            ['male' => 1, 'female' => 1, 'total' => 3],
            $this->callPrivate($this->newComponent(), 'tallySexes', [$apps])
        );
    }

    public function test_tally_sexes_of_an_empty_collection_is_all_zero(): void
    {
        $this->assertSame(
            ['male' => 0, 'female' => 0, 'total' => 0],
            $this->callPrivate($this->newComponent(), 'tallySexes', [new Collection()])
        );
    }

    public function test_skill_tallies_count_a_repeated_skill_once_per_applicant(): void
    {
        $apps = new Collection([
            $this->memApplicant(['sex' => 'Male'], ['Welding', 'Welding']),
            $this->memApplicant(['sex' => 'Female'], ['Welding', 'Baking']),
        ]);

        $out = $this->callPrivate($this->newComponent(), 'skillTallies', [$apps]);

        $this->assertSame(['male' => 1, 'female' => 1, 'total' => 2], $out['Welding']);
        $this->assertSame(['male' => 0, 'female' => 1, 'total' => 1], $out['Baking']);
    }

    public function test_skill_tallies_for_unspecified_sex_count_only_in_the_total(): void
    {
        $apps = new Collection([$this->memApplicant(['sex' => null], ['Welding'])]);

        $out = $this->callPrivate($this->newComponent(), 'skillTallies', [$apps]);

        $this->assertSame(['male' => 0, 'female' => 0, 'total' => 1], $out['Welding']);
    }

    public function test_six_returns_figures_in_form_order(): void
    {
        $p = ['male' => 1, 'female' => 2, 'total' => 3];
        $t = ['male' => 4, 'female' => 5, 'total' => 9];

        $this->assertSame([1, 2, 3, 4, 5, 9], $this->callPrivate($this->newComponent(), 'six', [$p, $t]));
    }

    // ── LISTING REPORT FIGURES ───────────────────────────────────────

    private function report(array $apps, array $props = []): array
    {
        $this->actingAs($this->staff);

        return $this->callPrivate($this->newComponent($props), 'buildReport', [new Collection($apps)]);
    }

    public function test_report_with_no_applicants_has_zero_totals_and_no_division_error(): void
    {
        $r = $this->report([]);

        $this->assertSame(0, $r['total']);
        $this->assertNull($r['avgAge']);
        $this->assertSame('No registrations match the selected filters', $r['coverage']);
        $this->assertSame([], $r['list']);
    }

    public function test_report_counts_male_and_female_and_percentages(): void
    {
        $r = $this->report([
            $this->memApplicant(['sex' => 'Male']),
            $this->memApplicant(['sex' => 'Female']),
            $this->memApplicant(['sex' => 'Female']),
            $this->memApplicant(['sex' => 'Female']),
        ]);

        $this->assertSame(4, $r['total']);
        $this->assertSame(1, $r['male']);
        $this->assertSame(3, $r['female']);

        $female = collect($r['sex'])->firstWhere('label', 'Female');
        $this->assertSame(75.0, (float) $female['pct']);
    }

    public function test_report_sex_rows_follow_fixed_order_with_unknown_last(): void
    {
        $r = $this->report([
            $this->memApplicant(['sex' => null]),
            $this->memApplicant(['sex' => 'Female']),
            $this->memApplicant(['sex' => 'Male']),
        ]);

        $this->assertSame(['Male', 'Female', 'Not specified'], array_column($r['sex'], 'label'));
    }

    public function test_report_status_rows_follow_fixed_order(): void
    {
        $r = $this->report([
            $this->memApplicant(['status' => 'Verified']),
            $this->memApplicant(['status' => 'Duplicated']),
            $this->memApplicant(['status' => 'Pending']),
            $this->memApplicant(['status' => 'Flagged']),
        ]);

        $this->assertSame(['Pending', 'Verified', 'Flagged', 'Duplicated'], array_column($r['status'], 'label'));
    }

    public function test_report_average_age_ignores_applicants_without_birthdate(): void
    {
        Carbon::setTestNow('2026-11-16 10:00:00');

        $r = $this->report([
            $this->memApplicant(['birthdate' => Carbon::parse('2006-01-01')]),  // 20
            $this->memApplicant(['birthdate' => Carbon::parse('1996-01-01')]),  // 30
            $this->memApplicant(['birthdate' => null]),
        ]);

        $this->assertSame(25, $r['avgAge']);
    }

    public function test_report_puts_missing_birthdate_in_not_specified_age_bracket(): void
    {
        $r = $this->report([$this->memApplicant(['birthdate' => null])]);

        $this->assertSame(['Not specified'], array_column($r['age'], 'label'));
    }

    public function test_report_uses_na_for_missing_values_in_the_list(): void
    {
        $r = $this->report([$this->memApplicant(['sex' => null, 'status' => null])]);

        $row = $r['list'][0];

        $this->assertSame(1, $row['no']);
        $this->assertSame('NA', $row['sex']);
        $this->assertSame('NA', $row['age']);
        $this->assertSame('NA', $row['barangay']);
        $this->assertSame('NA', $row['education']);
        $this->assertSame('NA', $row['status']);
    }

    public function test_report_top_skills_are_capped_at_twenty(): void
    {
        $names = array_map(fn ($i) => 'Skill ' . $i, range(1, 25));

        $r = $this->report([$this->memApplicant([], $names)]);

        $this->assertCount(20, $r['skills']);
    }

    public function test_report_counts_a_skill_once_per_applicant(): void
    {
        $r = $this->report([$this->memApplicant([], ['Welding', 'Welding'])]);

        $this->assertSame(1, collect($r['skills'])->firstWhere('label', 'Welding')['count']);
    }

    public function test_report_trend_groups_registrations_by_month_in_order(): void
    {
        $r = $this->report([
            $this->memApplicant(['created_at' => Carbon::parse('2026-03-10')]),
            $this->memApplicant(['created_at' => Carbon::parse('2026-01-05')]),
            $this->memApplicant(['created_at' => Carbon::parse('2026-03-20')]),
        ]);

        $this->assertSame(['January 2026', 'March 2026'], array_column($r['trend'], 'label'));
        $this->assertSame([1, 2], array_column($r['trend'], 'count'));
    }

    public function test_report_coverage_shows_first_and_last_registration_dates(): void
    {
        $r = $this->report([
            $this->memApplicant(['created_at' => Carbon::parse('2026-03-10 09:00:00')]),
            $this->memApplicant(['created_at' => Carbon::parse('2026-01-05 09:00:00')]),
        ]);

        $this->assertStringContainsString('Jan 05, 2026', $r['coverage']);
        $this->assertStringContainsString('Mar 10, 2026', $r['coverage']);
    }

    public function test_report_filters_text_says_none_when_no_filter_is_set(): void
    {
        $this->assertSame('None (all registrations)', $this->report([])['filters']);
    }

    public function test_report_filters_text_lists_the_selected_filters(): void
    {
        $category = SkillCategory::create(['name' => 'Welding']);

        $r = $this->report([], [
            'sex'        => 'Female',
            'barangayId' => (string) $this->barangay->id,
            'categoryId' => (string) $category->id,
            'educLevel'  => 'High School',
        ]);

        $this->assertStringContainsString('Sex: Female', $r['filters']);
        $this->assertStringContainsString('Barangay: ' . $this->barangay->name, $r['filters']);
        $this->assertStringContainsString('Skill category: Welding', $r['filters']);
        $this->assertStringContainsString('Education level: High School', $r['filters']);
    }

    public function test_report_prepared_by_is_the_logged_in_user(): void
    {
        $this->assertSame($this->staff->name, $this->report([])['preparedBy']);
    }
}