<?php

namespace Tests\Feature;

use App\Livewire\SkillManagement;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Skill CRUD and permissions.
 *
 * WHY: skills are what applicants pick at registration and what the
 * skills-gap analysis counts. Deleting a skill that applicants hold would
 * silently wipe their skill records.
 *
 * NOTE ON PERMISSIONS
 * ───────────────────
 * Only admins may manage skills. mount() aborts with 403 for anyone else, and
 * Livewire::test() does not give a working component to a user that mount()
 * refuses. So staff and no-role tests call methods directly and check for a
 * 403 HttpException, then check the database.
 *
 * NOTE ON KNOWN BUGS AND GAPS
 * ───────────────────────────
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions.
 */
class SkillManagementTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private SkillCategory $welding;
    private SkillCategory $cooking;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $this->welding = SkillCategory::create(['name' => 'Welding', 'pqf_level' => 'Level 3']);
        $this->cooking = SkillCategory::create(['name' => 'Cooking', 'pqf_level' => 'Level 2']);

        // A logged-in user with no admin or staff role
        $this->outsider = User::factory()->create();
    }

    private function makeSkill(string $name, ?SkillCategory $category = null): Skill
    {
        return Skill::create(['name' => $name, 'skill_category_id' => ($category ?? $this->welding)->id]);
    }

    /**
     * ASSUMPTION: applicant_skill.proficiency_level accepts 'Intermediate'.
     * Change it if that column is an enum with different values.
     */
    private function makeApplicant(Skill $skill, string $barangayName = 'San Jose'): Applicant
    {
        $municipality = Municipality::firstOrCreate(['name' => 'Virac', 'province' => 'Catanduanes']);
        $barangay     = Barangay::firstOrCreate(['name' => $barangayName, 'municipality_id' => $municipality->id]);
        $applicant    = Applicant::factory()->create([
            'barangay_id' => $barangay->id,
            'password'    => bcrypt('password'),
        ]);
        $skill->applicants()->attach($applicant->id, ['proficiency_level' => 'Intermediate']);

        return $applicant;
    }

    private function adminPage()
    {
        return Livewire::actingAs($this->admin)->test(SkillManagement::class);
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
        $this->actingAs($this->admin)->get(route('admin.skills'))->assertOk();
    }

    public function test_staff_cannot_open_page(): void
    {
        $this->actingAs($this->staff)->get(route('admin.skills'))->assertForbidden();
    }

    public function test_user_without_role_cannot_open_page(): void
    {
        $this->actingAs($this->outsider)->get(route('admin.skills'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.skills'))->assertRedirect(route('login'));
    }

    public function test_component_itself_rejects_non_admin(): void
    {
        Livewire::actingAs($this->staff)->test(SkillManagement::class)->assertForbidden();
    }

    public function test_component_itself_rejects_user_without_role(): void
    {
        Livewire::actingAs($this->outsider)->test(SkillManagement::class)->assertForbidden();
    }

    // ── LIST / SEARCH / FILTER ───────────────────────────────────────

    public function test_lists_skills_with_category_and_pqf_level(): void
    {
        $this->makeSkill('Arc Welding');

        $this->adminPage()->assertSee('Arc Welding')->assertSee('Welding')->assertSee('Level 3');
    }

    public function test_lists_skills_in_name_order(): void
    {
        $this->makeSkill('Soldering');
        $this->makeSkill('Arc Welding');
        $this->makeSkill('MIG Welding');

        $this->adminPage()->assertSeeInOrder(['Arc Welding', 'MIG Welding', 'Soldering']);
    }

    public function test_shows_dash_when_category_has_no_pqf_level(): void
    {
        $noPqf = SkillCategory::create(['name' => 'Carpentry']);
        $this->makeSkill('Framing', $noPqf);

        $this->adminPage()->assertSee('Framing')->assertSee('—');
    }

    public function test_applicant_count_is_correct_per_skill(): void
    {
        $arc = $this->makeSkill('Arc Welding');
        $mig = $this->makeSkill('MIG Welding');

        $this->makeApplicant($arc, 'San Jose');
        $this->makeApplicant($arc, 'San Isidro');
        $this->makeApplicant($mig, 'Santa Elena');

        $rows = $this->adminPage()->viewData('skills');

        $this->assertSame(2, $rows->firstWhere('id', $arc->id)->applicants_count);
        $this->assertSame(1, $rows->firstWhere('id', $mig->id)->applicants_count);
    }

    public function test_skill_with_no_applicants_shows_zero_count(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $rows = $this->adminPage()->viewData('skills');

        $this->assertSame(0, $rows->firstWhere('id', $s->id)->applicants_count);
    }

    public function test_shows_empty_state_when_there_are_no_skills(): void
    {
        $this->adminPage()->assertSee('No skill found');
    }

    public function test_category_dropdown_lists_all_categories_in_name_order(): void
    {
        SkillCategory::create(['name' => 'Carpentry']);

        $names = $this->adminPage()->viewData('categories')->pluck('name')->all();

        $this->assertSame(['Carpentry', 'Cooking', 'Welding'], $names);
    }

    public function test_search_by_skill_name(): void
    {
        $this->makeSkill('Arc Welding');
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->set('search', 'Arc')
            ->assertSee('Arc Welding')
            ->assertDontSee('Baking Bread');
    }

    public function test_search_ignores_surrounding_spaces(): void
    {
        $this->makeSkill('Arc Welding');
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->set('search', '   Arc   ')
            ->assertSee('Arc Welding')
            ->assertDontSee('Baking Bread');
    }

    public function test_whitespace_only_search_shows_everything(): void
    {
        $this->makeSkill('Arc Welding');
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->set('search', '     ')
            ->assertSee('Arc Welding')
            ->assertSee('Baking Bread');
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->makeSkill('Arc Welding');

        $this->adminPage()->set('search', 'arc welding')->assertSee('Arc Welding');
    }

    public function test_search_matches_part_of_the_name(): void
    {
        $this->makeSkill('Arc Welding');

        $this->adminPage()->set('search', 'Weld')->assertSee('Arc Welding');
    }

    public function test_search_does_not_match_category_name(): void
    {
        $this->makeSkill('Baking Bread', $this->cooking);

        // "Cooking" is the category name, not part of the skill name
        $this->adminPage()->set('search', 'Cooking')->assertDontSee('Baking Bread');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeSkill('Arc Welding');

        $this->adminPage()->set('search', 'zzzz-nothing')->assertSee('No skill found');
    }

    /**
     * KNOWN BUG — expected to FAIL (SRCH-4).
     *
     * The search term goes into LIKE without escaping, so "%" matches every row.
     * Same root cause as SRCH-1 (applicants), GEO-2 (barangays), SRCH-2 (municipalities).
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin and open /admin/skills with at least two skills.
     *   2. Type % in the search box.
     *   3. BUG: every skill is listed. Expected: "No skill found".
     *
     * FIX: escape !, %, _ before building the LIKE pattern and add ESCAPE '!' to the clause.
     */
    public function test_search_treats_percent_sign_as_literal_text(): void
    {
        $this->makeSkill('Arc Welding');
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->set('search', '%')
            ->assertDontSee('Arc Welding')
            ->assertDontSee('Baking Bread');
    }

    /**
     * KNOWN BUG — expected to FAIL (SRCH-4). Same cause as the % test.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin with at least one skill.
     *   2. Type _ in the search box.
     *   3. BUG: skills are listed. Expected: "No skill found".
     */
    public function test_search_treats_underscore_as_literal_text(): void
    {
        $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->set('search', '_')
            ->assertDontSee('Arc Welding');
    }

    /** "!" is the escape character, so a real "!" in a name must still be findable. */
    public function test_search_finds_a_name_containing_an_exclamation_mark(): void
    {
        $this->makeSkill('Safety First!');
        $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->set('search', '!')
            ->assertSee('Safety First!')
            ->assertDontSee('Arc Welding');
    }

    public function test_category_filter_shows_only_that_categorys_skills(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->set('categoryFilter', (string) $this->cooking->id)
            ->assertSee('Baking Bread')
            ->assertDontSee('Arc Welding');
    }

    public function test_category_filter_with_unknown_id_shows_empty_state(): void
    {
        $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->set('categoryFilter', '99999')
            ->assertSee('No skill found')
            ->assertDontSee('Arc Welding');
    }

    public function test_category_filter_on_category_with_no_skills_shows_empty_state(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);

        $this->adminPage()
            ->set('categoryFilter', (string) $this->cooking->id)
            ->assertSee('No skill found');
    }

    public function test_search_and_category_filter_work_together(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);
        $this->makeSkill('MIG Welding', $this->welding);
        $this->makeSkill('Arc Cooking', $this->cooking);

        $this->adminPage()
            ->set('categoryFilter', (string) $this->welding->id)
            ->set('search', 'Arc')
            ->assertSee('Arc Welding')
            ->assertDontSee('MIG Welding')
            ->assertDontSee('Arc Cooking');
    }

    public function test_clearing_category_filter_shows_everything_again(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->set('categoryFilter', (string) $this->cooking->id)
            ->set('categoryFilter', '')
            ->assertSee('Arc Welding')
            ->assertSee('Baking Bread');
    }

    public function test_changing_search_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeSkill(sprintf('Skill %02d', $i));
        }

        $this->adminPage()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('search', 'Skill 1')
            ->assertSet('paginators.page', 1);
    }

    public function test_changing_category_filter_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeSkill(sprintf('Skill %02d', $i));
        }

        $this->adminPage()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('categoryFilter', (string) $this->welding->id)
            ->assertSet('paginators.page', 1);
    }

    public function test_list_shows_twenty_per_page_with_remainder_on_next_page(): void
    {
        foreach (range(1, 21) as $i) {
            $this->makeSkill(sprintf('Skill %02d', $i));
        }

        $component = $this->adminPage();

        $this->assertCount(20, $component->viewData('skills'));

        $component->call('gotoPage', 2);

        $this->assertCount(1, $component->viewData('skills'));
    }

    // ── CREATE ───────────────────────────────────────────────────────

    public function test_open_create_shows_empty_modal(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('skill_category_id', '');
    }

    public function test_open_create_resets_state_left_by_a_previous_edit(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->call('openCreate')
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('skill_category_id', '');
    }

    public function test_open_create_clears_errors_from_a_failed_save(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors()
            ->call('openCreate')
            ->assertHasNoErrors();
    }

    public function test_create_saves_closes_modal_and_notifies(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->set('name', '  Arc Welding  ')
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('skills', [
            'name' => 'Arc Welding', 'skill_category_id' => $this->welding->id,
        ]);
    }

    public function test_create_writes_audit_log(): void
    {
        $this->adminPage()
            ->set('name', 'Arc Welding')
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save');

        $this->assertAudited('SKILL_CREATED', Skill::where('name', 'Arc Welding')->value('id'));
    }

    public function test_create_requires_name_and_category(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'skill_category_id' => 'required'])
            ->assertSet('showModal', true)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseCount('skills', 0);
    }

    public function test_create_requires_category_when_name_is_given(): void
    {
        $this->adminPage()
            ->set('name', 'Arc Welding')
            ->call('save')
            ->assertHasErrors(['skill_category_id' => 'required']);

        $this->assertDatabaseCount('skills', 0);
    }

    public function test_create_requires_name_when_category_is_given(): void
    {
        $this->adminPage()
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseCount('skills', 0);
    }

    public function test_whitespace_only_name_is_rejected(): void
    {
        $this->adminPage()
            ->set('name', '     ')
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseCount('skills', 0);
    }

    public function test_create_rejects_unknown_category(): void
    {
        $this->adminPage()
            ->set('name', 'Ghost Skill')
            ->set('skill_category_id', '99999')
            ->call('save')
            ->assertHasErrors(['skill_category_id' => 'exists']);
    }

    public function test_create_rejects_non_numeric_category(): void
    {
        $this->adminPage()
            ->set('name', 'Ghost Skill')
            ->set('skill_category_id', 'abc')
            ->call('save')
            ->assertHasErrors(['skill_category_id']);

        $this->assertDatabaseCount('skills', 0);
    }

    public function test_create_rejects_name_longer_than_255_chars(): void
    {
        $this->adminPage()
            ->set('name', str_repeat('a', 256))
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasErrors(['name' => 'max']);
    }

    public function test_create_accepts_name_of_exactly_255_chars(): void
    {
        $name = str_repeat('a', 255);

        $this->adminPage()
            ->set('name', $name)
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('skills', ['name' => $name]);
    }

    public function test_duplicate_name_in_same_category_is_rejected(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);

        $this->adminPage()
            ->set('name', 'Arc Welding')
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, Skill::where('name', 'Arc Welding')->count());
    }

    public function test_same_name_in_different_category_is_allowed(): void
    {
        $this->makeSkill('Safety Training', $this->welding);

        $this->adminPage()
            ->set('name', 'Safety Training')
            ->set('skill_category_id', (string) $this->cooking->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Skill::where('name', 'Safety Training')->count());
    }

    /**
     * KNOWN BUG — expected to FAIL (PROV-3).
     *
     * save() validates the untrimmed name, so "  Arc Welding  " passes the unique
     * check. The trimmed value "Arc Welding" is then saved, creating a real duplicate
     * inside the same category.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin and add skill "Arc Welding" under Welding.
     *   2. Add another with name "  Arc Welding  " (leading and trailing spaces) under Welding.
     *   3. BUG: saved, and the list shows two "Arc Welding" rows.
     *      Expected: "The name has already been taken."
     *
     * FIX: trim $this->name before validate() in save().
     */
    public function test_padded_name_cannot_create_a_duplicate(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);

        $this->adminPage()
            ->call('openCreate')
            ->set('name', '  Arc Welding  ')
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, Skill::where('name', 'Arc Welding')->count());
    }

    public function test_failed_create_writes_no_audit_log(): void
    {
        $this->adminPage()->call('openCreate')->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'SKILL_CREATED']);
    }

    public function test_failed_create_shows_error_toast(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertDispatched('notify', type: 'error');
    }

    public function test_create_fails_cleanly_when_category_is_deleted_meanwhile(): void
    {
        $temp = SkillCategory::create(['name' => 'Temporary']);

        $component = $this->adminPage()
            ->call('openCreate')
            ->set('name', 'Orphan Skill')
            ->set('skill_category_id', (string) $temp->id);

        // another admin deletes the category while this modal is still open
        $temp->delete();

        $component
            ->call('save')
            ->assertHasErrors(['skill_category_id' => 'exists'])
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('skills', ['name' => 'Orphan Skill']);
    }

    // ── EDIT ─────────────────────────────────────────────────────────

    public function test_open_edit_loads_existing_values(): void
    {
        $s = $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $s->id)
            ->assertSet('name', 'Baking Bread')
            ->assertSet('skill_category_id', (string) $this->cooking->id);
    }

    public function test_edit_updates_only_the_targeted_record(): void
    {
        $target = $this->makeSkill('Arc Welding', $this->welding);
        $other  = $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->call('openEdit', $target->id)
            ->set('name', 'Arc Welding (Advanced)')
            ->set('skill_category_id', (string) $this->cooking->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('skills', [
            'id' => $target->id, 'name' => 'Arc Welding (Advanced)', 'skill_category_id' => $this->cooking->id,
        ]);
        $this->assertDatabaseHas('skills', [
            'id' => $other->id, 'name' => 'Baking Bread', 'skill_category_id' => $this->cooking->id,
        ]);
    }

    public function test_edit_closes_modal_after_saving(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->set('name', 'Arc Welding 2')
            ->call('save')
            ->assertSet('showModal', false);
    }

    public function test_edit_trims_the_saved_name(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->set('name', '  Arc Welding 2  ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('skills', ['id' => $s->id, 'name' => 'Arc Welding 2']);
    }

    public function test_edit_can_move_skill_to_another_category(): void
    {
        $s = $this->makeSkill('Safety Training', $this->welding);

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->set('skill_category_id', (string) $this->cooking->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('skills', ['id' => $s->id, 'skill_category_id' => $this->cooking->id]);
    }

    public function test_edit_cannot_move_skill_into_a_category_that_already_has_that_name(): void
    {
        $this->makeSkill('Safety Training', $this->cooking);
        $moving = $this->makeSkill('Safety Training', $this->welding);

        $this->adminPage()
            ->call('openEdit', $moving->id)
            ->set('skill_category_id', (string) $this->cooking->id)
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertDatabaseHas('skills', ['id' => $moving->id, 'skill_category_id' => $this->welding->id]);
    }

    public function test_edit_rejects_unknown_category(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->set('skill_category_id', '99999')
            ->call('save')
            ->assertHasErrors(['skill_category_id' => 'exists']);

        $this->assertDatabaseHas('skills', ['id' => $s->id, 'skill_category_id' => $this->welding->id]);
    }

    public function test_edit_keeps_applicant_links(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $applicant = $this->makeApplicant($s);

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->set('name', 'Arc Welding (Advanced)')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applicant_skill', ['skill_id' => $s->id, 'applicant_id' => $applicant->id]);
    }

    public function test_edit_writes_audit_log(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()->call('openEdit', $s->id)->set('name', 'Arc Welding 2')->call('save');

        $this->assertAudited('SKILL_UPDATED', $s->id);
    }

    public function test_saving_without_changes_is_not_flagged_as_duplicate(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()->call('openEdit', $s->id)->call('save')->assertHasNoErrors();
    }

    public function test_edit_cannot_rename_into_an_existing_skill_in_same_category(): void
    {
        $this->makeSkill('Arc Welding');
        $mig = $this->makeSkill('MIG Welding');

        $this->adminPage()
            ->call('openEdit', $mig->id)
            ->set('name', 'Arc Welding')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertDatabaseHas('skills', ['id' => $mig->id, 'name' => 'MIG Welding']);
    }

    public function test_edit_can_rename_into_a_name_used_in_another_category(): void
    {
        $this->makeSkill('Safety Training', $this->cooking);
        $s = $this->makeSkill('Arc Welding', $this->welding);

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->set('name', 'Safety Training')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('skills', ['id' => $s->id, 'name' => 'Safety Training']);
    }

    public function test_edit_validation_failure_keeps_original_data(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseHas('skills', ['id' => $s->id, 'name' => 'Arc Welding']);
    }

    public function test_failed_edit_writes_no_audit_log(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()
            ->call('openEdit', $s->id)
            ->set('name', '')
            ->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'SKILL_UPDATED']);
    }

    public function test_edit_on_a_skill_deleted_meanwhile_shows_error_toast(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $component = $this->adminPage()->call('openEdit', $s->id);

        // another admin deletes it while this modal is still open
        $s->delete();

        $component
            ->set('name', 'Renamed')
            ->call('save')
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('skills', ['name' => 'Renamed']);
    }

    public function test_open_edit_on_missing_record_returns_404(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->adminPage()->call('openEdit', 99999);
    }

    public function test_close_modal_hides_it_and_clears_errors(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors()
            ->call('closeModal')
            ->assertSet('showModal', false)
            ->assertHasNoErrors();
    }

    // ── DELETE ───────────────────────────────────────────────────────

    public function test_delete_removes_skill_and_writes_audit_log(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()->call('delete', $s->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseMissing('skills', ['id' => $s->id]);
        $this->assertAudited('SKILL_DELETED', $s->id);
    }

    /**
     * ASSUMPTION: applicant_skill.proficiency_level accepts the string below.
     * Change it if that column is an enum with different values.
     */
    public function test_delete_is_blocked_when_applicants_have_the_skill(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $municipality = Municipality::create(['name' => 'Virac', 'province' => 'Catanduanes']);
        $barangay     = Barangay::create(['name' => 'San Jose', 'municipality_id' => $municipality->id]);
        $applicant    = Applicant::factory()->create([
            'barangay_id' => $barangay->id,
            'password'    => bcrypt('password'),
        ]);
        $s->applicants()->attach($applicant->id, ['proficiency_level' => 'Intermediate']);

        $this->adminPage()->call('delete', $s->id)->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('skills', ['id' => $s->id]);
        $this->assertDatabaseHas('applicant_skill', ['skill_id' => $s->id, 'applicant_id' => $applicant->id]);
    }

    public function test_blocked_delete_message_shows_applicant_count(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->makeApplicant($s, 'San Jose');
        $this->makeApplicant($s, 'San Isidro');

        $this->adminPage()
            ->call('delete', $s->id)
            ->assertDispatched('notify', type: 'error', message: 'Cannot delete: 2 applicant(s) have this skill.');
    }

    public function test_blocked_delete_writes_no_audit_log(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->makeApplicant($s);

        $this->adminPage()->call('delete', $s->id);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'SKILL_DELETED']);
    }

    public function test_delete_succeeds_once_applicants_no_longer_have_the_skill(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $applicant = $this->makeApplicant($s);

        $page = $this->adminPage();

        $page->call('delete', $s->id)->assertDispatched('notify', type: 'error');

        $s->applicants()->detach($applicant->id);

        $page->call('delete', $s->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseMissing('skills', ['id' => $s->id]);
    }

    public function test_delete_only_removes_the_targeted_skill(): void
    {
        $gone = $this->makeSkill('Arc Welding');
        $kept = $this->makeSkill('MIG Welding');

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseMissing('skills', ['id' => $gone->id]);
        $this->assertDatabaseHas('skills', ['id' => $kept->id]);
    }

    public function test_delete_does_not_remove_the_category(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->adminPage()->call('delete', $s->id);

        $this->assertDatabaseHas('skill_categories', ['id' => $this->welding->id]);
    }

    public function test_delete_does_not_remove_other_applicants_skill_links(): void
    {
        $gone = $this->makeSkill('Arc Welding');
        $kept = $this->makeSkill('MIG Welding');
        $applicant = $this->makeApplicant($kept);

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseHas('applicant_skill', ['skill_id' => $kept->id, 'applicant_id' => $applicant->id]);
    }

    public function test_delete_missing_record_returns_404(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->adminPage()->call('delete', 99999);
    }

    // ── PERMISSIONS: ADMIN ───────────────────────────────────────────

    public function test_admin_can_create_edit_and_delete(): void
    {
        $page = $this->adminPage();

        $page->call('openCreate')
            ->set('name', 'New Skill')
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasNoErrors();

        $created = Skill::where('name', 'New Skill')->firstOrFail();

        $page->call('openEdit', $created->id)
            ->set('name', 'Renamed Skill')
            ->call('save')
            ->assertHasNoErrors();

        $page->call('delete', $created->id);

        $this->assertDatabaseMissing('skills', ['id' => $created->id]);
    }

    // ── PERMISSIONS: STAFF ───────────────────────────────────────────

    public function test_staff_cannot_mount_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->mount()),
            'Staff mount() should be refused with 403.'
        );
    }

    public function test_staff_cannot_render_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->render()),
            'Staff render() should be refused with 403.'
        );
    }

    public function test_staff_cannot_create_skill(): void
    {
        $this->actingAs($this->staff);

        $component = new SkillManagement();
        $component->name = 'Sneaky';
        $component->skill_category_id = (string) $this->welding->id;

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() should be refused with 403.'
        );

        $this->assertDatabaseMissing('skills', ['name' => 'Sneaky']);
    }

    public function test_staff_cannot_edit_skill(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->actingAs($this->staff);

        $component = new SkillManagement();
        $component->editingId = $s->id;
        $component->name = 'Hacked';
        $component->skill_category_id = (string) $this->welding->id;

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() on an existing skill should be refused with 403.'
        );

        $this->assertDatabaseHas('skills', ['id' => $s->id, 'name' => 'Arc Welding']);
    }

    public function test_staff_cannot_delete_skill(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->delete($s->id, app(AuditLogService::class))),
            'Staff delete() should be refused with 403.'
        );

        $this->assertDatabaseHas('skills', ['id' => $s->id]);
    }

    /**
     * KNOWN GAP — expected to FAIL until openCreate() has a role check (GEO-5).
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   The route blocks staff, so this gap only matters if the component is
     *   reached another way. Calling openCreate() directly as staff shows the form.
     *
     * FIX: add abort_if(!auth()->user()->hasRole('admin'), 403); as the first line of openCreate().
     */
    public function test_staff_cannot_open_create_form(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->openCreate()),
            'Staff openCreate() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until openEdit() has a role check (GEO-5).
     *
     * FIX: add abort_if(!auth()->user()->hasRole('admin'), 403); as the first line of openEdit().
     */
    public function test_staff_cannot_open_edit_form(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->openEdit($s->id)),
            'Staff openEdit() should be refused with 403.'
        );
    }

    public function test_staff_writes_leave_no_audit_trail(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->actingAs($this->staff);

        $audit = app(AuditLogService::class);

        $create = new SkillManagement();
        $create->name = 'Sneaky';
        $create->skill_category_id = (string) $this->welding->id;
        $this->refusedWith403(fn () => $create->save($audit));

        $edit = new SkillManagement();
        $edit->editingId = $s->id;
        $edit->name = 'Hacked';
        $edit->skill_category_id = (string) $this->welding->id;
        $this->refusedWith403(fn () => $edit->save($audit));

        $this->refusedWith403(fn () => (new SkillManagement())->delete($s->id, $audit));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->staff->id]);
    }

    // ── PERMISSIONS: NO ROLE ─────────────────────────────────────────

    public function test_user_without_role_cannot_mount_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->mount()),
            'User without a role: mount() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_render_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->render()),
            'User without a role: render() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_create_skill(): void
    {
        $this->actingAs($this->outsider);

        $component = new SkillManagement();
        $component->name = 'Sneaky';
        $component->skill_category_id = (string) $this->welding->id;

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() should be refused with 403.'
        );

        $this->assertDatabaseMissing('skills', ['name' => 'Sneaky']);
    }

    public function test_user_without_role_cannot_edit_skill(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->actingAs($this->outsider);

        $component = new SkillManagement();
        $component->editingId = $s->id;
        $component->name = 'Hacked';
        $component->skill_category_id = (string) $this->welding->id;

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() on an existing skill should be refused with 403.'
        );

        $this->assertDatabaseHas('skills', ['id' => $s->id, 'name' => 'Arc Welding']);
    }

    public function test_user_without_role_cannot_delete_skill(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->delete($s->id, app(AuditLogService::class))),
            'User without a role: delete() should be refused with 403.'
        );

        $this->assertDatabaseHas('skills', ['id' => $s->id]);
    }

    /**
     * KNOWN GAP — expected to FAIL until openCreate() has a role check (GEO-5).
     */
    public function test_user_without_role_cannot_open_create_form(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->openCreate()),
            'User without a role: openCreate() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until openEdit() has a role check (GEO-5).
     */
    public function test_user_without_role_cannot_open_edit_form(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillManagement())->openEdit($s->id)),
            'User without a role: openEdit() should be refused with 403.'
        );
    }

    public function test_user_without_role_writes_leave_no_audit_trail(): void
    {
        $s = $this->makeSkill('Arc Welding');
        $this->actingAs($this->outsider);

        $audit = app(AuditLogService::class);

        $create = new SkillManagement();
        $create->name = 'Sneaky';
        $create->skill_category_id = (string) $this->welding->id;
        $this->refusedWith403(fn () => $create->save($audit));

        $this->refusedWith403(fn () => (new SkillManagement())->delete($s->id, $audit));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->outsider->id]);
    }
}