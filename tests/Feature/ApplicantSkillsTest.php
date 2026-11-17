<?php

namespace Tests\Feature;

use App\Livewire\Applicant\Skills;
use App\Models\Applicant;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Applicant "Skills" page: an applicant manages the skills on their own record.
 *
 * WHY: these rows feed the skills-gap analysis and what PESO staff see on an
 * applicant's record. An applicant must only ever read or change THEIR OWN rows,
 * must not exceed the skill limit, and must not hold the same skill twice.
 *
 * NOTE ON ASSUMPTIONS (change these to match your app)
 * ────────────────────────────────────────────────────
 *  - Route names: 'applicant.skills' (this page) and 'applicant.login' (guest redirect).
 *  - Applicants log in through the 'applicant' guard. Admin and staff use the 'web' guard.
 *  - applicant_skill has no id/timestamps requirement beyond the three columns
 *    the component inserts (applicant_id, skill_id, proficiency_level).
 *  - SetsUpAdminAndStaff provides $this->admin and $this->staff via setUpUsers().
 *
 * NOTE ON KNOWN BUGS AND GAPS
 * ───────────────────────────
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions.
 */
class ApplicantSkillsTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private SkillCategory $welding;
    private SkillCategory $cooking;
    private Applicant $applicant;
    private Applicant $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $this->welding = SkillCategory::create(['name' => 'Welding', 'pqf_level' => 'Level 3']);
        $this->cooking = SkillCategory::create(['name' => 'Cooking', 'pqf_level' => 'Level 2']);

        $this->applicant = Applicant::factory()->create();
        $this->other     = Applicant::factory()->create();
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function makeSkill(string $name, ?SkillCategory $category = null): Skill
    {
        return Skill::create(['name' => $name, 'skill_category_id' => ($category ?? $this->welding)->id]);
    }

    private function giveSkill(Applicant $applicant, Skill $skill, string $level = 'Intermediate'): void
    {
        DB::table('applicant_skill')->insert([
            'applicant_id'      => $applicant->id,
            'skill_id'          => $skill->id,
            'proficiency_level' => $level,
        ]);
    }

    private function holds(Applicant $applicant, Skill $skill): bool
    {
        return DB::table('applicant_skill')
            ->where('applicant_id', $applicant->id)
            ->where('skill_id', $skill->id)
            ->exists();
    }

    private function countFor(Applicant $applicant): int
    {
        return DB::table('applicant_skill')->where('applicant_id', $applicant->id)->count();
    }

    private function page(?Applicant $as = null)
    {
        return Livewire::actingAs($as ?? $this->applicant, 'applicant')->test(Skills::class);
    }

    /** Pick a category and a skill on the form, ready to call addSkill. */
    private function fill($component, Skill $skill, string $level = 'Beginner')
    {
        return $component
            ->set('category_id', (string) $skill->skill_category_id)
            ->set('skill_id', (string) $skill->id)
            ->set('proficiency', $level);
    }

    // ── ACCESS ───────────────────────────────────────────────────────

    public function test_applicant_can_open_page(): void
    {
        $this->actingAs($this->applicant, 'applicant')
            ->get(route('applicant.skills'))
            ->assertOk();
    }

    public function test_guest_is_redirected_to_applicant_login(): void
    {
        $this->get(route('applicant.skills'))->assertRedirect(route('applicant.login'));
    }

    public function test_admin_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('applicant.skills'))
            ->assertRedirect(route('applicant.login'));
    }

    public function test_staff_cannot_use_the_applicant_page(): void
    {
        $this->actingAs($this->staff)
            ->get(route('applicant.skills'))
            ->assertRedirect(route('applicant.login'));
    }

    /**
     * KNOWN GAP — expected to FAIL until the component checks the guard itself.
     *
     * The route blocks guests, so this only matters if the component is reached
     * another way. Today a guest gets a working component with a null applicant id.
     *
     * FIX: in mount(), abort_unless(Auth::guard('applicant')->check(), 403);
     */
    public function test_component_itself_rejects_a_guest(): void
    {
        Livewire::test(Skills::class)->assertForbidden();
    }

    /**
     * A guest (or an admin on the web guard) calling addSkill must never write a row.
     * Passes whether the app rejects the call or the database refuses the null applicant_id.
     */
    public function test_add_skill_without_applicant_login_writes_nothing(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        try {
            $this->fill(Livewire::actingAs($this->admin)->test(Skills::class), $skill)->call('addSkill');
        } catch (\Throwable $e) {
            // refusing is fine
        }

        $this->assertDatabaseCount('applicant_skill', 0);
    }

    // ── LIST ─────────────────────────────────────────────────────────

 public function test_shows_empty_state_when_applicant_has_no_skills(): void
{
    $this->page()
        ->assertSee("You haven't added any skills yet.", false)
        ->assertSee('My skills (0)');
}

    public function test_lists_own_skills_with_category_and_proficiency(): void
    {
        $this->giveSkill($this->applicant, $this->makeSkill('Arc Welding'), 'Advanced');

        $this->page()
            ->assertSee('Arc Welding')
            ->assertSee('Welding')
            ->assertSee('Advanced')
            ->assertSee('My skills (1)')
            ->assertDontSee("You haven't added any skills yet.");
    }

    public function test_does_not_list_another_applicants_skills(): void
    {
        $this->giveSkill($this->other, $this->makeSkill('Arc Welding'));
        $this->giveSkill($this->applicant, $this->makeSkill('Baking Bread', $this->cooking));

        $this->page()
            ->assertSee('Baking Bread')
            ->assertDontSee('Arc Welding')
            ->assertSee('My skills (1)');
    }

    public function test_skills_are_ordered_by_category_then_name(): void
    {
        $this->giveSkill($this->applicant, $this->makeSkill('Soldering', $this->welding));
        $this->giveSkill($this->applicant, $this->makeSkill('Arc Welding', $this->welding));
        $this->giveSkill($this->applicant, $this->makeSkill('Baking Bread', $this->cooking));

        // Cooking < Welding alphabetically, then by skill name inside a category
        $this->page()->assertSeeInOrder(['Baking Bread', 'Arc Welding', 'Soldering']);
    }

    public function test_two_applicants_can_hold_the_same_skill(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->applicant, $skill);
        $this->giveSkill($this->other, $skill);

        $this->page()->assertSee('My skills (1)');
        $this->page($this->other)->assertSee('My skills (1)');
    }

    public function test_category_dropdown_lists_all_categories_in_name_order(): void
    {
        SkillCategory::create(['name' => 'Carpentry']);

        $names = $this->page()->viewData('categories')->pluck('name')->all();

        $this->assertSame(['Carpentry', 'Cooking', 'Welding'], $names);
    }

    // ── SKILL DROPDOWN (available) ───────────────────────────────────

    public function test_skill_list_is_empty_until_a_category_is_chosen(): void
    {
        $this->makeSkill('Arc Welding');

        $this->assertCount(0, $this->page()->viewData('available'));
    }

    public function test_choosing_a_category_lists_only_that_categorys_skills_in_name_order(): void
    {
        $this->makeSkill('Soldering', $this->welding);
        $this->makeSkill('Arc Welding', $this->welding);
        $this->makeSkill('Baking Bread', $this->cooking);

        $names = $this->page()
            ->set('category_id', (string) $this->welding->id)
            ->viewData('available')
            ->pluck('name')
            ->all();

        $this->assertSame(['Arc Welding', 'Soldering'], $names);
    }

    public function test_skills_already_on_record_are_not_offered_again(): void
    {
        $held = $this->makeSkill('Arc Welding');
        $this->makeSkill('MIG Welding');
        $this->giveSkill($this->applicant, $held);

        $names = $this->page()
            ->set('category_id', (string) $this->welding->id)
            ->viewData('available')
            ->pluck('name')
            ->all();

        $this->assertSame(['MIG Welding'], $names);
    }

    public function test_skill_held_by_someone_else_is_still_offered(): void
    {
        $this->giveSkill($this->other, $this->makeSkill('Arc Welding'));

        $names = $this->page()
            ->set('category_id', (string) $this->welding->id)
            ->viewData('available')
            ->pluck('name')
            ->all();

        $this->assertSame(['Arc Welding'], $names);
    }

    public function test_unknown_category_id_shows_no_skills(): void
    {
        $this->makeSkill('Arc Welding');

        $this->assertCount(0, $this->page()->set('category_id', '99999')->viewData('available'));
    }

    public function test_non_numeric_category_id_does_not_crash(): void
    {
        $this->makeSkill('Arc Welding');

        $this->page()->set('category_id', 'abc')->assertOk();
    }

    public function test_changing_category_clears_the_chosen_skill(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->fill($this->page(), $skill)
            ->assertSet('skill_id', (string) $skill->id)
            ->set('category_id', (string) $this->cooking->id)
            ->assertSet('skill_id', '');
    }

    // ── ADD ──────────────────────────────────────────────────────────

    public function test_add_saves_the_skill_with_the_chosen_level(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->fill($this->page(), $skill, 'Expert')
            ->call('addSkill')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applicant_skill', [
            'applicant_id'      => $this->applicant->id,
            'skill_id'          => $skill->id,
            'proficiency_level' => 'Expert',
        ]);
    }

  public function test_add_flashes_a_success_message(): void
{
    $this->fill($this->page(), $this->makeSkill('Arc Welding'))
        ->call('addSkill')
        ->assertSee('Skill added.');
}

