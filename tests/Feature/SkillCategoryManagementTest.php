<?php

namespace Tests\Feature;

use App\Livewire\SkillCategoryManagement;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Skill category CRUD.
 *
 * WHY: categories (and their PQF level) drive the skills-gap analysis.
 * skills.skill_category_id is restrictOnDelete, so the UI must block the
 * delete cleanly instead of letting the DB throw a 500.
 */
class SkillCategoryManagementTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();
    }

    private function makeCategory(string $name, ?string $pqf = null, ?string $description = null): SkillCategory
    {
        return SkillCategory::create(['name' => $name, 'pqf_level' => $pqf, 'description' => $description]);
    }

    private function component()
    {
        return Livewire::actingAs($this->admin)->test(SkillCategoryManagement::class);
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.skill-categories'))->assertRedirect(route('login'));
    }

    public function test_component_itself_rejects_non_admin(): void
    {
        Livewire::actingAs($this->staff)->test(SkillCategoryManagement::class)->assertForbidden();
    }

    // ── LIST / SEARCH ────────────────────────────────────────────────

    public function test_lists_categories_with_pqf_level_and_skill_count(): void
    {
        $c = $this->makeCategory('Welding', 'Level 3');
        Skill::create(['name' => 'Arc Welding', 'skill_category_id' => $c->id]);
        Skill::create(['name' => 'MIG Welding', 'skill_category_id' => $c->id]);

        $this->component()->assertSee('Welding')->assertSee('Level 3')->assertSee('2');
    }

    public function test_search_by_category_name(): void
    {
        $this->makeCategory('Welding');
        $this->makeCategory('Cooking');

        $this->component()
            ->set('search', 'Welding')
            ->assertSee('Welding')
            ->assertDontSee('Cooking');
    }

    public function test_search_by_pqf_level(): void
    {
        $this->makeCategory('Welding', 'Level 3');
        $this->makeCategory('Cooking', 'Level 2');

        $this->component()
            ->set('search', 'Level 3')
            ->assertSee('Welding')
            ->assertDontSee('Cooking');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeCategory('Welding');

        $this->component()->set('search', 'zzzz-nothing')->assertSee('No category found');
    }

    public function test_changing_search_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeCategory(sprintf('Category %02d', $i));
        }

        $this->component()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('search', 'Category 1')
            ->assertSet('paginators.page', 1);
    }

    // ── CREATE ───────────────────────────────────────────────────────

    public function test_open_create_shows_empty_modal(): void
    {
        $this->component()
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

        $this->component()
            ->call('openEdit', $c->id)
            ->call('openCreate')
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('pqf_level', '')
            ->assertSet('description', '');
    }

    public function test_create_saves_all_fields_closes_modal_and_notifies(): void
    {
        $this->component()
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
        $this->component()->set('name', 'Welding')->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('skill_categories', [
            'name' => 'Welding', 'pqf_level' => null, 'description' => null,
        ]);
    }

    public function test_create_writes_audit_log(): void
    {
        $this->component()->set('name', 'Welding')->call('save');

        $this->assertAudited('SKILL_CATEGORY_CREATED', SkillCategory::where('name', 'Welding')->value('id'));
    }

    public function test_create_requires_name(): void
    {
        $this->component()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name' => 'required'])
            ->assertSet('showModal', true)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseCount('skill_categories', 0);
    }

    public function test_create_rejects_overlong_fields(): void
    {
        $this->component()
            ->set('name', str_repeat('a', 256))
            ->set('pqf_level', str_repeat('a', 256))
            ->set('description', str_repeat('a', 2001))
            ->call('save')
            ->assertHasErrors(['name' => 'max', 'pqf_level' => 'max', 'description' => 'max']);
    }

    /** categories.name has a DB unique index, so validation must catch it first. */
    public function test_duplicate_name_is_rejected(): void
    {
        $this->makeCategory('Welding');

        $this->component()
            ->set('name', 'Welding')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, SkillCategory::where('name', 'Welding')->count());
    }

    // ── EDIT ─────────────────────────────────────────────────────────

    public function test_open_edit_loads_existing_values(): void
    {
        $c = $this->makeCategory('Welding', 'Level 3', 'Metal joining');

        $this->component()
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

        $this->component()
            ->call('openEdit', $c->id)
            ->assertSet('pqf_level', '')
            ->assertSet('description', '');
    }

    public function test_edit_updates_only_the_targeted_record(): void
    {
        $target = $this->makeCategory('Welding', 'Level 3');
        $other  = $this->makeCategory('Cooking', 'Level 2');

        $this->component()
            ->call('openEdit', $target->id)
            ->set('name', 'Advanced Welding')
            ->set('pqf_level', 'Level 4')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('skill_categories', ['id' => $target->id, 'name' => 'Advanced Welding', 'pqf_level' => 'Level 4']);
        $this->assertDatabaseHas('skill_categories', ['id' => $other->id, 'name' => 'Cooking', 'pqf_level' => 'Level 2']);
    }

    public function test_edit_writes_audit_log(): void
    {
        $c = $this->makeCategory('Welding');

        $this->component()->call('openEdit', $c->id)->set('name', 'Welding 2')->call('save');

        $this->assertAudited('SKILL_CATEGORY_UPDATED', $c->id);
    }

    public function test_saving_without_changes_is_not_flagged_as_duplicate(): void
    {
        $c = $this->makeCategory('Welding');

        $this->component()->call('openEdit', $c->id)->call('save')->assertHasNoErrors();
    }

    public function test_edit_cannot_rename_into_an_existing_category(): void
    {
        $this->makeCategory('Welding');
        $cooking = $this->makeCategory('Cooking');

        $this->component()
            ->call('openEdit', $cooking->id)
            ->set('name', 'Welding')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertDatabaseHas('skill_categories', ['id' => $cooking->id, 'name' => 'Cooking']);
    }

    public function test_edit_validation_failure_keeps_original_data(): void
    {
        $c = $this->makeCategory('Welding', 'Level 3');

        $this->component()
            ->call('openEdit', $c->id)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id, 'name' => 'Welding', 'pqf_level' => 'Level 3']);
    }

    public function test_open_edit_on_missing_record_returns_404(): void
    {
        $this->component()->call('openEdit', 99999)->assertNotFound();
    }

    // ── DELETE ───────────────────────────────────────────────────────

    public function test_delete_removes_category_and_writes_audit_log(): void
    {
        $c = $this->makeCategory('Welding');

        $this->component()->call('delete', $c->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseMissing('skill_categories', ['id' => $c->id]);
        $this->assertAudited('SKILL_CATEGORY_DELETED', $c->id);
    }

    public function test_delete_is_blocked_when_category_still_has_skills(): void
    {
        $c = $this->makeCategory('Welding');
        Skill::create(['name' => 'Arc Welding', 'skill_category_id' => $c->id]);

        $this->component()->call('delete', $c->id)->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('skill_categories', ['id' => $c->id]);
        $this->assertDatabaseHas('skills', ['name' => 'Arc Welding']);
    }

    public function test_delete_only_removes_the_targeted_category(): void
    {
        $gone = $this->makeCategory('Welding');
        $kept = $this->makeCategory('Cooking');

        $this->component()->call('delete', $gone->id);

        $this->assertDatabaseMissing('skill_categories', ['id' => $gone->id]);
        $this->assertDatabaseHas('skill_categories', ['id' => $kept->id]);
    }
}
