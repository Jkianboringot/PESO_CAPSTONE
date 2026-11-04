<?php

namespace Tests\Feature;

use App\Livewire\SkillManagement;
use App\Models\Applicant;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Skill CRUD.
 *
 * WHY: skills are what applicants pick at registration and what the
 * skills-gap analysis counts. Deleting a skill that applicants hold would
 * silently wipe their skill records.
 */
class SkillManagementTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private SkillCategory $welding;
    private SkillCategory $cooking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $this->welding = SkillCategory::create(['name' => 'Welding', 'pqf_level' => 'Level 3']);
        $this->cooking = SkillCategory::create(['name' => 'Cooking', 'pqf_level' => 'Level 2']);
    }

    private function makeSkill(string $name, ?SkillCategory $category = null): Skill
    {
        return Skill::create(['name' => $name, 'skill_category_id' => ($category ?? $this->welding)->id]);
    }

    private function component()
    {
        return Livewire::actingAs($this->admin)->test(SkillManagement::class);
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.skills'))->assertRedirect(route('login'));
    }

    public function test_component_itself_rejects_non_admin(): void
    {
        Livewire::actingAs($this->staff)->test(SkillManagement::class)->assertForbidden();
    }

    // ── LIST / SEARCH / FILTER ───────────────────────────────────────

    public function test_lists_skills_with_category_and_pqf_level(): void
    {
        $this->makeSkill('Arc Welding');

        $this->component()->assertSee('Arc Welding')->assertSee('Welding')->assertSee('Level 3');
    }

    public function test_search_by_skill_name(): void
    {
        $this->makeSkill('Arc Welding');
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->component()
            ->set('search', 'Arc')
            ->assertSee('Arc Welding')
            ->assertDontSee('Baking Bread');
    }

    public function test_category_filter_shows_only_that_categorys_skills(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->component()
            ->set('categoryFilter', (string) $this->cooking->id)
            ->assertSee('Baking Bread')
            ->assertDontSee('Arc Welding');
    }

    public function test_search_and_category_filter_work_together(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);
        $this->makeSkill('MIG Welding', $this->welding);
        $this->makeSkill('Arc Cooking', $this->cooking);

        $this->component()
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

        $this->component()
            ->set('categoryFilter', (string) $this->cooking->id)
            ->set('categoryFilter', '')
            ->assertSee('Arc Welding')
            ->assertSee('Baking Bread');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeSkill('Arc Welding');

        $this->component()->set('search', 'zzzz-nothing')->assertSee('No skill found');
    }

    public function test_changing_search_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeSkill(sprintf('Skill %02d', $i));
        }

        $this->component()
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

        $this->component()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('categoryFilter', (string) $this->welding->id)
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
            ->assertSet('skill_category_id', '');
    }

    public function test_open_create_resets_state_left_by_a_previous_edit(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->component()
            ->call('openEdit', $s->id)
            ->call('openCreate')
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('skill_category_id', '');
    }

    public function test_create_saves_closes_modal_and_notifies(): void
    {
        $this->component()
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
        $this->component()
            ->set('name', 'Arc Welding')
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save');

        $this->assertAudited('SKILL_CREATED', Skill::where('name', 'Arc Welding')->value('id'));
    }

    public function test_create_requires_name_and_category(): void
    {
        $this->component()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'skill_category_id' => 'required'])
            ->assertSet('showModal', true)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseCount('skills', 0);
    }

    public function test_create_rejects_unknown_category(): void
    {
        $this->component()
            ->set('name', 'Ghost Skill')
            ->set('skill_category_id', '99999')
            ->call('save')
            ->assertHasErrors(['skill_category_id' => 'exists']);
    }

    public function test_create_rejects_name_longer_than_255_chars(): void
    {
        $this->component()
            ->set('name', str_repeat('a', 256))
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasErrors(['name' => 'max']);
    }

    public function test_duplicate_name_in_same_category_is_rejected(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);

        $this->component()
            ->set('name', 'Arc Welding')
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, Skill::where('name', 'Arc Welding')->count());
    }

    public function test_same_name_in_different_category_is_allowed(): void
    {
        $this->makeSkill('Safety Training', $this->welding);

        $this->component()
            ->set('name', 'Safety Training')
            ->set('skill_category_id', (string) $this->cooking->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Skill::where('name', 'Safety Training')->count());
    }

    // ── EDIT ─────────────────────────────────────────────────────────

    public function test_open_edit_loads_existing_values(): void
    {
        $s = $this->makeSkill('Baking Bread', $this->cooking);

        $this->component()
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

        $this->component()
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

    public function test_edit_writes_audit_log(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->component()->call('openEdit', $s->id)->set('name', 'Arc Welding 2')->call('save');

        $this->assertAudited('SKILL_UPDATED', $s->id);
    }

    public function test_saving_without_changes_is_not_flagged_as_duplicate(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->component()->call('openEdit', $s->id)->call('save')->assertHasNoErrors();
    }

    public function test_edit_cannot_rename_into_an_existing_skill_in_same_category(): void
    {
        $this->makeSkill('Arc Welding');
        $mig = $this->makeSkill('MIG Welding');

        $this->component()
            ->call('openEdit', $mig->id)
            ->set('name', 'Arc Welding')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertDatabaseHas('skills', ['id' => $mig->id, 'name' => 'MIG Welding']);
    }

    public function test_edit_validation_failure_keeps_original_data(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->component()
            ->call('openEdit', $s->id)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        $this->assertDatabaseHas('skills', ['id' => $s->id, 'name' => 'Arc Welding']);
    }

    public function test_open_edit_on_missing_record_returns_404(): void
    {
        $this->component()->call('openEdit', 99999)->assertNotFound();
    }

    // ── DELETE ───────────────────────────────────────────────────────

    public function test_delete_removes_skill_and_writes_audit_log(): void
    {
        $s = $this->makeSkill('Arc Welding');

        $this->component()->call('delete', $s->id)->assertDispatched('notify', type: 'success');

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
        $applicant = Applicant::factory()->create();
        $s->applicants()->attach($applicant->id, ['proficiency_level' => 'Intermediate']);

        $this->component()->call('delete', $s->id)->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('skills', ['id' => $s->id]);
        $this->assertDatabaseHas('applicant_skill', ['skill_id' => $s->id, 'applicant_id' => $applicant->id]);
    }

    public function test_delete_only_removes_the_targeted_skill(): void
    {
        $gone = $this->makeSkill('Arc Welding');
        $kept = $this->makeSkill('MIG Welding');

        $this->component()->call('delete', $gone->id);

        $this->assertDatabaseMissing('skills', ['id' => $gone->id]);
        $this->assertDatabaseHas('skills', ['id' => $kept->id]);
    }
}
