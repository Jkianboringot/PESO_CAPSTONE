<?php

namespace Tests\Feature;

use App\Exports\ApplicantsExport;
use App\Livewire\ReportGenerator;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\SkillCategory;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use App\Models\Education;
use App\Models\Skill;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Mockery\MockInterface;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Tests the Livewire ReportGenerator (filters + on-screen table + export).
 *
 * ASSUMPTIONS you may need to adjust to match your project:
 *  - Roles are Spatie roles named 'admin', 'staff', 'applicant'.
 *  - Applicant::active() excludes applicants whose status is 'Archived'
 *    (change ACTIVE_STATUS / INACTIVE_STATUS below if your scope differs).
 *  - Applicant columns match the ones used in makeApplicant().
 *  - Municipality::factory() and Barangay::factory() exist (your
 *    RegistrationFormTest already uses them).
 */
class ReportGeneratorTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private const ACTIVE_STATUS   = 'Pending';
    private const INACTIVE_STATUS = 'Archived';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();   // creates $this->admin and $this->staff (same trait as your other tests)
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    private function actingAsRole(string $role): User
    {
        $user = $role === 'admin' ? $this->admin : $this->staff;
        $this->actingAs($user);

        return $user;
    }

    private function makeGeography(string $barangayName = 'San Jose'): array
    {
        $municipality = Municipality::factory()->create(['name' => 'Virac']);
        $barangay     = Barangay::factory()->create([
            'municipality_id' => $municipality->id,
            'name'            => $barangayName,
        ]);

        return [$municipality, $barangay];
    }

    private function makeCategory(string $name = 'Information Technology'): SkillCategory
    {
        // SkillCategory::factory() does not exist -> forceCreate()
        return SkillCategory::forceCreate(['name' => $name]);
    }

    private function makeApplicant(Barangay $barangay, array $overrides = []): Applicant
    {
        static $counter = 0;
        $counter++;

        return Applicant::forceCreate(array_merge([
            'reference_id'   => 'PESO-' . str_pad((string) $counter, 13, '0', STR_PAD_LEFT),
            'last_name'      => 'Lastname' . $counter,
            'first_name'     => 'First' . $counter,
            'birthdate'      => '1995-06-15',
            'sex'            => 'Male',
            'civil_status'   => 'Single',
            'contact_number' => '0917' . str_pad((string) $counter, 7, '0', STR_PAD_LEFT),
            'barangay_id'    => $barangay->id,
            'password'       => Hash::make('password123'),
            'status'         => self::ACTIVE_STATUS,
            'oath_given'     => true,
            'oath_given_at'  => now(),
            'consent_given'  => true,
            'consent_given_at' => now(),
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────
    // ACCESS CONTROL
    // ─────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_staff_can_open_the_reports_page(): void
    {
        $this->actingAsRole('staff');

        $this->get('/reports')->assertOk()->assertSeeLivewire(ReportGenerator::class);
    }

    public function test_admin_can_open_the_reports_page(): void
    {
        $this->actingAsRole('admin');

        $this->get('/reports')->assertOk()->assertSeeLivewire(ReportGenerator::class);
    }

    public function test_user_without_staff_or_admin_role_cannot_open_the_reports_page(): void
    {
        $user = User::factory()->create(); // no role
        $this->actingAs($user);

        $this->get('/reports')->assertForbidden();
    }

    public function test_user_without_role_cannot_call_generate(): void
    {
        $this->actingAs(User::factory()->create());

        Excel::fake();

        Livewire::test(ReportGenerator::class)
            ->call('generate')
            ->assertForbidden();    }

    // ─────────────────────────────────────────────────────────────────
    // INITIAL RENDER
    // ─────────────────────────────────────────────────────────────────

    public function test_component_renders_with_default_filter_values(): void
    {
        $this->actingAsRole('staff');

        Livewire::test(ReportGenerator::class)
            ->assertOk()
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '')
            ->assertSet('barangayId', '')
            ->assertSet('categoryId', '')
            ->assertSet('educLevel', '')
            ->assertSet('sex', '')
            ->assertSet('format', 'xlsx');
    }

    public function test_barangay_and_category_dropdowns_are_populated_and_sorted(): void
    {
        $this->actingAsRole('staff');

        [, $zeta]  = $this->makeGeography('Zeta');
        $alpha     = Barangay::factory()->create(['municipality_id' => $zeta->municipality_id, 'name' => 'Alpha']);
        $this->makeCategory('Welding');
        $this->makeCategory('Agriculture');

        $component = Livewire::test(ReportGenerator::class);

        $this->assertEquals(['Alpha', 'Zeta'], array_values($component->viewData('barangays')->all()));
        $this->assertEquals(['Agriculture', 'Welding'], array_values($component->viewData('categories')->all()));
    }

    // ─────────────────────────────────────────────────────────────────
    // ON-SCREEN TABLE + FILTERS
    // ─────────────────────────────────────────────────────────────────

    public function test_table_lists_applicants_ordered_by_last_then_first_name(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $this->makeApplicant($barangay, ['last_name' => 'Santos', 'first_name' => 'Zed']);
        $this->makeApplicant($barangay, ['last_name' => 'Santos', 'first_name' => 'Ana']);
        $this->makeApplicant($barangay, ['last_name' => 'Abad',   'first_name' => 'Mia']);

        $names = Livewire::test(ReportGenerator::class)
            ->viewData('applicants')
            ->map(fn ($a) => $a->last_name . ' ' . $a->first_name)
            ->all();

        $this->assertEquals(['Abad Mia', 'Santos Ana', 'Santos Zed'], $names);
    }

    public function test_inactive_applicants_are_not_listed(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $this->makeApplicant($barangay, ['last_name' => 'Visible']);
        $this->makeApplicant($barangay, ['last_name' => 'Hidden', 'status' => self::INACTIVE_STATUS]);

        Livewire::test(ReportGenerator::class)
            ->assertSee('Visible')
            ->assertDontSee('Hidden');
    }

    public function test_table_is_paginated_by_15(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        for ($i = 0; $i < 20; $i++) {
            $this->makeApplicant($barangay);
        }

        $applicants = Livewire::test(ReportGenerator::class)->viewData('applicants');

        $this->assertEquals(20, $applicants->total());
        $this->assertCount(15, $applicants->items());
    }

    public function test_sex_filter_narrows_the_table(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $this->makeApplicant($barangay, ['last_name' => 'Malecase',   'sex' => 'Male']);
        $this->makeApplicant($barangay, ['last_name' => 'Femalecase', 'sex' => 'Female']);

        Livewire::test(ReportGenerator::class)
            ->set('sex', 'Female')
            ->assertSee('Femalecase')
            ->assertDontSee('Malecase');
    }

    public function test_barangay_filter_narrows_the_table(): void
    {
        $this->actingAsRole('staff');
        [$municipality, $barangayA] = $this->makeGeography('Barangay A');
        $barangayB = Barangay::factory()->create(['municipality_id' => $municipality->id, 'name' => 'Barangay B']);

        $this->makeApplicant($barangayA, ['last_name' => 'InsideA']);
        $this->makeApplicant($barangayB, ['last_name' => 'InsideB']);

        Livewire::test(ReportGenerator::class)
            ->set('barangayId', (string) $barangayA->id)
            ->assertSee('InsideA')
            ->assertDontSee('InsideB');
    }

    public function test_changing_a_filter_resets_pagination_to_page_one(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        for ($i = 0; $i < 20; $i++) {
            $this->makeApplicant($barangay);
        }

        Livewire::test(ReportGenerator::class)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('sex', 'Male')
            ->assertSet('paginators.page', 1);
    }

    public function test_changing_the_format_does_not_reset_pagination(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        for ($i = 0; $i < 20; $i++) {
            $this->makeApplicant($barangay);
        }

        Livewire::test(ReportGenerator::class)
            ->call('gotoPage', 2)
            ->set('format', 'csv')
            ->assertSet('paginators.page', 2);
    }

    // ─────────────────────────────────────────────────────────────────
    // VALIDATION
    // ─────────────────────────────────────────────────────────────────

    public function test_generate_blocked_when_date_to_is_before_date_from(): void
    {
        $this->actingAsRole('staff');
        Excel::fake();

        Livewire::test(ReportGenerator::class)
            ->set('dateFrom', '2026-06-30')
            ->set('dateTo', '2026-01-01')
            ->call('generate')
            ->assertHasErrors(['dateTo']);    }

    public function test_generate_blocked_when_date_is_not_a_valid_date(): void
    {
        $this->actingAsRole('staff');
        Excel::fake();

        Livewire::test(ReportGenerator::class)
            ->set('dateFrom', 'not-a-date')
            ->call('generate')
            ->assertHasErrors(['dateFrom']);    }

    public function test_generate_blocked_when_format_is_not_allowed(): void
    {
        $this->actingAsRole('staff');
        Excel::fake();

        Livewire::test(ReportGenerator::class)
            ->set('format', 'docx')
            ->call('generate')
            ->assertHasErrors(['format']);    }

    public function test_audit_log_is_not_written_when_validation_fails(): void
    {
        $this->actingAsRole('staff');
        Excel::fake();

        $this->mock(AuditLogService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('logReportDownloaded');
        });

        Livewire::test(ReportGenerator::class)
            ->set('format', 'docx')
            ->call('generate')
            ->assertHasErrors(['format']);
    }

    // ─────────────────────────────────────────────────────────────────
    // DOWNLOAD
    // ─────────────────────────────────────────────────────────────────

    public function test_generate_downloads_xlsx_with_expected_filename_by_default(): void
    {
        $this->actingAsRole('staff');
        Carbon::setTestNow('2026-11-13 10:30:00');
        Excel::fake();

        Livewire::test(ReportGenerator::class)
            ->call('generate')
            ->assertHasNoErrors();

        Excel::assertDownloaded(
            'PESO_Catanduanes_Workforce_20261113_103000.xlsx',
            fn ($export) => $export instanceof ApplicantsExport
        );
    }

    public function test_generate_downloads_csv_when_csv_selected(): void
    {
        $this->actingAsRole('admin');
        Carbon::setTestNow('2026-11-13 10:30:00');
        Excel::fake();

        Livewire::test(ReportGenerator::class)
            ->set('format', 'csv')
            ->call('generate')
            ->assertHasNoErrors();

        Excel::assertDownloaded(
            'PESO_Catanduanes_Workforce_20261113_103000.csv',
            fn ($export) => $export instanceof ApplicantsExport
        );
    }

    public function test_generate_downloads_pdf_when_pdf_selected(): void
    {
        $this->actingAsRole('admin');
        Carbon::setTestNow('2026-11-13 10:30:00');
        Excel::fake();

        Livewire::test(ReportGenerator::class)
            ->set('format', 'pdf')
            ->call('generate')
            ->assertHasNoErrors();

        Excel::assertDownloaded(
            'PESO_Catanduanes_Workforce_20261113_103000.pdf',
            fn ($export) => $export instanceof ApplicantsExport
        );
    }

    public function test_generate_logs_only_the_filters_that_were_filled_in(): void
    {
        $this->actingAsRole('staff');
        Excel::fake();

        [, $barangay] = $this->makeGeography();

        $this->mock(AuditLogService::class, function (MockInterface $mock) use ($barangay) {
            $mock->shouldReceive('logReportDownloaded')
                ->once()
                ->with(json_encode([
                    'dateFrom'   => '2026-01-01',
                    'dateTo'     => '2026-06-30',
                    'barangayId' => (string) $barangay->id,
                    'sex'        => 'Female',
                ]));
        });

        Livewire::test(ReportGenerator::class)
            ->set('dateFrom', '2026-01-01')
            ->set('dateTo', '2026-06-30')
            ->set('barangayId', (string) $barangay->id)
            ->set('sex', 'Female')
            ->call('generate')
            ->assertHasNoErrors();
    }

    public function test_generate_logs_empty_params_when_no_filters_are_set(): void
    {
        $this->actingAsRole('staff');
        Excel::fake();

        $this->mock(AuditLogService::class, function (MockInterface $mock) {
            $mock->shouldReceive('logReportDownloaded')
                ->once()
                ->with(json_encode([]));
        });

        Livewire::test(ReportGenerator::class)
            ->call('generate')
            ->assertHasNoErrors();
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT CONTENT (ApplicantsExport)
    //
    // These render the export to a CSV string with Excel::raw() and check
    // what is inside, so they do not depend on whether the export uses
    // FromQuery or FromCollection. They only assume the constructor takes
    // the same $params array that generate() builds.
    // ─────────────────────────────────────────────────────────────────

    private function exportCsv(array $params = []): string
    {
        return Excel::raw(new ApplicantsExport($params), ExcelWriter::CSV);
    }

    private function makeEducation(Applicant $applicant, string $level): void
    {
        Education::forceCreate([
            'applicant_id'  => $applicant->id,
            'highest_level' => $level,
        ]);
    }

    private function attachSkill(Applicant $applicant, Skill $skill): void
    {
        $applicant->skills()->attach($skill->id, ['proficiency_level' => 'Intermediate']);
    }

    public function test_export_has_the_expected_column_headings(): void
    {
        $this->actingAsRole('staff');

        $firstLine = strtok($this->exportCsv(), "\n");

        foreach (['Reference ID', 'Last Name', 'First Name', 'Birthdate', 'Sex',
                  'Contact Number', 'Barangay', 'Municipality', 'Skills',
                  'Registration Status', 'Date Registered'] as $heading) {
            $this->assertStringContainsString($heading, $firstLine);
        }
    }

    public function test_export_with_no_filters_includes_all_active_applicants(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $this->makeApplicant($barangay, ['last_name' => 'Exportone']);
        $this->makeApplicant($barangay, ['last_name' => 'Exporttwo']);

        $csv = $this->exportCsv();

        $this->assertStringContainsString('Exportone', $csv);
        $this->assertStringContainsString('Exporttwo', $csv);
    }

    public function test_export_excludes_inactive_applicants(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $this->makeApplicant($barangay, ['last_name' => 'Keepme']);
        $this->makeApplicant($barangay, ['last_name' => 'Dropme', 'status' => self::INACTIVE_STATUS]);

        $csv = $this->exportCsv();

        $this->assertStringContainsString('Keepme', $csv);
        $this->assertStringNotContainsString('Dropme', $csv);
    }

    public function test_export_is_not_limited_to_the_first_page_of_the_screen_table(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        for ($i = 1; $i <= 20; $i++) {
            $this->makeApplicant($barangay, ['last_name' => sprintf('Bulk%02d', $i)]);
        }

        $csv = $this->exportCsv();

        // The screen shows 15 per page; the file must contain all 20.
        $this->assertStringContainsString('Bulk01', $csv);
        $this->assertStringContainsString('Bulk20', $csv);
    }

    public function test_export_sex_filter(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $this->makeApplicant($barangay, ['last_name' => 'Malecase',   'sex' => 'Male']);
        $this->makeApplicant($barangay, ['last_name' => 'Femalecase', 'sex' => 'Female']);

        $csv = $this->exportCsv(['sex' => 'Female']);

        $this->assertStringContainsString('Femalecase', $csv);
        $this->assertStringNotContainsString('Malecase', $csv);
    }

    public function test_export_barangay_filter(): void
    {
        $this->actingAsRole('staff');
        [$municipality, $barangayA] = $this->makeGeography('Barangay A');
        $barangayB = Barangay::factory()->create(['municipality_id' => $municipality->id, 'name' => 'Barangay B']);

        $this->makeApplicant($barangayA, ['last_name' => 'InsideA']);
        $this->makeApplicant($barangayB, ['last_name' => 'InsideB']);

        $csv = $this->exportCsv(['barangayId' => (string) $barangayA->id]);

        $this->assertStringContainsString('InsideA', $csv);
        $this->assertStringNotContainsString('InsideB', $csv);
    }

    public function test_export_education_level_filter(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $graduate = $this->makeApplicant($barangay, ['last_name' => 'Gradperson']);
        $highSch  = $this->makeApplicant($barangay, ['last_name' => 'Hsperson']);
        $this->makeEducation($graduate, 'College Graduate');
        $this->makeEducation($highSch, 'High School');

        $csv = $this->exportCsv(['educLevel' => 'College Graduate']);

        $this->assertStringContainsString('Gradperson', $csv);
        $this->assertStringNotContainsString('Hsperson', $csv);
    }

    public function test_export_skill_category_filter(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $itCategory   = $this->makeCategory('Information Technology');
        $weldCategory = $this->makeCategory('Welding');

        // ASSUMPTION: skills table has name + skill_category_id.
        $itSkill   = Skill::forceCreate(['name' => 'PHP',     'skill_category_id' => $itCategory->id]);
        $weldSkill = Skill::forceCreate(['name' => 'Arc Weld', 'skill_category_id' => $weldCategory->id]);

        $coder  = $this->makeApplicant($barangay, ['last_name' => 'Coderperson']);
        $welder = $this->makeApplicant($barangay, ['last_name' => 'Welderperson']);
        $this->attachSkill($coder, $itSkill);
        $this->attachSkill($welder, $weldSkill);

        $csv = $this->exportCsv(['categoryId' => (string) $itCategory->id]);

        $this->assertStringContainsString('Coderperson', $csv);
        $this->assertStringNotContainsString('Welderperson', $csv);
    }

    public function test_export_date_range_filter(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        // ASSUMPTION: byDateRange() filters on created_at (Date Registered).
        $this->makeApplicant($barangay, ['last_name' => 'Inrange',  'created_at' => '2026-03-15 09:00:00']);
        $this->makeApplicant($barangay, ['last_name' => 'Toolate',  'created_at' => '2026-09-15 09:00:00']);
        $this->makeApplicant($barangay, ['last_name' => 'Tooearly', 'created_at' => '2025-12-15 09:00:00']);

        $csv = $this->exportCsv(['dateFrom' => '2026-01-01', 'dateTo' => '2026-06-30']);

        $this->assertStringContainsString('Inrange', $csv);
        $this->assertStringNotContainsString('Toolate', $csv);
        $this->assertStringNotContainsString('Tooearly', $csv);
    }

    public function test_export_combines_multiple_filters_with_and_logic(): void
    {
        $this->actingAsRole('staff');
        [$municipality, $barangayA] = $this->makeGeography('Barangay A');
        $barangayB = Barangay::factory()->create(['municipality_id' => $municipality->id, 'name' => 'Barangay B']);

        $this->makeApplicant($barangayA, ['last_name' => 'Matchboth',   'sex' => 'Female']);
        $this->makeApplicant($barangayA, ['last_name' => 'Wrongsex',    'sex' => 'Male']);
        $this->makeApplicant($barangayB, ['last_name' => 'Wrongplace',  'sex' => 'Female']);

        $csv = $this->exportCsv([
            'barangayId' => (string) $barangayA->id,
            'sex'        => 'Female',
        ]);

        $this->assertStringContainsString('Matchboth', $csv);
        $this->assertStringNotContainsString('Wrongsex', $csv);
        $this->assertStringNotContainsString('Wrongplace', $csv);
    }

    public function test_export_matches_what_the_screen_table_shows_for_the_same_filters(): void
    {
        $this->actingAsRole('staff');
        [, $barangay] = $this->makeGeography();

        $this->makeApplicant($barangay, ['last_name' => 'Samefemale', 'sex' => 'Female']);
        $this->makeApplicant($barangay, ['last_name' => 'Samemale',   'sex' => 'Male']);

        Livewire::test(ReportGenerator::class)
            ->set('sex', 'Female')
            ->assertSee('Samefemale')
            ->assertDontSee('Samemale');

        $csv = $this->exportCsv(['sex' => 'Female']);

        $this->assertStringContainsString('Samefemale', $csv);
        $this->assertStringNotContainsString('Samemale', $csv);
    }
}