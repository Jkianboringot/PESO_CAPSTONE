<?php

namespace Tests\Feature;

use App\Livewire\SkillCategoryManagement;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Skill category CRUD and permissions.
 *
 * WHY: categories (and their PQF level) drive the skills-gap analysis.
 * skills.skill_category_id is restrictOnDelete, so the UI must block the
 * delete cleanly instead of letting the DB throw a 500.
 *
 * NOTE ON PERMISSIONS
 * ───────────────────
 * Only admins may manage skill categories. mount() aborts with 403 for anyone
 * else, and Livewire::test() does not give a working component to a user that
 * mount() refuses. So staff and no-role tests call methods directly and check
 * for a 403 HttpException, then check the database.
 *
 * NOTE ON KNOWN BUGS AND GAPS
 * ───────────────────────────
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions.
 */
class SkillCategoryManagementTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        // A logged-in user with no admin or staff role
        $this->outsider = User::factory()->create();
    }

    private function makeCategory(string $name, ?string $pqf = null, ?string $description = null): SkillCategory
    {
        return SkillCategory::create(['name' => $name, 'pqf_level' => $pqf, 'description' => $description]);
    }

    private function adminPage()
    {
        return Livewire::actingAs($this->admin)->test(SkillCategoryManagement::class);
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
        $this->actingAs($this->admin)->get(route('admin.skill-categories'))->assertOk();
    }

    public function test_staff_cannot_open_page(): void
    {
        $this->actingAs($this->staff)->get(route('admin.skill-categories'))->assertForbidden();
    }

    public function test_user_without_role_cannot_open_page(): void
    {
        $this->actingAs($this->outsider)->get(route('admin.skill-categories'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.skill-categories'))->assertRedirect(route('login'));
    }

    // ── LIST / SEARCH ────────────────────────────────────────────────

    public function test_lists_categories_with_pqf_level_and_skill_count(): void
    {
        $c = $this->makeCategory('Welding', 'Level 3');
        Skill::create(['name' => 'Arc Welding', 'skill_category_id' => $c->id]);
        Skill::create(['name' => 'MIG Welding', 'skill_category_id' => $c->id]);

        $this->adminPage()->assertSee('Welding')->assertSee('Level 3');
    }

    public function test_lists_categories_in_name_order(): void
    {
        $this->makeCategory('Cooking');
        $this->makeCategory('Welding');
        $this->makeCategory('Carpentry');

        $this->adminPage()->assertSeeInOrder(['Carpentry', 'Cooking', 'Welding']);
    }

    public function test_skill_count_is_correct_per_category(): void
    {
        $welding = $this->makeCategory('Welding');
        $cooking = $this->makeCategory('Cooking');

        Skill::create(['name' => 'Arc Welding', 'skill_category_id' => $welding->id]);
        Skill::create(['name' => 'MIG Welding', 'skill_category_id' => $welding->id]);
        Skill::create(['name' => 'Baking', 'skill_category_id' => $cooking->id]);

        $rows = $this->adminPage()->viewData('categories');

        $this->assertSame(2, $rows->firstWhere('id', $welding->id)->skills_count);
        $this->assertSame(1, $rows->firstWhere('id', $cooking->id)->skills_count);
    }

    public function test_category_with_no_skills_shows_zero_count(): void
    {
        $c = $this->makeCategory('Welding');

        $rows = $this->adminPage()->viewData('categories');

        $this->assertSame(0, $rows->firstWhere('id', $c->id)->skills_count);
    }

    public function test_shows_empty_state_when_there_are_no_categories(): void
    {
        $this->adminPage()->assertSee('No category found');
    }

    public function test_search_by_category_name(): void
    {
        $this->makeCategory('Welding');
        $this->makeCategory('Cooking');

        $this->adminPage()
            ->set('search', 'Welding')
            ->assertSee('Welding')
            ->assertDontSee('Cooking');
    }

    public function test_search_by_pqf_level(): void
    {
        $this->makeCategory('Welding', 'Level 3');
        $this->makeCategory('Cooking', 'Level 2');

        $this->adminPage()
            ->set('search', 'Level 3')
            ->assertSee('Welding')
            ->assertDontSee('Cooking');
    }

    public function test_search_ignores_surrounding_spaces(): void
    {
        $this->makeCategory('Welding');
        $this->makeCategory('Cooking');

        $this->adminPage()
            ->set('search', '   Welding   ')
            ->assertSee('Welding')
            ->assertDontSee('Cooking');
    }

    public function test_search_is_case_insensitive_on_name(): void
    {
        $this->makeCategory('Welding');

        $this->adminPage()->set('search', 'welding')->assertSee('Welding');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeCategory('Welding');

        $this->adminPage()->set('search', 'zzzz-nothing')->assertSee('No category found');
    }

    /**
     * KNOWN BUG — expected to FAIL (SRCH-3).
     *
     * The search term goes into LIKE without escaping, so "%" matches every row.
     * Same root cause as SRCH-1 (applicants), GEO-2 (barangays), SRCH-2 (municipalities).
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin and open /admin/skill-categories with at least two categories.
     *   2. Type % in the search box.
     *   3. BUG: every category is listed. Expected: "No category found".
     *
     * FIX: escape !, %, _ before building the LIKE pattern and add ESCAPE '!' to each clause.
     */
    public function test_search_treats_percent_sign_as_literal_text(): void
    {
        $this->makeCategory('Welding');
        $this->makeCategory('Cooking');

        $this->adminPage()
            ->set('search', '%')
            ->assertDontSee('Welding')
            ->assertDontSee('Cooking');
    }

    /**
     * KNOWN BUG — expected to FAIL (SRCH-3). Same cause as the % test.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin with at least one category.
     *   2. Type _ in the search box.
     *   3. BUG: categories are listed. Expected: "No category found".
     */
    public function test_search_treats_underscore_as_literal_text(): void
    {
        $this->makeCategory('Welding');

        $this->adminPage()
            ->set('search', '_')
            ->assertDontSee('Welding');
    }

    public function test_changing_search_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeCategory(sprintf('Category %02d', $i));
        }

        $this->adminPage()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('search', 'Category 1')
            ->assertSet('paginators.page', 1);
    }

    public function test_list_shows_twenty_per_page_with_remainder_on_next_page(): void
    {
        foreach (range(1, 21) as $i) {
            $this->makeCategory(sprintf('Category %02d', $i));
        }

        $component = $this->adminPage();

        $this->assertCount(20, $component->viewData('categories'));

        $component->call('gotoPage', 2);

        $this->assertCount(1, $component->viewData('categories'));
    }

    // ── CREATE ───────────────────────────────────────────────────────

    public function test_open_create_shows_empty_modal(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('pqf_level', '')
            ->assertSet('description', '');
    }

    public function test_open_create_resets_state_left_by_a_previous_edit(): void
    {
        $c = $this->makeCategory('Welding', 'Level 3', 'desc');

        $this->adminPage()
            ->call('openEdit', $c->id)
            ->call('openCreate')
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('pqf_level', '')
            ->assertSet('description', '');
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

    public function test_create_saves_all_fields_closes_modal_and_notifies(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->set('name', '  Welding  ')
            ->set('pqf_level', 'Level 3')
            ->set('description', 'Metal joining work')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('skill_categories', [
            'name' => 'Welding', 'pqf_level' => 'Level 3', 'description' => 'Metal joining work',
        ]);
    }

    /** Blank optional fields must be stored as NULL, not empty strings. */
    public function test_blank_optional_fields_are_stored_as_null(): void
    {
        $this->adminPage()->set('name', 'Welding')->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('skill_categories', [
            'name' => 'Welding', 'pqf_level' => null, 'description' => null,
        ]);
    }

    /** Whitespace-only optional fields must also be stored as NULL. */
    public function test_whitespace_only_optional_fields_are_stored_as_null(): void
    {
        $this->adminPage()
            ->set('name', 'Welding')
            ->set('pqf_level', '     ')
            ->set('description', '     ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('skill_categories', [
            'name' => 'Welding', 'pqf_level' => null, 'description' => null,
        ]);
    }

    public function test_create_writes_audit_log(): void
    {
        $this->adminPage()->set('name', 'Welding')->call('save');

        $this->assertAudited('SKILL_CATEGORY_CREATED', SkillCategory::where('name', 'Welding')->value('id'));
    }

    public function test_create_requires_name(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name' => 'required'])
            ->assertSet('showModal', true)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseCount('skill_categories', 0);
    }

    public function test_whitespace_only_name_is_rejected(): void
    {
        $this->adminPage()
            ->set('name', '     ')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseCount('skill_categories', 0);
    }

    public function test_create_rejects_name_longer_than_255_chars(): void
    {
        $this->adminPage()
            ->set('name', str_repeat('a', 256))
            ->call('save')
            ->assertHasErrors(['name' => 'max']);
    }

    public function test_create_accepts_name_of_exactly_255_chars(): void
    {
        $name = str_repeat('a', 255);

        $this->adminPage()
            ->set('name', $name)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('skill_categories', ['name' => $name]);
    }

    public function test_create_rejects_pqf_level_longer_than_255_chars(): void
    {
        $this->adminPage()
            ->set('name', 'Welding')
            ->set('pqf_level', str_repeat('a', 256))
            ->call('save')
            ->assertHasErrors(['pqf_level' => 'max']);
    }

    public function test_create_rejects_description_longer_than_2000_chars(): void
    {
        $this->adminPage()
            ->set('name', 'Welding')
            ->set('description', str_repeat('a', 2001))
            ->call('save')
            ->assertHasErrors(['description' => 'max']);
    }

    public function test_create_accepts_description_of_exactly_2000_chars(): void
    {
        $this->adminPage()
            ->set('name', 'Welding')
            ->set('description', str_repeat('a', 2000))
            ->call('save')
            ->assertHasNoErrors();
    }

    /** categories.name has a DB unique index, so validation must catch it first. */
    public function test_duplicate_name_is_rejected(): void
    {
        $this->makeCategory('Welding');

        $this->adminPage()
            ->set('name', 'Welding')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, SkillCategory::where('name', 'Welding')->count());
    }

    /**
     * KNOWN BUG — expected to FAIL (PROV-2).
     *
     * save() validates the untrimmed name, so "  Welding  " passes the unique
     * check. The trimmed value "Welding" is then saved, creating a real duplicate.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin and add category "Welding".
     *   2. Add another with name "  Welding  " (leading and trailing spaces).
     *   3. BUG: saved, and the list shows two "Welding" rows.
     *      Expected: "The name has already been taken."
     *
     * FIX: trim $this->name before validate() in save().
     */
    public function test_padded_name_cannot_create_a_duplicate(): void
    {
        $this->makeCategory('Welding');

        $this->adminPage()
            ->call('openCreate')
            ->set('name', '  Welding  ')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, SkillCategory::where('name', 'Welding')->count());
    }

    public function test_failed_create_writes_no_audit_log(): void
    {
        $this->adminPage()->call('openCreate')->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'SKILL_CATEGORY_CREATED']);
    }

    public function test_failed_create_shows_error_toast(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertDispatched('notify', type: 'error');
    }

    // ── EDIT ─────────────────────────────────────────────────────────

    public function test_open_edit_loads_existing_values(): void
    {
        $c = $this->makeCategory('Welding', 'Level 3', 'Metal joining');

        $this->adminPage()
            ->call('openEdit', $c->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $c->id)
            ->assertSet('name', 'Welding')
            ->assertSet('pqf_level', 'Level 3')
            ->assertSet('description', 'Metal joining');
    }

    public function test_open_edit_handles_null_optional_fields(): void
    {
        $c = $this->makeCategory('Welding');

        $this->adminPage()
            ->call('openEdit', $c->id)
            ->assertSet('pqf_level', '')
            ->assertSet('description', '');
    }

    public function test_edit_updates_only_the_targeted_record(): void
    {
        $target = $this->makeCategory('Welding', 'Level 3');
        $other  = $this->makeCategory('Cooking', 'Level 2');

        $this->adminPage()
            ->call('openEdit', $target->id)
            ->set('name', 'Advanced Welding')
            ->set('pqf_level', 'Level 4')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('skill_categories', ['id' => $target->id, 'name' => 'Advanced Welding', 'pqf_level' => 'Level 4']);
        $this->assertDatabaseHas('skill_categories', ['id' => $other->id, 'name' => 'Cooking', 'pqf_level' => 'Level 2']);
    }

    public function test_edit_can_clear_optional_fields_to_null(): void
    {
        $c = $this->makeCategory('Welding', 'Level 3', 'Metal joining');

        $this->adminPage()
            ->call('openEdit', $c->id)
            ->set('pqf_level', '')
            ->set('description', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id, 'pqf_level' => null, 'description' => null]);
    }

    public function test_edit_writes_audit_log(): void
    {
        $c = $this->makeCategory('Welding');

        $this->adminPage()->call('openEdit', $c->id)->set('name', 'Welding 2')->call('save');

        $this->assertAudited('SKILL_CATEGORY_UPDATED', $c->id);
    }

    public function test_saving_without_changes_is_not_flagged_as_duplicate(): void
    {
        $c = $this->makeCategory('Welding');

        $this->adminPage()->call('openEdit', $c->id)->call('save')->assertHasNoErrors();
    }

    public function test_edit_cannot_rename_into_an_existing_category(): void
    {
        $this->makeCategory('Welding');
        $cooking = $this->makeCategory('Cooking');

        $this->adminPage()
            ->call('openEdit', $cooking->id)
            ->set('name', 'Welding')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertDatabaseHas('skill_categories', ['id' => $cooking->id, 'name' => 'Cooking']);
    }

    public function test_edit_validation_failure_keeps_original_data(): void
    {
        $c = $this->makeCategory('Welding', 'Level 3');

        $this->adminPage()
            ->call('openEdit', $c->id)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id, 'name' => 'Welding', 'pqf_level' => 'Level 3']);
    }

    public function test_failed_edit_writes_no_audit_log(): void
    {
        $c = $this->makeCategory('Welding');

        $this->adminPage()
            ->call('openEdit', $c->id)
            ->set('name', '')
            ->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'SKILL_CATEGORY_UPDATED']);
    }

    public function test_edit_on_a_category_deleted_meanwhile_shows_error_toast(): void
    {
        $c = $this->makeCategory('Welding');

        $component = $this->adminPage()->call('openEdit', $c->id);

        // another admin deletes it while this modal is still open
        $c->delete();

        $component
            ->set('name', 'Renamed')
            ->call('save')
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('skill_categories', ['name' => 'Renamed']);
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

    public function test_delete_removes_category_and_writes_audit_log(): void
    {
        $c = $this->makeCategory('Welding');

        $this->adminPage()->call('delete', $c->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseMissing('skill_categories', ['id' => $c->id]);
        $this->assertAudited('SKILL_CATEGORY_DELETED', $c->id);
    }

    public function test_delete_is_blocked_when_category_still_has_skills(): void
    {
        $c = $this->makeCategory('Welding');
        Skill::create(['name' => 'Arc Welding', 'skill_category_id' => $c->id]);

        $this->adminPage()->call('delete', $c->id)->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id]);
        $this->assertDatabaseHas('skills', ['name' => 'Arc Welding']);
    }

    public function test_blocked_delete_writes_no_audit_log(): void
    {
        $c = $this->makeCategory('Welding');
        Skill::create(['name' => 'Arc Welding', 'skill_category_id' => $c->id]);

        $this->adminPage()->call('delete', $c->id);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'SKILL_CATEGORY_DELETED']);
    }

    public function test_delete_succeeds_once_its_skills_are_removed(): void
    {
        $c = $this->makeCategory('Welding');
        $skill = Skill::create(['name' => 'Arc Welding', 'skill_category_id' => $c->id]);

        $page = $this->adminPage();

        $page->call('delete', $c->id)->assertDispatched('notify', type: 'error');

        $skill->delete();

        $page->call('delete', $c->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseMissing('skill_categories', ['id' => $c->id]);
    }

    public function test_delete_only_removes_the_targeted_category(): void
    {
        $gone = $this->makeCategory('Welding');
        $kept = $this->makeCategory('Cooking');

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseMissing('skill_categories', ['id' => $gone->id]);
        $this->assertDatabaseHas('skill_categories', ['id' => $kept->id]);
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
            ->set('name', 'New Category')
            ->call('save')
            ->assertHasNoErrors();

        $created = SkillCategory::where('name', 'New Category')->firstOrFail();

        $page->call('openEdit', $created->id)
            ->set('name', 'Renamed Category')
            ->call('save')
            ->assertHasNoErrors();

        $page->call('delete', $created->id);

        $this->assertDatabaseMissing('skill_categories', ['id' => $created->id]);
    }

    // ── PERMISSIONS: STAFF ───────────────────────────────────────────

    public function test_staff_cannot_mount_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->mount()),
            'Staff mount() should be refused with 403.'
        );
    }

    public function test_staff_cannot_render_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->render()),
            'Staff render() should be refused with 403.'
        );
    }

    public function test_staff_cannot_create_category(): void
    {
        $this->actingAs($this->staff);

        $component = new SkillCategoryManagement();
        $component->name = 'Sneaky';

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() should be refused with 403.'
        );

        $this->assertDatabaseMissing('skill_categories', ['name' => 'Sneaky']);
    }

    public function test_staff_cannot_edit_category(): void
    {
        $c = $this->makeCategory('Welding');
        $this->actingAs($this->staff);

        $component = new SkillCategoryManagement();
        $component->editingId = $c->id;
        $component->name = 'Hacked';

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() on an existing category should be refused with 403.'
        );

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id, 'name' => 'Welding']);
    }

    public function test_staff_cannot_delete_category(): void
    {
        $c = $this->makeCategory('Welding');
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->delete($c->id, app(AuditLogService::class))),
            'Staff delete() should be refused with 403.'
        );

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id]);
    }

    /**
     * KNOWN GAP — expected to FAIL until openCreate() has a role check (GEO-4).
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
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->openCreate()),
            'Staff openCreate() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until openEdit() has a role check (GEO-4).
     *
     * FIX: add abort_if(!auth()->user()->hasRole('admin'), 403); as the first line of openEdit().
     */
    public function test_staff_cannot_open_edit_form(): void
    {
        $c = $this->makeCategory('Welding');
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->openEdit($c->id)),
            'Staff openEdit() should be refused with 403.'
        );
    }

    public function test_staff_writes_leave_no_audit_trail(): void
    {
        $c = $this->makeCategory('Welding');
        $this->actingAs($this->staff);

        $audit = app(AuditLogService::class);

        $create = new SkillCategoryManagement();
        $create->name = 'Sneaky';
        $this->refusedWith403(fn () => $create->save($audit));

        $edit = new SkillCategoryManagement();
        $edit->editingId = $c->id;
        $edit->name = 'Hacked';
        $this->refusedWith403(fn () => $edit->save($audit));

        $this->refusedWith403(fn () => (new SkillCategoryManagement())->delete($c->id, $audit));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->staff->id]);
    }

    // ── PERMISSIONS: NO ROLE ─────────────────────────────────────────

    public function test_user_without_role_cannot_mount_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->mount()),
            'User without a role: mount() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_render_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->render()),
            'User without a role: render() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_create_category(): void
    {
        $this->actingAs($this->outsider);

        $component = new SkillCategoryManagement();
        $component->name = 'Sneaky';

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() should be refused with 403.'
        );

        $this->assertDatabaseMissing('skill_categories', ['name' => 'Sneaky']);
    }

    public function test_user_without_role_cannot_edit_category(): void
    {
        $c = $this->makeCategory('Welding');
        $this->actingAs($this->outsider);

        $component = new SkillCategoryManagement();
        $component->editingId = $c->id;
        $component->name = 'Hacked';

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() on an existing category should be refused with 403.'
        );

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id, 'name' => 'Welding']);
    }

    public function test_user_without_role_cannot_delete_category(): void
    {
        $c = $this->makeCategory('Welding');
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->delete($c->id, app(AuditLogService::class))),
            'User without a role: delete() should be refused with 403.'
        );

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id]);
    }

    /**
     * KNOWN GAP — expected to FAIL until openCreate() has a role check (GEO-4).
     */
    public function test_user_without_role_cannot_open_create_form(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->openCreate()),
            'User without a role: openCreate() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until openEdit() has a role check (GEO-4).
     */
    public function test_user_without_role_cannot_open_edit_form(): void
    {
        $c = $this->makeCategory('Welding');
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new SkillCategoryManagement())->openEdit($c->id)),
            'User without a role: openEdit() should be refused with 403.'
        );
    }

    public function test_user_without_role_writes_leave_no_audit_trail(): void
    {
        $c = $this->makeCategory('Welding');
        $this->actingAs($this->outsider);

        $audit = app(AuditLogService::class);

        $create = new SkillCategoryManagement();
        $create->name = 'Sneaky';
        $this->refusedWith403(fn () => $create->save($audit));

        $this->refusedWith403(fn () => (new SkillCategoryManagement())->delete($c->id, $audit));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->outsider->id]);
    }
}