public function test_remove_flashes_a_message(): void
{
    $skill = $this->makeSkill('Arc Welding');
    $this->giveSkill($this->applicant, $skill);

    $this->page()
        ->call('removeSkill', $skill->id)
        ->assertSee('Skill removed.');
}
    public function test_add_resets_skill_and_proficiency_but_keeps_the_category(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->fill($this->page(), $skill, 'Expert')
            ->call('addSkill')
            ->assertSet('skill_id', '')
            ->assertSet('proficiency', 'Beginner')
            ->assertSet('category_id', (string) $this->welding->id);
    }

    public function test_added_skill_shows_up_in_the_list_and_leaves_the_dropdown(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $component = $this->fill($this->page(), $skill)->call('addSkill');

        $component->assertSee('My skills (1)');
        $this->assertCount(0, $component->viewData('available'));
    }

    public function test_add_only_touches_the_logged_in_applicants_record(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->fill($this->page(), $skill)->call('addSkill');

        $this->assertSame(1, $this->countFor($this->applicant));
        $this->assertSame(0, $this->countFor($this->other));
    }

    public function test_every_proficiency_level_is_accepted(): void
    {
        foreach (Skills::LEVELS as $i => $level) {
            $skill = $this->makeSkill("Skill $i");

            $this->fill($this->page(), $skill, $level)->call('addSkill')->assertHasNoErrors();

            $this->assertDatabaseHas('applicant_skill', [
                'applicant_id' => $this->applicant->id, 'skill_id' => $skill->id, 'proficiency_level' => $level,
            ]);
        }
    }

    public function test_default_proficiency_is_beginner(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->page()
            ->assertSet('proficiency', 'Beginner')
            ->set('category_id', (string) $this->welding->id)
            ->set('skill_id', (string) $skill->id)
            ->call('addSkill');

        $this->assertDatabaseHas('applicant_skill', ['skill_id' => $skill->id, 'proficiency_level' => 'Beginner']);
    }

    // ── ADD: validation ──────────────────────────────────────────────

    public function test_add_requires_a_skill(): void
    {
        $this->page()
            ->call('addSkill')
            ->assertHasErrors(['skill_id' => 'required'])
            ->assertSee('Please choose a skill.');

        $this->assertSame(0, $this->countFor($this->applicant));
    }

    public function test_add_with_category_but_no_skill_is_rejected(): void
    {
        $this->makeSkill('Arc Welding');

        $this->page()
            ->set('category_id', (string) $this->welding->id)
            ->call('addSkill')
            ->assertHasErrors(['skill_id' => 'required']);
    }

    public function test_add_rejects_unknown_skill(): void
    {
        $this->page()
            ->set('skill_id', '99999')
            ->call('addSkill')
            ->assertHasErrors(['skill_id' => 'exists']);

        $this->assertSame(0, $this->countFor($this->applicant));
    }

    public function test_add_rejects_non_numeric_skill(): void
    {
        $this->page()
            ->set('skill_id', 'abc')
            ->call('addSkill')
            ->assertHasErrors(['skill_id' => 'integer']);
    }

    public function test_add_rejects_decimal_skill_id(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->page()
            ->set('skill_id', $skill->id . '.5')
            ->call('addSkill')
            ->assertHasErrors(['skill_id' => 'integer']);

        $this->assertSame(0, $this->countFor($this->applicant));
    }

    public function test_add_rejects_zero_and_negative_skill_ids(): void
    {
        foreach (['0', '-1'] as $bad) {
            $this->page()->set('skill_id', $bad)->call('addSkill')->assertHasErrors(['skill_id']);
        }

        $this->assertSame(0, $this->countFor($this->applicant));
    }

    public function test_add_rejects_unknown_proficiency(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->fill($this->page(), $skill, 'Godlike')
            ->call('addSkill')
            ->assertHasErrors(['proficiency']);

        $this->assertSame(0, $this->countFor($this->applicant));
    }

    public function test_proficiency_check_is_case_sensitive(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->fill($this->page(), $skill, 'beginner')
            ->call('addSkill')
            ->assertHasErrors(['proficiency']);
    }

    public function test_add_rejects_empty_proficiency(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $this->fill($this->page(), $skill, '')
            ->call('addSkill')
            ->assertHasErrors(['proficiency' => 'required']);
    }

