<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * Analytics is why PESO Connect was built. If the charts return
 * wrong numbers, PESO makes wrong decisions: recommends wrong training
 * programs, reports wrong numbers to DOLE, misidentifies skill gaps.
 *
 * TESTING MINDSET:
 * I seed KNOWN data and assert EXPECTED outputs.
 * This is called "fixture-based testing" — the test controls the data
 * so the expected results are mathematically certain.
 *
 * For example: if I create exactly 3 applicants with "Rice Farming"
 * and 7 with "Web Development", I can assert:
 * - Rice Farming count = 3 in the skills chart
 * - Web Development count = 7
 * - With threshold=5: Rice Farming is a GAP, Web Development is ADEQUATE
 *
 * Any deviation from these exact numbers means the query is wrong.
 *
 * WHY NOT JUST TEST THE QUERY? Because Eloquent queries interact
 * with scopes, joins, and filters in complex ways. The only reliable
 * test is end-to-end: seed data → run component → assert output.
 */
class WorkforceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;
    private Municipality $municipality;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        $role              = Role::factory()->create(['slug' => 'staff']);
        $this->staffUser   = User::factory()->create(['role_id' => $role->id]);
        $this->municipality = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay     = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
            'name'            => 'San Jose',
        ]);
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
        $this->actingAs($this->staffUser);
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
     * report inflated workforce numbers to DOLE. This is the most
     * fundamental accuracy requirement of the analytics system.
     */
    public function test_total_count_reflects_only_active_applicants(): void
    {
        // Create 5 active and 3 inactive
        $this->makeApplicant(['is_active' => true]);
        $this->makeApplicant(['is_active' => true]);
        $this->makeApplicant(['is_active' => true]);
        $this->makeApplicant(['is_active' => true]);
        $this->makeApplicant(['is_active' => true]);
        $this->makeApplicant(['is_active' => false]);
        $this->makeApplicant(['is_active' => false]);
        $this->makeApplicant(['is_active' => false]);

        $component = Livewire::actingAs($this->staffUser)
            ->test(WorkforceAnalyticsDashboard::class);

        $chartData = $component->get('chartData');

        $this->assertEquals(5, $chartData['totals']['total'],
            'Total count must be 5 (active only). The 3 inactive must not be counted.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SKILL CHART ACCURACY
    // ─────────────────────────────────────────────────────────────────

    /**
     * The skills chart data must accurately count applicants per skill.
     *
     * WHY: The skills chart is used to identify the most prevalent
     * workforce skills in Catanduanes. If counts are wrong, PESO
     * might recommend training in a skill that is already abundant
     * and ignore a skill that is critically scarce.
     */
    public function test_skills_chart_counts_applicants_per_skill_correctly(): void
    {
        $skill1 = $this->makeSkill('ICT & Digital Technology', 'Web Development');
        $skill2 = $this->makeSkill('Agricultural & Fisheries', 'Rice Farming');

        // Attach skill1 to 7 applicants, skill2 to 3
        for ($i = 0; $i < 7; $i++) {
            $a = $this->makeApplicant();
            $a->skills()->attach($skill1->id, ['proficiency_level' => 'Beginner']);
        }
        for ($i = 0; $i < 3; $i++) {
            $a = $this->makeApplicant();
            $a->skills()->attach($skill2->id, ['proficiency_level' => 'Beginner']);
        }

        $component = Livewire::actingAs($this->staffUser)
            ->test(WorkforceAnalyticsDashboard::class);

        $chartData = $component->get('chartData');

        // Find Web Development in the skills chart data
        $labels = $chartData['skills']['labels']->toArray();
        $data   = $chartData['skills']['data']->toArray();

        $webDevIndex   = array_search('Web Development', $labels);
        $riceFarmIndex = array_search('Rice Farming',    $labels);

        $this->assertNotFalse($webDevIndex,   'Web Development must appear in skills chart.');
        $this->assertNotFalse($riceFarmIndex, 'Rice Farming must appear in skills chart.');

        $this->assertEquals(7, $data[$webDevIndex],
            'Web Development must have count of 7.'
        );
        $this->assertEquals(3, $data[$riceFarmIndex],
            'Rice Farming must have count of 3.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // EDUCATION CHART ACCURACY
    // ─────────────────────────────────────────────────────────────────

    /**
     * Education chart must correctly count applicants per education level.
     *
     * WHY: The education distribution is used for workforce planning
     * and for DOLE reporting. Incorrect counts would misrepresent
     * the province's education profile.
     */
    public function test_education_chart_counts_correctly(): void
    {
        // Create applicants with specific education levels
        for ($i = 0; $i < 4; $i++) {
            $a = $this->makeApplicant();
            Education::factory()->create([
                'applicant_id'  => $a->id,
                'highest_level' => 'College Graduate',
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            $a = $this->makeApplicant();
            Education::factory()->create([
                'applicant_id'  => $a->id,
                'highest_level' => 'Vocational/Technical',
            ]);
        }

        $component = Livewire::actingAs($this->staffUser)
            ->test(WorkforceAnalyticsDashboard::class);

        $chartData = $component->get('chartData');
        $labels    = $chartData['education']['labels']->toArray();
        $data      = $chartData['education']['data']->toArray();

        $cgIdx  = array_search('College Graduate',   $labels);
        $vocIdx = array_search('Vocational/Technical', $labels);

        $this->assertEquals(4, $data[$cgIdx],  'College Graduate must count 4.');
        $this->assertEquals(2, $data[$vocIdx], 'Vocational/Technical must count 2.');
    }

    // ─────────────────────────────────────────────────────────────────
    // FILTER: INACTIVE EXCLUDED FROM ALL QUERIES
    // ─────────────────────────────────────────────────────────────────

    /**
     * All chart queries must exclude inactive applicants.
     *
     * WHY: The base query uses Applicant::active() scope.
     * This test verifies that scope propagates through the joinSub()
     * queries used for each chart — not just the count query.
     */
    public function test_all_chart_queries_exclude_inactive_applicants(): void
    {
        $skill = $this->makeSkill('ICT & Digital Technology', 'Data Encoding');

        // Active applicant with skills
        $activeApplicant = $this->makeApplicant(['is_active' => true]);
        $activeApplicant->skills()->attach($skill->id, ['proficiency_level' => 'Beginner']);

        // Inactive applicant with same skill
        $inactiveApplicant = $this->makeApplicant(['is_active' => false]);
        $inactiveApplicant->skills()->attach($skill->id, ['proficiency_level' => 'Beginner']);

        $component = Livewire::actingAs($this->staffUser)
            ->test(WorkforceAnalyticsDashboard::class);

        $chartData = $component->get('chartData');
        $labels    = $chartData['skills']['labels']->toArray();
        $data      = $chartData['skills']['data']->toArray();

        $idx = array_search('Data Encoding', $labels);
        $this->assertNotFalse($idx);

        $this->assertEquals(1, $data[$idx],
            'Skill count must be 1 (only active applicant). Inactive must not be counted.'
        );
    }
}

// ════════════════════════════════════════════════════════════════════════
// SKILLS GAP ANALYSIS TESTS
// ════════════════════════════════════════════════════════════════════════

class SkillsGapAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;
    private Municipality $municipality;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        $role              = Role::factory()->create(['slug' => 'staff']);
        $this->staffUser   = User::factory()->create(['role_id' => $role->id]);
        $this->municipality = Municipality::factory()->create();
        $this->barangay     = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
        ]);
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

    // ─────────────────────────────────────────────────────────────────
    // GAP vs ADEQUATE SPLIT
    // ─────────────────────────────────────────────────────────────────

    /**
     * Skills below threshold appear in gap panel.
     * Skills at or above threshold appear in adequate panel.
     *
     * WHY: This is the core function of the entire module.
     * If the split is wrong at the threshold boundary (e.g., uses >
     * instead of >= for adequate), a skill with EXACTLY 10 registrants
     * (at threshold=10) would appear in gap instead of adequate —
     * causing PESO to request unnecessary TESDA training for a
     * skill that already meets the minimum requirement.
     *
     * The boundary condition (count == threshold) is the most
     * dangerous case and gets its own explicit test.
     */
    public function test_gap_and_adequate_split_at_threshold_boundary(): void
    {
        $gapSkill      = $this->makeSkill('Tour Guiding',   'Tourism & Hospitality');
        $borderSkill   = $this->makeSkill('Bartending',     'Tourism & Hospitality');
        $adequateSkill = $this->makeSkill('Rice Farming',   'Agricultural & Fisheries');

        // Tour Guiding: 3 applicants → GAP (below threshold=10)
        for ($i = 0; $i < 3; $i++) {
            $a = $this->makeApplicant();
            $a->skills()->attach($gapSkill->id, ['proficiency_level' => 'Beginner']);
        }

        // Bartending: EXACTLY 10 → must be ADEQUATE (at threshold, not below)
        for ($i = 0; $i < 10; $i++) {
            $a = $this->makeApplicant();
            $a->skills()->attach($borderSkill->id, ['proficiency_level' => 'Beginner']);
        }

        // Rice Farming: 25 applicants → ADEQUATE (well above threshold)
        for ($i = 0; $i < 25; $i++) {
            $a = $this->makeApplicant();
            $a->skills()->attach($adequateSkill->id, ['proficiency_level' => 'Beginner']);
        }

        $component = Livewire::actingAs($this->staffUser)
            ->test(SkillsGapAnalysis::class)
            ->set('threshold', 10); // default threshold

        $gapSkills      = $component->get('gapSkills');
        $surplusSkills  = $component->get('surplusSkills');

        $gapNames      = $gapSkills->pluck('skill')->toArray();
        $adequateNames = $surplusSkills->pluck('skill')->toArray();

        // Tour Guiding (3) → GAP
        $this->assertContains('Tour Guiding', $gapNames,
            'Tour Guiding with 3 registrants must be in the gap panel (below threshold=10).'
        );

        // Bartending (10) → ADEQUATE (NOT gap — >= threshold)
        $this->assertNotContains('Bartending', $gapNames,
            'Bartending with EXACTLY 10 registrants must NOT be in gap — threshold is inclusive on adequate side.'
        );
        $this->assertContains('Bartending', $adequateNames,
            'Bartending with exactly 10 registrants must be in the adequate panel.'
        );

        // Rice Farming (25) → ADEQUATE
        $this->assertContains('Rice Farming', $adequateNames,
            'Rice Farming with 25 registrants must be in the adequate panel.'
        );
    }

    /**
     * Adjusting the threshold must recalculate both panels.
     *
     * WHY: The threshold is adjustable by staff for a reason —
     * different skills have different demand levels.
     * If the threshold change does not recalculate the panels,
     * staff would see stale data after adjusting the slider.
     */
    public function test_threshold_change_recalculates_panels(): void
    {
        $skill = $this->makeSkill('Web Development', 'ICT & Digital Technology');

        // 15 applicants have this skill
        for ($i = 0; $i < 15; $i++) {
            $a = $this->makeApplicant();
            $a->skills()->attach($skill->id, ['proficiency_level' => 'Intermediate']);
        }

        $component = Livewire::actingAs($this->staffUser)
            ->test(SkillsGapAnalysis::class);

        // With threshold=10: 15 >= 10 → ADEQUATE
        $component->set('threshold', 10);
        $adequateAt10 = $component->get('surplusSkills')->pluck('skill')->toArray();
        $this->assertContains('Web Development', $adequateAt10,
            'With threshold=10, Web Development (15) must be ADEQUATE.'
        );

        // With threshold=20: 15 < 20 → GAP
        $component->set('threshold', 20);
        $gapAt20 = $component->get('gapSkills')->pluck('skill')->toArray();
        $this->assertContains('Web Development', $gapAt20,
            'With threshold=20, Web Development (15) must become a GAP.'
        );
    }

    /**
     * Inactive applicants must NOT count toward skill totals.
     *
     * WHY: If inactive applicants count, a deactivated duplicate
     * would still inflate the skill count. A skill might appear
     * adequate when in reality the active workforce has fewer people
     * with that skill than the threshold.
     */
    public function test_inactive_applicants_not_counted_in_skill_totals(): void
    {
        $skill = $this->makeSkill('Caregiving', 'Health & Social Services');

        // 8 active + 5 inactive — total visible is 8
        for ($i = 0; $i < 8; $i++) {
            $a = Applicant::factory()->create([
                'barangay_id' => $this->barangay->id,
                'is_active'   => true,
            ]);
            $a->skills()->attach($skill->id, ['proficiency_level' => 'Beginner']);
        }
        for ($i = 0; $i < 5; $i++) {
            $a = Applicant::factory()->create([
                'barangay_id' => $this->barangay->id,
                'is_active'   => false, // INACTIVE
            ]);
            $a->skills()->attach($skill->id, ['proficiency_level' => 'Beginner']);
        }

        $component = Livewire::actingAs($this->staffUser)
            ->test(SkillsGapAnalysis::class)
            ->set('threshold', 10); // 8 < 10 → should be gap

        $gapSkills = $component->get('gapSkills');
        $gapNames  = $gapSkills->pluck('skill')->toArray();

        $this->assertContains('Caregiving', $gapNames,
            'With only 8 active applicants, Caregiving must be a GAP (threshold=10). ' .
            'The 5 inactive applicants must not be counted.'
        );
    }

    /**
     * Skills page is accessible to staff.
     */
    public function test_skills_gap_page_accessible_to_staff(): void
    {
        $this->actingAs($this->staffUser);
        $this->get(route('skills-gap'))->assertStatus(200);
    }
}
