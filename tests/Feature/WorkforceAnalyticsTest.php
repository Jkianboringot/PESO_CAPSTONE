<?php

namespace Tests\Feature;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Education;
use App\Models\Skill;
use App\Models\SkillCategory;
use Livewire\Livewire;
use App\Livewire\WorkforceAnalyticsDashboard;
use App\Livewire\SkillsGapAnalysis;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * Analytics is why PESO Connect was built. If the charts return
 * wrong numbers, PESO makes wrong decisions: recommends wrong training
 * programs, reports wrong numbers to DOLE, misidentifies skill gaps.
 *
 * TESTING MINDSET:
 * I seed KNOWN data and assert EXPECTED outputs.
 * This is called "fixture-based testing": the test controls the data
 * so the expected results are mathematically certain.
 *
 * WHY NOT JUST TEST THE QUERY? Because Eloquent queries interact
 * with scopes, joins, and filters in complex ways. The only reliable
 * test is end-to-end: seed data → run component → assert output.
 *
 * KNOWN BUG / KNOWN GAP tests are expected to FAIL until the app code is fixed.
 * Do not weaken their assertions.
 */
class WorkforceAnalyticsTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private User $outsider;
    private Municipality $municipality;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $this->outsider     = User::factory()->create();
        $this->municipality = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay     = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
            'name'            => 'San Jose',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeApplicant(array $overrides = []): Applicant
    {
        return Applicant::factory()->create(array_merge([
            'barangay_id' => $this->barangay->id,
            'is_active'   => true,
        ], $overrides));
    }

    private function makeSkill(string $categoryName, string $skillName): Skill
    {
        $cat = SkillCategory::firstOrCreate(['name' => $categoryName]);

        return Skill::firstOrCreate([
            'name'              => $skillName,
            'skill_category_id' => $cat->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // PAGE ACCESS
    // ─────────────────────────────────────────────────────────────────

    public function test_staff_can_access_analytics_page(): void
    {
        $this->actingAs($this->staff);
        $this->get(route('analytics'))->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────
    // TOTAL COUNT ACCURACY
    // ─────────────────────────────────────────────────────────────────

    /**
     * The 'total' summary count must equal the number of active applicants.
     * Inactive applicants must NOT be counted.
     *
     * WHY: The summary card on the dashboard shows "Total Registered
     * Applicants." If inactive records inflate this number, PESO would
     * report inflated workforce numbers to DOLE.
     */
    public function test_total_count_reflects_only_active_applicants(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeApplicant(['is_active' => true]);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->makeApplicant(['is_active' => false]);
        }

        $chartData = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData');

        $this->assertEquals(5, $chartData['totals']['total'],
            'Total count must be 5 (active only). The 3 inactive must not be counted.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SKILL CHART ACCURACY
    // ─────────────────────────────────────────────────────────────────

    /**
     * The skills chart must accurately count applicants per skill.
     *
     * WHY: If counts are wrong, PESO might recommend training in a skill
     * that is already abundant and ignore a skill that is critically scarce.
     */
    public function test_skills_chart_counts_applicants_per_skill_correctly(): void
    {
        $skill1 = $this->makeSkill('ICT & Digital Technology', 'Web Development');
        $skill2 = $this->makeSkill('Agricultural & Fisheries', 'Rice Farming');

        for ($i = 0; $i < 7; $i++) {
            $this->makeApplicant()->skills()->attach($skill1->id, ['proficiency_level' => 'Beginner']);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->makeApplicant()->skills()->attach($skill2->id, ['proficiency_level' => 'Beginner']);
        }

        $chartData = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData');

        $labels = $chartData['skills']['labels']->toArray();
        $data   = $chartData['skills']['data']->toArray();

        $webDevIndex   = array_search('Web Development', $labels);
        $riceFarmIndex = array_search('Rice Farming', $labels);

        $this->assertNotFalse($webDevIndex,   'Web Development must appear in skills chart.');
        $this->assertNotFalse($riceFarmIndex, 'Rice Farming must appear in skills chart.');
        $this->assertEquals(7, $data[$webDevIndex], 'Web Development must have count of 7.');
        $this->assertEquals(3, $data[$riceFarmIndex], 'Rice Farming must have count of 3.');
    }

    // ─────────────────────────────────────────────────────────────────
    // EDUCATION CHART ACCURACY
    // ─────────────────────────────────────────────────────────────────

    /**
     * Education chart must correctly count applicants per education level.
     */
    public function test_education_chart_counts_correctly(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $a = $this->makeApplicant();
            Education::factory()->create(['applicant_id' => $a->id, 'highest_level' => 'College Graduate']);
        }
        for ($i = 0; $i < 2; $i++) {
            $a = $this->makeApplicant();
            Education::factory()->create(['applicant_id' => $a->id, 'highest_level' => 'Vocational/Technical']);
        }

        $chartData = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData');

        $labels = $chartData['education']['labels']->toArray();
        $data   = $chartData['education']['data']->toArray();

        $this->assertEquals(4, $data[array_search('College Graduate', $labels)], 'College Graduate must count 4.');
        $this->assertEquals(2, $data[array_search('Vocational/Technical', $labels)], 'Vocational/Technical must count 2.');
    }

    // ─────────────────────────────────────────────────────────────────
    // FILTER: INACTIVE EXCLUDED FROM ALL QUERIES
    // ─────────────────────────────────────────────────────────────────

    /**
     * All chart queries must exclude inactive applicants, including the
     * joinSub() queries used for each chart, not only the count query.
     */
    public function test_all_chart_queries_exclude_inactive_applicants(): void
    {
        $skill = $this->makeSkill('ICT & Digital Technology', 'Data Encoding');

        $active = $this->makeApplicant(['is_active' => true]);
        $active->skills()->attach($skill->id, ['proficiency_level' => 'Beginner']);

        $inactive = $this->makeApplicant(['is_active' => false]);
        $inactive->skills()->attach($skill->id, ['proficiency_level' => 'Beginner']);

        $chartData = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData');

        $labels = $chartData['skills']['labels']->toArray();
        $data   = $chartData['skills']['data']->toArray();

        $idx = array_search('Data Encoding', $labels);
        $this->assertNotFalse($idx);
        $this->assertEquals(1, $data[$idx], 'Skill count must be 1 (only active applicant).');
    }

    // ═════════════════════════════════════════════════════════════════
    // EDGE CASES: DASHBOARD DATA
    // ═════════════════════════════════════════════════════════════════

    public function test_empty_database_gives_zero_totals_and_empty_charts(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');

        $data = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData');

        $this->assertSame(0, $data['totals']['total']);
        $this->assertSame(0, $data['totals']['thisMonth']);
        $this->assertSame([], $data['skills']['labels']->toArray());
        $this->assertSame([], $data['education']['labels']->toArray());
    }

    public function test_empty_database_trend_is_twelve_zero_months(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');

        $trend = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData')['trend'];

        $this->assertCount(12, $trend['labels']);
        $this->assertSame(array_fill(0, 12, 0), $trend['data']);
    }

    public function test_default_trend_covers_last_twelve_months_ending_this_month(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');

        $labels = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData')['trend']['labels'];

        $this->assertCount(12, $labels);
        $this->assertSame('Dec 2025', $labels[0]);
        $this->assertSame('Nov 2026', $labels[11]);
    }

    public function test_trend_counts_registration_in_its_month_and_fills_gaps_with_zero(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->makeApplicant(['created_at' => '2026-03-10 09:00:00']);

        $trend = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData')['trend'];

        $this->assertSame(1, $trend['data'][array_search('Mar 2026', $trend['labels'])]);
        $this->assertSame(0, $trend['data'][array_search('Apr 2026', $trend['labels'])]);
    }

    public function test_trend_is_capped_at_sixty_months(): void
    {
        $labels = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->set('filterFrom', '2000-01-01')
            ->set('filterTo', '2026-11-17')
            ->get('chartData')['trend']['labels'];

        $this->assertCount(60, $labels);
    }

    public function test_reversed_date_range_is_swapped_and_gives_same_totals(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->makeApplicant(['created_at' => '2026-03-15 10:00:00']);
        $this->makeApplicant(['created_at' => '2026-08-15 10:00:00']);

        $correct = Livewire::actingAs($this->staff)->test(WorkforceAnalyticsDashboard::class)
            ->set('filterFrom', '2026-01-01')->set('filterTo', '2026-06-30')
            ->get('chartData');

        $reversed = Livewire::actingAs($this->staff)->test(WorkforceAnalyticsDashboard::class)
            ->set('filterFrom', '2026-06-30')->set('filterTo', '2026-01-01')
            ->get('chartData');

        $this->assertSame(1, $correct['totals']['total']);
        $this->assertSame($correct['totals']['total'], $reversed['totals']['total']);
    }

    public function test_date_to_includes_late_evening_registration_on_that_day(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->makeApplicant(['created_at' => '2026-03-31 23:45:00']);

        $total = Livewire::actingAs($this->staff)->test(WorkforceAnalyticsDashboard::class)
            ->set('filterFrom', '2026-03-01')
            ->set('filterTo', '2026-03-31')
            ->get('chartData')['totals']['total'];

        $this->assertSame(1, $total);
    }

    public function test_this_month_includes_first_second_and_excludes_last_second_of_previous_month(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->makeApplicant(['created_at' => '2026-11-01 00:00:00']);
        $this->makeApplicant(['created_at' => '2026-10-31 23:59:59']);

        $this->assertSame(1, Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData')['totals']['thisMonth']);
    }

    /**
     * KNOWN BUG (ANA-2): thisMonth uses whereMonth() without a year, so a
     * registration from the same month last year is counted as "this month".
     *
     * HOW TO REPRODUCE: the clock is 2026-11-17. Register an applicant on
     * 2025-11-10. BUG: the card shows 1. Expected: 0.
     *
     * FIX: use whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]).
     */
    public function test_this_month_ignores_same_month_from_previous_year(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->makeApplicant(['created_at' => '2025-11-10 10:00:00']);

        $this->assertSame(0, Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData')['totals']['thisMonth']);
    }

  public function test_sex_chart_adds_up_to_total_for_active_applicants(): void
{
    $this->makeApplicant(['sex' => 'Male']);
    $this->makeApplicant(['sex' => 'Female']);
    $this->makeApplicant(['sex' => 'Female']);

    $data = Livewire::actingAs($this->staff)
        ->test(WorkforceAnalyticsDashboard::class)
        ->get('chartData');

    $this->assertSame(3, $data['totals']['total']);
    $this->assertSame(3, array_sum($data['sex']['data']->toArray()));
}

   public function test_civil_status_chart_adds_up_to_total_for_active_applicants(): void
{
    $this->makeApplicant(['civil_status' => 'Single']);
    $this->makeApplicant(['civil_status' => 'Married']);

    $data = Livewire::actingAs($this->staff)
        ->test(WorkforceAnalyticsDashboard::class)
        ->get('chartData');

    $this->assertSame(2, array_sum($data['civilStatus']['data']->toArray()));
}

    public function test_age_bracket_boundary_on_eighteenth_birthday(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->makeApplicant(['birthdate' => '2008-11-17']); // turns 18 today
        $this->makeApplicant(['birthdate' => '2008-11-18']); // turns 18 tomorrow

        $ages = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData')['ageGroups'];

        $this->assertSame(1, $ages['data'][array_search('18–24', $ages['labels'])]);
        $this->assertSame(1, $ages['data'][array_search('Under 18', $ages['labels'])]);
    }

 /** birthdate is NOT NULL in the schema, so a missing birthdate cannot be stored. */
public function test_birthdate_is_required_in_the_database(): void
{
    $this->expectException(\Illuminate\Database\QueryException::class);

    $this->makeApplicant(['birthdate' => null]);
}

    public function test_sex_chart_respects_barangay_and_sex_filters_together(): void
    {
        $rawis = Barangay::factory()->create(['municipality_id' => $this->municipality->id, 'name' => 'Rawis']);

        $this->makeApplicant(['sex' => 'Female', 'barangay_id' => $this->barangay->id]);
        $this->makeApplicant(['sex' => 'Female', 'barangay_id' => $rawis->id]);
        $this->makeApplicant(['sex' => 'Male',   'barangay_id' => $this->barangay->id]);

        $data = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->set('filterBarangay', (string) $this->barangay->id)
            ->set('filterSex', 'Female')
            ->get('chartData');

        $this->assertSame(1, $data['totals']['total']);
        $this->assertSame(1, array_sum($data['sex']['data']->toArray()));
    }

    /** The barangay chart ignores filters on purpose. */
    public function test_barangay_chart_ignores_filters_by_design(): void
    {
        $this->makeApplicant(['sex' => 'Male']);
        $this->makeApplicant(['sex' => 'Female']);

        $data = Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->set('filterSex', 'Female')
            ->get('chartData');

        $this->assertSame(2, array_sum($data['barangay']['data']->toArray()));
    }

    public function test_education_chart_excludes_inactive_applicants(): void
    {
        $inactive = $this->makeApplicant(['is_active' => false]);
        Education::factory()->create(['applicant_id' => $inactive->id, 'highest_level' => 'College Graduate']);

        $this->assertSame([], Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData')['education']['labels']->toArray());
    }

  public function test_barangay_chart_is_capped_at_twenty(): void
{
    $municipality = Municipality::factory()->create();

    for ($i = 1; $i <= 22; $i++) {
        $barangayId = DB::table('barangays')->insertGetId([
            'name'            => "Brgy $i",
            'municipality_id' => $municipality->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->makeApplicant(['barangay_id' => $barangayId]);
    }

    $this->assertCount(20, Livewire::actingAs($this->staff)
        ->test(WorkforceAnalyticsDashboard::class)
        ->get('chartData')['barangay']['labels']);
}

    public function test_skills_chart_is_capped_at_ten(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->makeApplicant()->skills()->attach(
                $this->makeSkill('Cat', "Skill $i")->id,
                ['proficiency_level' => 'Beginner']
            );
        }

        $this->assertCount(10, Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->get('chartData')['skills']['labels']);
    }

    public function test_apply_filters_dispatches_refresh_event(): void
    {
        Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->call('applyFilters')
            ->assertDispatched('refresh-charts');
    }

    public function test_clear_filters_resets_every_filter_and_dispatches_refresh(): void
    {
        Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->set('filterCategory', '1')
            ->set('filterBarangay', '1')
            ->set('filterEdLevel', 'College Graduate')
            ->set('filterSex', 'Female')
            ->set('filterFrom', '2026-01-01')
            ->set('filterTo', '2026-06-30')
            ->call('clearFilters')
            ->assertSet('filterCategory', '')
            ->assertSet('filterBarangay', '')
            ->assertSet('filterEdLevel', '')
            ->assertSet('filterSex', '')
            ->assertSet('filterFrom', '')
            ->assertSet('filterTo', '')
            ->assertDispatched('refresh-charts');
    }

    /**
     * KNOWN BUG (ANA-3): filterFrom and filterTo are not validated. A malformed
     * date makes Carbon::parse() throw inside the trend chart, and the page fails.
     *
     * HOW TO REPRODUCE: set filterFrom to "not-a-date". BUG: exception.
     * Expected: the value is ignored or rejected, and the page still renders.
     *
     * FIX: validate filterFrom and filterTo as nullable dates, or ignore values
     * that fail Carbon::canBeCreatedFromFormat('Y-m-d').
     */
    public function test_malformed_date_filter_does_not_break_the_page(): void
    {
        Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->set('filterFrom', 'not-a-date')
            ->assertOk();
    }

    // ═════════════════════════════════════════════════════════════════
    // PERMISSIONS: ANALYTICS
    // ═════════════════════════════════════════════════════════════════

    public function test_admin_can_open_analytics_page(): void
    {
        $this->actingAs($this->admin)->get(route('analytics'))->assertOk();
    }

    public function test_user_without_role_cannot_open_analytics_page(): void
    {
        $this->actingAs($this->outsider)->get(route('analytics'))->assertForbidden();
    }

    public function test_guest_is_redirected_from_analytics_page(): void
    {
        $this->get(route('analytics'))->assertRedirect(route('login'));
    }

    public function test_applicant_account_cannot_open_analytics_page(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant, 'applicant')->get(route('analytics'))->assertForbidden();
    }

    /**
     * KNOWN GAP (ANA-1): WorkforceAnalyticsDashboard has no role check in
     * render(). The route is the only protection.
     *
     * HOW TO REPRODUCE: call render() on the component as a user with no role.
     * BUG: the view is returned. Expected: 403.
     *
     * FIX: add abort_if(!auth()->user()->hasRole(['admin', 'staff']), 403); at the top of render().
     */
public function test_user_without_role_cannot_render_analytics_component(): void
{
    $this->actingAs($this->outsider);

    $this->assertTrue(
        $this->refusedWith403(fn () => (new WorkforceAnalyticsDashboard())->render()),
        'User without a role: analytics render() should be refused with 403.'
    );
}

    /** Filter buttons redraw charts and must not write anything. */
    public function test_analytics_actions_write_nothing_to_the_database(): void
    {
        $this->makeApplicant();
        $before = Applicant::count();

        Livewire::actingAs($this->staff)
            ->test(WorkforceAnalyticsDashboard::class)
            ->call('applyFilters')
            ->call('clearFilters');

        $this->assertSame($before, Applicant::count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** Returns true only when the callable throws an HttpException with status 403. */
    private function refusedWith403(callable $action): bool
    {
        try {
            $action();
            return false;
        } catch (HttpException $e) {
            return $e->getStatusCode() === 403;
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// SKILLS GAP ANALYSIS TESTS
// ─────────────────────────────────────────────────────────────────────

class SkillsGapAnalysisTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $municipality   = Municipality::factory()->create();
        $this->barangay = Barangay::factory()->create(['municipality_id' => $municipality->id]);
    }

    private function makeApplicant(): Applicant
    {
        return Applicant::factory()->create([
            'barangay_id' => $this->barangay->id,
            'is_active'   => true,
        ]);
    }

    private function makeSkill(string $name, string $category): Skill
    {
        $cat = SkillCategory::firstOrCreate(['name' => $category]);

        return Skill::firstOrCreate(['name' => $name, 'skill_category_id' => $cat->id]);
    }

    private function attachApplicants(Skill $skill, int $count, bool $active = true): void
    {
        for ($i = 0; $i < $count; $i++) {
            $a = Applicant::factory()->create([
                'barangay_id' => $this->barangay->id,
                'is_active'   => $active,
            ]);
            $a->skills()->attach($skill->id, ['proficiency_level' => 'Beginner']);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // GAP vs ADEQUATE SPLIT
    // ─────────────────────────────────────────────────────────────────

    /**
     * Skills below threshold appear in the gap panel. Skills at or above
     * threshold appear in the adequate panel. The boundary (count == threshold)
     * must be ADEQUATE, not GAP.
     */
    public function test_gap_and_adequate_split_at_threshold_boundary(): void
    {
        $gapSkill      = $this->makeSkill('Tour Guiding', 'Tourism & Hospitality');
        $borderSkill   = $this->makeSkill('Bartending',   'Tourism & Hospitality');
        $adequateSkill = $this->makeSkill('Rice Farming', 'Agricultural & Fisheries');

        $this->attachApplicants($gapSkill, 3);
        $this->attachApplicants($borderSkill, 10);
        $this->attachApplicants($adequateSkill, 25);

        $component = Livewire::actingAs($this->staff)
            ->test(SkillsGapAnalysis::class)
            ->set('threshold', 10);

        $gapNames      = $component->get('gapSkills')->pluck('skill')->toArray();
        $adequateNames = $component->get('surplusSkills')->pluck('skill')->toArray();

        $this->assertContains('Tour Guiding', $gapNames,
            'Tour Guiding with 3 registrants must be in the gap panel (below threshold=10).'
        );
        $this->assertNotContains('Bartending', $gapNames,
            'Bartending with EXACTLY 10 registrants must NOT be in gap.'
        );
        $this->assertContains('Bartending', $adequateNames,
            'Bartending with exactly 10 registrants must be in the adequate panel.'
        );
        $this->assertContains('Rice Farming', $adequateNames,
            'Rice Farming with 25 registrants must be in the adequate panel.'
        );
    }

    /**
     * Adjusting the threshold must recalculate both panels.
     */
    public function test_threshold_change_recalculates_panels(): void
    {
        $skill = $this->makeSkill('Web Development', 'ICT & Digital Technology');
        $this->attachApplicants($skill, 15, active: true);

        $component = Livewire::actingAs($this->staff)->test(SkillsGapAnalysis::class);

        $component->set('threshold', 10);
        $this->assertContains('Web Development', $component->get('surplusSkills')->pluck('skill')->toArray(),
            'With threshold=10, Web Development (15) must be ADEQUATE.'
        );

        $component->set('threshold', 20);
        $this->assertContains('Web Development', $component->get('gapSkills')->pluck('skill')->toArray(),
            'With threshold=20, Web Development (15) must become a GAP.'
        );
    }

    /**
     * Inactive applicants must NOT count toward skill totals.
     */
    public function test_inactive_applicants_not_counted_in_skill_totals(): void
    {
        $skill = $this->makeSkill('Caregiving', 'Health & Social Services');
        $this->attachApplicants($skill, 8, active: true);
        $this->attachApplicants($skill, 5, active: false);

        $gapNames = Livewire::actingAs($this->staff)
            ->test(SkillsGapAnalysis::class)
            ->set('threshold', 10)
            ->get('gapSkills')->pluck('skill')->toArray();

        $this->assertContains('Caregiving', $gapNames,
            'With only 8 active applicants, Caregiving must be a GAP (threshold=10).'
        );
    }

    public function test_skills_gap_page_accessible_to_staff(): void
    {
        $this->actingAs($this->staff);
        $this->get(route('skills-gap'))->assertStatus(200);
    }

    // ═════════════════════════════════════════════════════════════════
    // EDGE CASES: SKILLS GAP
    // ═════════════════════════════════════════════════════════════════

    public function test_empty_database_gives_empty_gap_and_surplus_panels(): void
    {
        $component = Livewire::actingAs($this->staff)
            ->test(SkillsGapAnalysis::class)
            ->set('threshold', 10);

        $this->assertCount(0, $component->get('gapSkills'));
        $this->assertCount(0, $component->get('surplusSkills'));
    }

    public function test_threshold_of_zero_puts_no_skill_in_the_gap_panel(): void
    {
        $this->attachApplicants($this->makeSkill('Baking', 'Food Service'), 1);

        $gapNames = Livewire::actingAs($this->staff)
            ->test(SkillsGapAnalysis::class)
            ->set('threshold', 0)
            ->get('gapSkills')->pluck('skill')->toArray();

        $this->assertNotContains('Baking', $gapNames);
    }

    public function test_threshold_above_every_count_puts_all_skills_in_gap(): void
    {
        $this->attachApplicants($this->makeSkill('Baking', 'Food Service'), 3);
        $this->attachApplicants($this->makeSkill('Carpentry', 'Construction'), 5);

        $component = Livewire::actingAs($this->staff)
            ->test(SkillsGapAnalysis::class)
            ->set('threshold', 100);

        $gapNames = $component->get('gapSkills')->pluck('skill')->toArray();

        $this->assertCount(0, $component->get('surplusSkills'));
        $this->assertContains('Baking', $gapNames);
        $this->assertContains('Carpentry', $gapNames);
    }

    public function test_skill_held_only_by_inactive_applicants_is_not_adequate(): void
    {
        $this->attachApplicants($this->makeSkill('Welding', 'Construction'), 5, active: false);

        $surplus = Livewire::actingAs($this->staff)
            ->test(SkillsGapAnalysis::class)
            ->set('threshold', 1)
            ->get('surplusSkills')->pluck('skill')->toArray();

        $this->assertNotContains('Welding', $surplus);
    }

    // ═════════════════════════════════════════════════════════════════
    // PERMISSIONS: SKILLS GAP
    // ═════════════════════════════════════════════════════════════════

    public function test_admin_can_open_skills_gap_page(): void
    {
        $this->actingAs($this->admin)->get(route('skills-gap'))->assertOk();
    }

    public function test_user_without_role_cannot_open_skills_gap_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('skills-gap'))->assertForbidden();
    }

    public function test_guest_is_redirected_from_skills_gap_page(): void
    {
        $this->get(route('skills-gap'))->assertRedirect(route('login'));
    }

    /**
     * KNOWN GAP (SGA-1): SkillsGapAnalysis has no role check in render() as far as
     * the code reviewed shows. If it already has one, this test passes and SGA-1 can be closed.
     *
     * FIX: add abort_if(!auth()->user()->hasRole(['admin', 'staff']), 403); at the top of render().
     */
    public function test_user_without_role_cannot_render_skills_gap_component(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillsGapAnalysis())->render()),
            'User without a role: skills gap render() should be refused with 403.'
        );
    }

    private function refusedWith403(callable $action): bool
    {
        try {
            $action();
            return false;
        } catch (HttpException $e) {
            return $e->getStatusCode() === 403;
        }
    }
}