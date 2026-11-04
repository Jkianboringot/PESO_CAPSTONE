<?php

namespace Tests\Feature;

use App\Livewire\MunicipalityManagement;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Municipality CRUD.
 *
 * WHY: municipalities are the parent of every barangay. Deleting or
 * mis-saving one breaks the whole geographic hierarchy used in reports.
 */
class MunicipalityManagementTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();
    }

    private function makeMunicipality(string $name, string $province = 'Catanduanes'): Municipality
    {
        return Municipality::factory()->create(['name' => $name, 'province' => $province]);
    }

    private function component()
    {
        return Livewire::actingAs($this->admin)->test(MunicipalityManagement::class);
    }

    // ── ACCESS ───────────────────────────────────────────────────────

    public function test_admin_can_open_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.municipalities'))->assertOk();
    }

    public function test_staff_cannot_open_page(): void
    {
        $this->actingAs($this->staff)->get(route('admin.municipalities'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.municipalities'))->assertRedirect(route('login'));
    }

    public function test_component_itself_rejects_non_admin(): void
    {
        Livewire::actingAs($this->staff)->test(MunicipalityManagement::class)->assertForbidden();
    }

    // ── LIST / SEARCH ────────────────────────────────────────────────

    public function test_lists_municipalities_with_province_and_barangay_count(): void
    {
        $virac = $this->makeMunicipality('Virac');
        Barangay::factory()->count(3)->create(['municipality_id' => $virac->id]);

        $this->component()->assertSee('Virac')->assertSee('Catanduanes')->assertSee('3');
    }

    public function test_search_by_municipality_name(): void
    {
        $this->makeMunicipality('Virac');
        $this->makeMunicipality('Bato');

        $this->component()
            ->set('search', 'Virac')
            ->assertSee('Virac')
            ->assertDontSee('Bato');
    }

    public function test_search_by_province(): void
    {
        $this->makeMunicipality('Virac', 'Catanduanes');
        $this->makeMunicipality('Legazpi', 'Albay');

        $this->component()
            ->set('search', 'Albay')
            ->assertSee('Legazpi')
            ->assertDontSee('Virac');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeMunicipality('Virac');

        $this->component()->set('search', 'zzzz-nothing')->assertSee('No municipality found');
    }

    public function test_changing_search_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeMunicipality(sprintf('Town %02d', $i));
        }

        $this->component()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('search', 'Town 1')
            ->assertSet('paginators.page', 1);
    }

    // ── CREATE ───────────────────────────────────────────────────────

    public function test_open_create_defaults_province_to_catanduanes(): void
    {
        $this->component()
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('municipalityName', '')
            ->assertSet('province', 'Catanduanes');
    }

    public function test_open_create_resets_state_left_by_a_previous_edit(): void
    {
        $m = $this->makeMunicipality('Virac', 'Albay');

        $this->component()
            ->call('openEdit', $m->id)
            ->call('openCreate')
            ->assertSet('editingId', null)
            ->assertSet('municipalityName', '')
            ->assertSet('province', 'Catanduanes');
    }

    public function test_create_saves_closes_modal_and_notifies(): void
    {
        $this->component()
            ->call('openCreate')
            ->set('municipalityName', '  Pandan  ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('municipalities', ['name' => 'Pandan', 'province' => 'Catanduanes']);
    }

    public function test_create_writes_audit_log(): void
    {
        $this->component()->set('municipalityName', 'Pandan')->call('save');

        $this->assertAudited('MUNICIPALITY_CREATED', Municipality::where('name', 'Pandan')->value('id'));
    }

    public function test_create_requires_name_and_province(): void
    {
        $this->component()
            ->set('municipalityName', '')
            ->set('province', '')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'required', 'province' => 'required'])
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseCount('municipalities', 0);
    }

    public function test_create_rejects_name_longer_than_100_chars(): void
    {
        $this->component()
            ->set('municipalityName', str_repeat('a', 101))
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'max']);
    }

    public function test_duplicate_name_in_same_province_is_rejected(): void
    {
        $this->makeMunicipality('Virac', 'Catanduanes');

        $this->component()
            ->set('municipalityName', 'Virac')
            ->set('province', 'Catanduanes')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'unique']);

        $this->assertSame(1, Municipality::where('name', 'Virac')->count());
    }

    public function test_same_name_in_different_province_is_allowed(): void
    {
        $this->makeMunicipality('San Miguel', 'Catanduanes');

        $this->component()
            ->set('municipalityName', 'San Miguel')
            ->set('province', 'Albay')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Municipality::where('name', 'San Miguel')->count());
    }

    // ── EDIT ─────────────────────────────────────────────────────────

    public function test_open_edit_loads_existing_values(): void
    {
        $m = $this->makeMunicipality('Virac', 'Catanduanes');

        $this->component()
            ->call('openEdit', $m->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $m->id)
            ->assertSet('municipalityName', 'Virac')
            ->assertSet('province', 'Catanduanes');
    }

    public function test_edit_updates_only_the_targeted_record(): void
    {
        $target = $this->makeMunicipality('Virac');
        $other  = $this->makeMunicipality('Bato');

        $this->component()
            ->call('openEdit', $target->id)
            ->set('municipalityName', 'Virac City')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('municipalities', ['id' => $target->id, 'name' => 'Virac City']);
        $this->assertDatabaseHas('municipalities', ['id' => $other->id, 'name' => 'Bato']);
    }

    public function test_edit_writes_audit_log(): void
    {
        $m = $this->makeMunicipality('Virac');

        $this->component()->call('openEdit', $m->id)->set('municipalityName', 'Virac 2')->call('save');

        $this->assertAudited('MUNICIPALITY_UPDATED', $m->id);
    }

    public function test_saving_without_changes_is_not_flagged_as_duplicate(): void
    {
        $m = $this->makeMunicipality('Virac');

        $this->component()->call('openEdit', $m->id)->call('save')->assertHasNoErrors();
    }

    public function test_edit_cannot_rename_into_an_existing_municipality(): void
    {
        $this->makeMunicipality('Virac');
        $bato = $this->makeMunicipality('Bato');

        $this->component()
            ->call('openEdit', $bato->id)
            ->set('municipalityName', 'Virac')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'unique']);

        $this->assertDatabaseHas('municipalities', ['id' => $bato->id, 'name' => 'Bato']);
    }

    public function test_edit_validation_failure_keeps_original_data(): void
    {
        $m = $this->makeMunicipality('Virac');

        $this->component()
            ->call('openEdit', $m->id)
            ->set('municipalityName', '')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'required']);

        $this->assertDatabaseHas('municipalities', ['id' => $m->id, 'name' => 'Virac']);
    }

    public function test_open_edit_on_missing_record_returns_404(): void
    {
        $this->component()->call('openEdit', 99999)->assertNotFound();
    }

    // ── DELETE ───────────────────────────────────────────────────────

    public function test_delete_removes_municipality_and_writes_audit_log(): void
    {
        $m = $this->makeMunicipality('Virac');

        $this->component()->call('delete', $m->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseMissing('municipalities', ['id' => $m->id]);
        $this->assertAudited('MUNICIPALITY_DELETED', $m->id);
    }

    /** WHY: barangays hang off municipalities; deleting the parent would orphan them. */
    public function test_delete_is_blocked_when_it_still_has_barangays(): void
    {
        $m = $this->makeMunicipality('Virac');
        Barangay::factory()->create(['municipality_id' => $m->id]);

        $this->component()->call('delete', $m->id)->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('municipalities', ['id' => $m->id]);
    }

    public function test_delete_only_removes_the_targeted_municipality(): void
    {
        $gone = $this->makeMunicipality('Virac');
        $kept = $this->makeMunicipality('Bato');

        $this->component()->call('delete', $gone->id);

        $this->assertDatabaseMissing('municipalities', ['id' => $gone->id]);
        $this->assertDatabaseHas('municipalities', ['id' => $kept->id]);
    }
}
