<?php

namespace Tests\Feature;

use App\Livewire\SkillsGapAnalysis;
use App\Models\{Applicant, Barangay, Municipality, Skill, SkillCategory, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Skills gap analysis: threshold rules, edge cases, and role access.
 *
 * WHY: this page decides which skills PESO asks TESDA to train for. A skill
 * at the threshold boundary wrongly listed as a gap means unnecessary
 * training spend. A skill missing from the gap panel means a real shortage
 * goes unaddressed.
 *
 * RULES UNDER TEST
 * ────────────────
 * - Gap:      active applicant count < threshold
 * - Adequate: active applicant count >= threshold
 * - Inactive applicants are never counted.
 * - Rows are ordered by applicant count, highest first.
 *
 * KNOWN BUG / KNOWN GAP tests are expected to FAIL until the app code is fixed.
 * Do not weaken their assertions.
 *
 * Values are read with viewData(), because gapSkills, surplusSkills and
 * categoryTotals are passed to the view from render(), not stored as properties.
 */
class EdgeSkillsGapAnalysisTest extends TestCase
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

    private function page()
    {
        return Livewire::actingAs($this->staff)->test(SkillsGapAnalysis::class);
    }

    private function makeSkill(string $name, string $category): Skill
    {
        $cat = SkillCategory::firstOrCreate(['name' => $category]);

        return Skill::firstOrCreate(['name' => $name, 'skill_category_id' => $cat->id]);
    }

    private function attachApplicants(Skill $skill, int $count, bool $active = true): void
    {
        for ($i = 0; $i < $count; $i++) {
            $applicant = Applicant::factory()->create([
                'barangay_id' => $this->barangay->id,
                'is_active'   => $active,
            ]);
            $applicant->skills()->attach($skill->id, ['proficiency_level' => 'Beginner']);
        }
    }

    private function gapNames($component): array
    {
        return $component->viewData('gapSkills')->pluck('skill')->toArray();
    }

    private function surplusNames($component): array
    {
        return $component->viewData('surplusSkills')->pluck('skill')->toArray();
    }

    /** True only when the callable throws an HttpException with status 403. */
    private function refusedWith403(callable $action): bool
    {
        try {
            $action();
            return false;
        } catch (HttpException $e) {
            return $e->getStatusCode() === 403;
        }
    }

    // ── GAP VS ADEQUATE SPLIT ────────────────────────────────────────

    /** Count exactly equal to the threshold is ADEQUATE, not GAP. */
    public function test_gap_and_adequate_split_at_threshold_boundary(): void
    {
        $this->attachApplicants($this->makeSkill('Tour Guiding', 'Tourism'), 3);
        $this->attachApplicants($this->makeSkill('Bartending', 'Tourism'), 10);
        $this->attachApplicants($this->makeSkill('Rice Farming', 'Agriculture'), 25);

        $component = $this->page()->set('threshold', 10);

        $this->assertContains('Tour Guiding', $this->gapNames($component));
        $this->assertNotContains('Bartending', $this->gapNames($component));
        $this->assertContains('Bartending', $this->surplusNames($component));
        $this->assertContains('Rice Farming', $this->surplusNames($component));
    }

    /** One below the threshold is a GAP. */
    public function test_one_below_threshold_is_a_gap(): void
    {
        $this->attachApplicants($this->makeSkill('Welding', 'Construction'), 9);

        $this->assertContains('Welding', $this->gapNames($this->page()->set('threshold', 10)));
    }

    /** Changing the threshold recalculates both panels. */
    public function test_threshold_change_recalculates_panels(): void
    {
        $this->attachApplicants($this->makeSkill('Web Development', 'ICT'), 15);

        $component = $this->page();

        $component->set('threshold', 10);
        $this->assertContains('Web Development', $this->surplusNames($component));

        $component->set('threshold', 20);
        $this->assertContains('Web Development', $this->gapNames($component));
        $this->assertNotContains('Web Development', $this->surplusNames($component));
    }

    /** Inactive applicants do not count toward the skill total. */
    public function test_inactive_applicants_are_not_counted(): void
    {
        $skill = $this->makeSkill('Caregiving', 'Health');
        $this->attachApplicants($skill, 8, active: true);
        $this->attachApplicants($skill, 5, active: false);

        $this->assertContains('Caregiving', $this->gapNames($this->page()->set('threshold', 10)));
    }

    /** A skill held only by inactive applicants is not adequate. */
    public function test_skill_held_only_by_inactive_applicants_is_not_adequate(): void
    {
        $this->attachApplicants($this->makeSkill('Welding', 'Construction'), 5, active: false);

        $this->assertNotContains('Welding', $this->surplusNames($this->page()->set('threshold', 1)));
    }

    // ── EDGE CASES ───────────────────────────────────────────────────

    public function test_empty_database_gives_empty_panels(): void
    {
        $component = $this->page()->set('threshold', 10);

        $this->assertCount(0, $component->viewData('gapSkills'));
        $this->assertCount(0, $component->viewData('surplusSkills'));
    }

    /** With threshold 0, a skill with 1 applicant is adequate, so nothing is a gap. */
    public function test_threshold_of_zero_puts_no_skill_in_the_gap_panel(): void
    {
        $this->attachApplicants($this->makeSkill('Baking', 'Food Service'), 1);

        $this->assertNotContains('Baking', $this->gapNames($this->page()->set('threshold', 0)));
    }

    /** A threshold above every count puts every skill in the gap panel. */
    public function test_threshold_above_every_count_puts_all_skills_in_gap(): void
    {
        $this->attachApplicants($this->makeSkill('Baking', 'Food Service'), 3);
        $this->attachApplicants($this->makeSkill('Carpentry', 'Construction'), 5);

        $component = $this->page()->set('threshold', 100);

        $this->assertCount(0, $component->viewData('surplusSkills'));
        $this->assertContains('Baking', $this->gapNames($component));
        $this->assertContains('Carpentry', $this->gapNames($component));
    }

    /** Rows are ordered by applicant count, highest first. */
    public function test_gap_rows_are_ordered_by_count_descending(): void
    {
        $this->attachApplicants($this->makeSkill('Alpha', 'Cat'), 1);
        $this->attachApplicants($this->makeSkill('Beta', 'Cat'), 3);
        $this->attachApplicants($this->makeSkill('Gamma', 'Cat'), 2);

        $this->assertSame(
            ['Beta', 'Gamma', 'Alpha'],
            $this->gapNames($this->page()->set('threshold', 10))
        );
    }

    /** Two skills with the same name in different categories are separate rows. */
    public function test_same_skill_name_in_two_categories_gives_two_rows(): void
    {
        $this->attachApplicants($this->makeSkill('First Aid', 'Health'), 2);
        $this->attachApplicants($this->makeSkill('First Aid', 'Safety'), 2);

        $component = $this->page()->set('threshold', 3);

        $this->assertCount(2, $component->viewData('gapSkills')->where('skill', 'First Aid'));
    }

    /** The category column shows the category each skill belongs to. */
    public function test_gap_rows_show_their_category(): void
    {
        $this->attachApplicants($this->makeSkill('Sewing', 'Garments'), 2);

        $row = $this->page()->set('threshold', 10)->viewData('gapSkills')->firstWhere('skill', 'Sewing');

        $this->assertSame('Garments', $row->category);
        $this->assertSame(2, (int) $row->applicant_count);
    }

    /** Category totals list each category with its registration count. */
    public function test_category_totals_count_registrations_per_category(): void
    {
        $this->attachApplicants($this->makeSkill('Sewing', 'Garments'), 2);
        $this->attachApplicants($this->makeSkill('Weaving', 'Garments'), 3);

        $row = $this->page()->viewData('categoryTotals')->firstWhere('name', 'Garments');

        $this->assertSame(5, (int) $row->total);
    }

    /**
     * KNOWN BUG (SGA-2): threshold is not validated. A negative value is
     * accepted, and the view shows "Below -5 Registrants".
     *
     * HOW TO REPRODUCE: set the threshold to -5. BUG: it stays -5.
     * Expected: the value is clamped to a minimum of 1.
     *
     * FIX: add public function updatedThreshold() that sets threshold to max(1, (int) $value).
     */
    public function test_negative_threshold_is_clamped_to_one(): void
    {
        $this->page()->set('threshold', -5)->assertSet('threshold', 1);
    }

    /**
     * KNOWN BUG (SGA-3): categoryTotals does not filter by is_active. Inactive
     * applicants inflate the category totals, while the skill panels exclude them.
     *
     * HOW TO REPRODUCE: one active and one inactive applicant with a skill in
     * category "Garments". BUG: the total is 2. Expected: 1.
     *
     * FIX: join applicants on applicant_skill.applicant_id and add where('applicants.is_active', true).
     */
    public function test_category_totals_exclude_inactive_applicants(): void
    {
        $skill = $this->makeSkill('Sewing', 'Garments');
        $this->attachApplicants($skill, 1, active: true);
        $this->attachApplicants($skill, 1, active: false);

        $row = $this->page()->viewData('categoryTotals')->firstWhere('name', 'Garments');

        $this->assertSame(1, (int) $row->total);
    }

    /**
     * KNOWN GAP (SGA-4): a skill that no active applicant has is missing from
     * both panels, because the ranking query only returns skills with at least
     * one applicant. The most serious shortage is hidden.
     *
     * HOW TO REPRODUCE: create a skill with no applicants, threshold 1.
     * BUG: it does not appear in the gap panel. Expected: it appears there.
     *
     * FIX: start the query from the skills table with a LEFT JOIN to
     * applicant_skill and applicants, and count with COUNT(applicants.id)
     * so skills with zero applicants get a count of 0.
     */
    public function test_skill_with_no_applicants_appears_in_gap_panel(): void
    {
        $this->makeSkill('Pottery', 'Crafts');

        $this->assertContains('Pottery', $this->gapNames($this->page()->set('threshold', 1)));
    }

    // ── PERMISSIONS: ROUTE ───────────────────────────────────────────

    public function test_admin_can_open_skills_gap_page(): void
    {
        $this->actingAs($this->admin)->get(route('skills-gap'))->assertOk();
    }

    public function test_staff_can_open_skills_gap_page(): void
    {
        $this->actingAs($this->staff)->get(route('skills-gap'))->assertOk();
    }

    public function test_user_without_role_cannot_open_skills_gap_page(): void
    {
        $this->actingAs($this->outsider)->get(route('skills-gap'))->assertForbidden();
    }

    public function test_guest_is_redirected_from_skills_gap_page(): void
    {
        $this->get(route('skills-gap'))->assertRedirect(route('login'));
    }

    /**
     * ASSUMPTION: applicants get a 403 from the role:admin|staff route group.
     * If they are redirected to the applicant login, change the assertion.
     */
    public function test_applicant_account_cannot_open_skills_gap_page(): void
    {
        $applicant = Applicant::factory()->create(['barangay_id' => $this->barangay->id]);

        $this->actingAs($applicant, 'applicant')->get(route('skills-gap'))->assertForbidden();
    }

    // ── PERMISSIONS: COMPONENT ───────────────────────────────────────

    public function test_staff_can_mount_skills_gap_component(): void
    {
        Livewire::actingAs($this->staff)->test(SkillsGapAnalysis::class)->assertOk();
    }

    public function test_admin_can_mount_skills_gap_component(): void
    {
        Livewire::actingAs($this->admin)->test(SkillsGapAnalysis::class)->assertOk();
    }

    /**
     * KNOWN GAP (SGA-1): SkillsGapAnalysis::render() has no role check. The
     * route is the only protection, so any path that mounts the component
     * would show skill counts to a user with no role.
     *
     * HOW TO REPRODUCE: call render() as a user with no role.
     * BUG: the view is returned. Expected: 403.
     *
     * FIX: add abort_if(!auth()->user()->hasRole(['admin', 'staff']), 403); as the first line of render().
     */
    public function test_user_without_role_cannot_render_skills_gap_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillsGapAnalysis())->render()),
            'User without a role: skills gap render() should be refused with 403.'
        );
    }
}