public function test_failed_add_does_not_flash_success(): void
{
    $this->page()->call('addSkill')->assertDontSee('Skill added.');
}
public function test_removing_a_skill_not_on_the_record_does_not_claim_success(): void
{
    $this->page()->call('removeSkill', 99999)->assertDontSee('Skill removed.');
}

    public function test_skill_deleted_meanwhile_cannot_be_added(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $component = $this->fill($this->page(), $skill);

        // an admin deletes the skill while the form is still open
        $skill->delete();

        $component->call('addSkill')->assertHasErrors(['skill_id' => 'exists']);

        $this->assertSame(0, $this->countFor($this->applicant));
    }

    // ── ADD: duplicates ──────────────────────────────────────────────

    public function test_cannot_add_the_same_skill_twice(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->applicant, $skill, 'Beginner');

        $this->fill($this->page(), $skill, 'Expert')
            ->call('addSkill')
            ->assertHasErrors('skill_id')
            ->assertSee('That skill is already on your record.');

        $this->assertSame(1, $this->countFor($this->applicant));
    }

    public function test_duplicate_attempt_does_not_change_the_existing_level(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->applicant, $skill, 'Beginner');

        $this->fill($this->page(), $skill, 'Expert')->call('addSkill');

        $this->assertDatabaseHas('applicant_skill', [
            'applicant_id' => $this->applicant->id, 'skill_id' => $skill->id, 'proficiency_level' => 'Beginner',
        ]);
    }

    public function test_double_submit_does_not_create_two_rows(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        $component = $this->fill($this->page(), $skill);
        $component->call('addSkill');

        // the form was reset after the first call; a second click with the same skill must still be safe
        $this->fill($component, $skill)->call('addSkill')->assertHasErrors('skill_id');

        $this->assertSame(1, $this->countFor($this->applicant));
    }

    public function test_another_applicant_holding_the_skill_does_not_block_adding_it(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->other, $skill);

        $this->fill($this->page(), $skill)->call('addSkill')->assertHasNoErrors();

        $this->assertTrue($this->holds($this->applicant, $skill));
        $this->assertTrue($this->holds($this->other, $skill));
    }

    // ── ADD: skill limit ─────────────────────────────────────────────

    public function test_max_skills_constant_is_fifteen(): void
    {
        $this->assertSame(15, Skills::MAX_SKILLS);
    }

    public function test_can_add_the_last_allowed_skill(): void
    {
        foreach (range(1, Skills::MAX_SKILLS - 1) as $i) {
            $this->giveSkill($this->applicant, $this->makeSkill("Skill $i"));
        }
        $last = $this->makeSkill('Last Skill');

        $this->fill($this->page(), $last)->call('addSkill')->assertHasNoErrors();

        $this->assertSame(Skills::MAX_SKILLS, $this->countFor($this->applicant));
    }

    public function test_cannot_add_beyond_the_limit(): void
    {
        foreach (range(1, Skills::MAX_SKILLS) as $i) {
            $this->giveSkill($this->applicant, $this->makeSkill("Skill $i"));
        }
        $extra = $this->makeSkill('One Too Many');

        $this->fill($this->page(), $extra)
            ->call('addSkill')
            ->assertHasErrors('skill_id')
            ->assertSee('You can add up to 15 skills.');

        $this->assertSame(Skills::MAX_SKILLS, $this->countFor($this->applicant));
        $this->assertFalse($this->holds($this->applicant, $extra));
    }

    public function test_limit_is_per_applicant_not_global(): void
    {
        foreach (range(1, Skills::MAX_SKILLS) as $i) {
            $this->giveSkill($this->other, $this->makeSkill("Skill $i"));
        }
        $mine = $this->makeSkill('My Skill');

        $this->fill($this->page(), $mine)->call('addSkill')->assertHasNoErrors();

        $this->assertTrue($this->holds($this->applicant, $mine));
    }

    public function test_removing_a_skill_frees_a_slot_at_the_limit(): void
    {
        $skills = [];
        foreach (range(1, Skills::MAX_SKILLS) as $i) {
            $skills[] = $s = $this->makeSkill("Skill $i");
            $this->giveSkill($this->applicant, $s);
        }
        $extra = $this->makeSkill('Extra');

        $page = $this->page();
        $this->fill($page, $extra)->call('addSkill')->assertHasErrors('skill_id');

        $page->call('removeSkill', $skills[0]->id);

        $this->fill($page, $extra)->call('addSkill')->assertHasNoErrors();

        $this->assertTrue($this->holds($this->applicant, $extra));
    }

    public function test_re_adding_an_owned_skill_at_the_limit_is_refused_and_writes_nothing(): void
    {
        $first = $this->makeSkill('Skill 1');
        $this->giveSkill($this->applicant, $first);
        foreach (range(2, Skills::MAX_SKILLS) as $i) {
            $this->giveSkill($this->applicant, $this->makeSkill("Skill $i"));
        }

        $this->fill($this->page(), $first)->call('addSkill')->assertHasErrors('skill_id');

        $this->assertSame(Skills::MAX_SKILLS, $this->countFor($this->applicant));
    }

    // ── REMOVE ───────────────────────────────────────────────────────

    public function test_remove_deletes_the_skill_from_the_record(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->applicant, $skill);

        $this->page()->call('removeSkill', $skill->id);

        $this->assertFalse($this->holds($this->applicant, $skill));
    }

   
    public function test_remove_only_removes_the_targeted_skill(): void
    {
        $gone = $this->makeSkill('Arc Welding');
        $kept = $this->makeSkill('MIG Welding');
        $this->giveSkill($this->applicant, $gone);
        $this->giveSkill($this->applicant, $kept);

        $this->page()->call('removeSkill', $gone->id);

        $this->assertFalse($this->holds($this->applicant, $gone));
        $this->assertTrue($this->holds($this->applicant, $kept));
    }

    public function test_remove_does_not_delete_the_skill_or_its_category(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->applicant, $skill);

        $this->page()->call('removeSkill', $skill->id);

        $this->assertDatabaseHas('skills', ['id' => $skill->id]);
        $this->assertDatabaseHas('skill_categories', ['id' => $this->welding->id]);
    }

    public function test_removed_skill_becomes_available_to_add_again(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->applicant, $skill);

        $page = $this->page()->set('category_id', (string) $this->welding->id);
        $this->assertCount(0, $page->viewData('available'));

        $page->call('removeSkill', $skill->id);

        $this->assertSame(['Arc Welding'], $page->viewData('available')->pluck('name')->all());
    }

    public function test_removing_a_skill_not_on_the_record_does_not_crash(): void
    {
        $this->page()->call('removeSkill', 99999)->assertOk();
    }

    /**
     * KNOWN BUG — expected to FAIL (APP-SKILL-1).
     *
     * removeSkill() always flashes "Skill removed." even when nothing was deleted
     * (stale page, double click, or a forged id).
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Open /skills in two tabs with the same skill listed.
     *   2. Remove the skill in tab 1, then in tab 2.
     *   3. BUG: tab 2 also says "Skill removed." Expected: no success message.
     *
     * FIX: check the return value of ->delete(); flash only when it is greater than 0.
     */

    // ── PERMISSIONS: ISOLATION BETWEEN APPLICANTS ────────────────────

    public function test_cannot_remove_another_applicants_skill(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->other, $skill, 'Expert');

        // this applicant does not hold the skill, but forges the call with its id
        $this->page()->call('removeSkill', $skill->id);

        $this->assertDatabaseHas('applicant_skill', [
            'applicant_id' => $this->other->id, 'skill_id' => $skill->id, 'proficiency_level' => 'Expert',
        ]);
    }

    public function test_removing_a_shared_skill_only_removes_my_row(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->applicant, $skill);
        $this->giveSkill($this->other, $skill);

        $this->page()->call('removeSkill', $skill->id);

        $this->assertFalse($this->holds($this->applicant, $skill));
        $this->assertTrue($this->holds($this->other, $skill));
    }

    public function test_adding_a_skill_cannot_be_redirected_to_another_applicant(): void
    {
        $skill = $this->makeSkill('Arc Welding');

        // the component has no applicant_id property to tamper with; the id comes from the guard
        $this->fill($this->page(), $skill)->call('addSkill');

        $this->assertTrue($this->holds($this->applicant, $skill));
        $this->assertFalse($this->holds($this->other, $skill));
    }

    public function test_each_applicant_sees_and_changes_only_their_own_list(): void
    {
        $a = $this->makeSkill('Arc Welding');
        $b = $this->makeSkill('Baking Bread', $this->cooking);

        $this->fill($this->page($this->applicant), $a)->call('addSkill');
        $this->fill($this->page($this->other), $b)->call('addSkill');

        $this->page($this->applicant)->assertSee('Arc Welding')->assertDontSee('Baking Bread');
        $this->page($this->other)->assertSee('Baking Bread')->assertDontSee('Arc Welding');
    }

    public function test_removing_skill_in_one_session_does_not_affect_the_other_applicants_page(): void
    {
        $skill = $this->makeSkill('Arc Welding');
        $this->giveSkill($this->applicant, $skill);
        $this->giveSkill($this->other, $skill);

        $this->page($this->applicant)->call('removeSkill', $skill->id);

        $this->page($this->other)->assertSee('Arc Welding')->assertSee('My skills (1)');
    }

    // ── EDGE CASES ───────────────────────────────────────────────────

    /**
     * The component does not check that the chosen skill belongs to the chosen
     * category (the dropdown does that in the UI only). Adding a valid skill from
     * another category is harmless because the skill is real, so it is allowed.
     */
    public function test_skill_from_a_different_category_than_selected_is_still_a_real_skill(): void
    {
        $skill = $this->makeSkill('Baking Bread', $this->cooking);

        $this->page()
            ->set('category_id', (string) $this->welding->id)
            ->set('skill_id', (string) $skill->id)
            ->call('addSkill')
            ->assertHasNoErrors();

        $this->assertTrue($this->holds($this->applicant, $skill));
    }

    public function test_list_groups_by_category_and_shows_each_category_heading_once(): void
    {
        $this->giveSkill($this->applicant, $this->makeSkill('Arc Welding', $this->welding));
        $this->giveSkill($this->applicant, $this->makeSkill('MIG Welding', $this->welding));

        $html = $this->page()->html();

        $this->assertSame(1, substr_count($html, 'tracking-wider'));
    }

    public function test_skill_name_with_html_is_escaped_in_the_list(): void
    {
        $this->giveSkill($this->applicant, $this->makeSkill('<script>alert(1)</script>'));

        $this->page()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_page_renders_with_every_category_empty(): void
    {
        $this->page()->set('category_id', (string) $this->cooking->id)->assertOk();
    }
}