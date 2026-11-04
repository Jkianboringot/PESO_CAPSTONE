<?php

namespace Tests\Feature;

use App\Livewire\GeographicalManagement;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Barangay CRUD (GeographicalManagement).
 *
 * WHY: barangays feed applicant registration dropdowns and the geographic
 * reports. A wrong save or an unguarded delete orphans applicant records and
 * silently breaks DOLE reporting.
 */
class GeographicalManagementTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private Municipality $virac;
    private Municipality $bato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $this->virac = Municipality::create(['name' => 'Virac', 'province' => 'Catanduanes']);
        $this->bato  = Municipality::create(['name' => 'Bato', 'province' => 'Catanduanes']);
    }

    private function makeBarangay(string $name, ?Municipality $municipality = null): Barangay
    {
        return Barangay::create([
            'name'            => $name,
            'municipality_id' => ($municipality ?? $this->virac)->id,
        ]);
    }

    private function adminPage()
    {
        return Livewire::actingAs($this->admin)->test(GeographicalManagement::class);
    }

    // ── ACCESS ───────────────────────────────────────────────────────

    public function test_admin_can_open_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.geographical'))->assertOk();
    }

    public function test_staff_cannot_open_page(): void
    {
        $this->actingAs($this->staff)->get(route('admin.geographical'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.geographical'))->assertRedirect(route('login'));
    }

    /** Route middleware can be changed by accident; the component must guard itself too. */
    public function test_component_itself_rejects_non_admin(): void
    {
        Livewire::actingAs($this->staff)->test(GeographicalManagement::class)->assertForbidden();
    }

    // ── LIST / SEARCH ────────────────────────────────────────────────

    public function test_lists_barangays_with_their_municipality(): void
    {
        $this->makeBarangay('San Jose');

        $this->adminPage()->assertSee('San Jose')->assertSee('Virac');
    }

    public function test_search_by_barangay_name(): void
    {
        $this->makeBarangay('San Jose');
        $this->makeBarangay('Calatagan');

        $this->adminPage()
            ->set('search', 'San Jose')
            ->assertSee('San Jose')
            ->assertDontSee('Calatagan');
    }

    public function test_search_by_municipality_name(): void
    {
        $this->makeBarangay('San Jose', $this->virac);
        $this->makeBarangay('Poblacion Bato', $this->bato);

        $this->adminPage()
            ->set('search', 'Bato')
            ->assertSee('Poblacion Bato')
            ->assertDontSee('San Jose');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeBarangay('San Jose');

        $this->adminPage()
            ->set('search', 'zzzz-nothing')
            ->assertSee('No barangay found');
    }

    /** WHY: staying on page 2 after a narrower search would show an empty page. */
    public function test_changing_search_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeBarangay(sprintf('Barangay %02d', $i));
        }

        $this->adminPage()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('search', 'Barangay 1')
            ->assertSet('paginators.page', 1);
    }

    // ── CREATE ───────────────────────────────────────────────────────

    public function test_open_create_shows_empty_modal(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('barangayName', '')
            ->assertSet('municipalityID', '');
    }

    public function test_open_create_clears_leftovers_from_a_previous_edit(): void
    {
        $existing = $this->makeBarangay('San Jose');

        $this->adminPage()
            ->call('openEdit', $existing->id)
            ->call('openCreate')
            ->assertSet('editingId', null)
            ->assertSet('barangayName', '');
    }

    public function test_create_saves_barangay_closes_modal_and_notifies(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->set('barangayName', '  San Isidro  ')
            ->set('municipalityID', (string) $this->virac->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('notify', type: 'success');

        // trimmed, and attached to the right municipality
        $this->assertDatabaseHas('barangays', [
            'name'            => 'San Isidro',
            'municipality_id' => $this->virac->id,
        ]);
    }

    public function test_create_writes_audit_log(): void
    {
        $this->adminPage()
            ->set('barangayName', 'San Isidro')
            ->set('municipalityID', (string) $this->virac->id)
            ->call('save');

        $this->assertAudited('BARANGAY_CREATED', Barangay::where('name', 'San Isidro')->value('id'));
    }

    public function test_create_requires_name_and_municipality(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['barangayName' => 'required', 'municipalityID' => 'required'])
            ->assertSet('showModal', true)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseCount('barangays', 0);
    }

    public function test_create_rejects_unknown_municipality(): void
    {
        $this->adminPage()
            ->set('barangayName', 'Ghost')
            ->set('municipalityID', '99999')
            ->call('save')
            ->assertHasErrors(['municipalityID' => 'exists']);
    }

    public function test_create_rejects_name_longer_than_100_chars(): void
    {
        $this->adminPage()
            ->set('barangayName', str_repeat('a', 101))
            ->set('municipalityID', (string) $this->virac->id)
            ->call('save')
            ->assertHasErrors(['barangayName' => 'max']);
    }

    public function test_duplicate_name_in_same_municipality_is_rejected(): void
    {
        $this->makeBarangay('San Jose', $this->virac);

        $this->adminPage()
            ->set('barangayName', 'San Jose')
            ->set('municipalityID', (string) $this->virac->id)
            ->call('save')
            ->assertHasErrors(['barangayName' => 'unique']);

        $this->assertSame(1, Barangay::where('name', 'San Jose')->count());
    }

    /** Many real barangays share names across towns, so uniqueness is per-municipality. */
    public function test_same_name_in_different_municipality_is_allowed(): void
    {
        $this->makeBarangay('San Jose', $this->virac);

        $this->adminPage()
            ->set('barangayName', 'San Jose')
            ->set('municipalityID', (string) $this->bato->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Barangay::where('name', 'San Jose')->count());
    }

    // ── EDIT ─────────────────────────────────────────────────────────

    public function test_open_edit_loads_existing_values(): void
    {
        $b = $this->makeBarangay('San Jose', $this->bato);

        $this->adminPage()
            ->call('openEdit', $b->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $b->id)
            ->assertSet('barangayName', 'San Jose')
            ->assertSet('municipalityID', (string) $this->bato->id);
    }

    public function test_edit_updates_only_the_targeted_record(): void
    {
        $target = $this->makeBarangay('San Jose', $this->virac);
        $other  = $this->makeBarangay('Calatagan', $this->virac);

        $this->adminPage()
            ->call('openEdit', $target->id)
            ->set('barangayName', 'San Jose Renamed')
            ->set('municipalityID', (string) $this->bato->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('barangays', [
            'id' => $target->id, 'name' => 'San Jose Renamed', 'municipality_id' => $this->bato->id,
        ]);
        $this->assertDatabaseHas('barangays', [
            'id' => $other->id, 'name' => 'Calatagan', 'municipality_id' => $this->virac->id,
        ]);
    }

    public function test_edit_writes_audit_log(): void
    {
        $b = $this->makeBarangay('San Jose');

        $this->adminPage()
            ->call('openEdit', $b->id)
            ->set('barangayName', 'San Jose 2')
            ->call('save');

        $this->assertAudited('BARANGAY_UPDATED', $b->id);
    }

    /** The unique rule must ignore the record being edited, or no barangay could ever be re-saved. */
    public function test_saving_without_changing_the_name_is_not_flagged_as_duplicate(): void
    {
        $b = $this->makeBarangay('San Jose');

        $this->adminPage()
            ->call('openEdit', $b->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_edit_cannot_rename_into_an_existing_barangay(): void
    {
        $this->makeBarangay('San Jose');
        $other = $this->makeBarangay('Calatagan');

        $this->adminPage()
            ->call('openEdit', $other->id)
            ->set('barangayName', 'San Jose')
            ->call('save')
            ->assertHasErrors(['barangayName' => 'unique']);

        $this->assertDatabaseHas('barangays', ['id' => $other->id, 'name' => 'Calatagan']);
    }

    public function test_edit_validation_failure_keeps_original_data(): void
    {
        $b = $this->makeBarangay('San Jose');

        $this->adminPage()
            ->call('openEdit', $b->id)
            ->set('barangayName', '')
            ->call('save')
            ->assertHasErrors(['barangayName' => 'required']);

        $this->assertDatabaseHas('barangays', ['id' => $b->id, 'name' => 'San Jose']);
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

    public function test_delete_removes_barangay_and_writes_audit_log(): void
    {
        $b = $this->makeBarangay('San Jose');

        $this->adminPage()
            ->call('delete', $b->id)
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseMissing('barangays', ['id' => $b->id]);
        $this->assertAudited('BARANGAY_DELETED', $b->id);
    }

    /** WHY: deleting a barangay that applicants reference would orphan their address data. */
    public function test_delete_is_blocked_when_applicants_belong_to_it(): void
    {
        $b = $this->makeBarangay('San Jose');
        Applicant::factory()->create(['barangay_id' => $b->id, 'password' => bcrypt('password')]);

        $this->adminPage()
            ->call('delete', $b->id)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('barangays', ['id' => $b->id]);
    }

    public function test_delete_only_removes_the_targeted_barangay(): void
    {
        $gone = $this->makeBarangay('San Jose');
        $kept = $this->makeBarangay('Calatagan');

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseMissing('barangays', ['id' => $gone->id]);
        $this->assertDatabaseHas('barangays', ['id' => $kept->id]);
    }
}
