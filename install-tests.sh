#!/usr/bin/env bash
# Run from your Laravel project root:  bash install-tests.sh
set -e

mkdir -p "$(dirname 'tests/Feature/Concerns/SetsUpAdminAndStaff.php')"
cat > 'tests/Feature/Concerns/SetsUpAdminAndStaff.php' <<'CRUD_FILE_EOF'
<?php

namespace Tests\Feature\Concerns;

use App\Models\AuditLog;
use Spatie\Permission\Models\Role;
use App\Models\User;

/**
 * Shared setup for the admin-only CRUD tests.
 *
 * The app uses Spatie-style roles (hasRole / syncRoles / role:admin middleware),
 * so users get roles through assignRole(), NOT a role_id column.
 */
trait SetsUpAdminAndStaff
{
    protected User $admin;
    protected User $staff;

    protected function setUpUsers(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('staff', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create();
        $this->staff->assignRole('staff');
    }

    /**
     * Assert an audit row exists. Checks action + actor (+ optional model id)
     * instead of model_type, which depends on how AuditLogService stores it.
     */
    protected function assertAudited(string $action, ?int $modelId = null): void
    {
        $query = AuditLog::where('action', $action)->where('user_id', $this->admin->id);

        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }

        $this->assertTrue($query->exists(), "Expected audit log entry [{$action}] was not written.");
    }
}
CRUD_FILE_EOF
echo "created tests/Feature/Concerns/SetsUpAdminAndStaff.php"

mkdir -p "$(dirname 'tests/Feature/GeographicalManagementTest.php')"
cat > 'tests/Feature/GeographicalManagementTest.php' <<'CRUD_FILE_EOF'
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
CRUD_FILE_EOF
echo "created tests/Feature/GeographicalManagementTest.php"

mkdir -p "$(dirname 'tests/Feature/MunicipalityManagementTest.php')"
cat > 'tests/Feature/MunicipalityManagementTest.php' <<'CRUD_FILE_EOF'
<?php

namespace Tests\Feature;

use App\Livewire\MunicipalityManagement;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        return Municipality::create(['name' => $name, 'province' => $province]);
    }

    private function adminPage()
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
        foreach (['Brgy A', 'Brgy B', 'Brgy C'] as $name) {
            Barangay::create(['name' => $name, 'municipality_id' => $virac->id]);
        }

        $this->adminPage()->assertSee('Virac')->assertSee('Catanduanes')->assertSee('3');
    }

    public function test_search_by_municipality_name(): void
    {
        $this->makeMunicipality('Virac');
        $this->makeMunicipality('Bato');

        $this->adminPage()
            ->set('search', 'Virac')
            ->assertSee('Virac')
            ->assertDontSee('Bato');
    }

    public function test_search_by_province(): void
    {
        $this->makeMunicipality('Virac', 'Catanduanes');
        $this->makeMunicipality('Legazpi', 'Albay');

        $this->adminPage()
            ->set('search', 'Albay')
            ->assertSee('Legazpi')
            ->assertDontSee('Virac');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeMunicipality('Virac');

        $this->adminPage()->set('search', 'zzzz-nothing')->assertSee('No municipality found');
    }

    public function test_changing_search_resets_pagination_to_page_one(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeMunicipality(sprintf('Town %02d', $i));
        }

        $this->adminPage()
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('search', 'Town 1')
            ->assertSet('paginators.page', 1);
    }

    // ── CREATE ───────────────────────────────────────────────────────

    public function test_open_create_defaults_province_to_catanduanes(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null)
            ->assertSet('municipalityName', '')
            ->assertSet('province', 'Catanduanes');
    }

    public function test_open_create_resets_state_left_by_a_previous_edit(): void
    {
        $m = $this->makeMunicipality('Virac', 'Albay');

        $this->adminPage()
            ->call('openEdit', $m->id)
            ->call('openCreate')
            ->assertSet('editingId', null)
            ->assertSet('municipalityName', '')
            ->assertSet('province', 'Catanduanes');
    }

    public function test_create_saves_closes_modal_and_notifies(): void
    {
        $this->adminPage()
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
        $this->adminPage()->set('municipalityName', 'Pandan')->call('save');

        $this->assertAudited('MUNICIPALITY_CREATED', Municipality::where('name', 'Pandan')->value('id'));
    }

    public function test_create_requires_name_and_province(): void
    {
        $this->adminPage()
            ->set('municipalityName', '')
            ->set('province', '')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'required', 'province' => 'required'])
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseCount('municipalities', 0);
    }

    public function test_create_rejects_name_longer_than_100_chars(): void
    {
        $this->adminPage()
            ->set('municipalityName', str_repeat('a', 101))
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'max']);
    }

    public function test_duplicate_name_in_same_province_is_rejected(): void
    {
        $this->makeMunicipality('Virac', 'Catanduanes');

        $this->adminPage()
            ->set('municipalityName', 'Virac')
            ->set('province', 'Catanduanes')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'unique']);

        $this->assertSame(1, Municipality::where('name', 'Virac')->count());
    }

    public function test_same_name_in_different_province_is_allowed(): void
    {
        $this->makeMunicipality('San Miguel', 'Catanduanes');

        $this->adminPage()
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

        $this->adminPage()
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

        $this->adminPage()
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

        $this->adminPage()->call('openEdit', $m->id)->set('municipalityName', 'Virac 2')->call('save');

        $this->assertAudited('MUNICIPALITY_UPDATED', $m->id);
    }

    public function test_saving_without_changes_is_not_flagged_as_duplicate(): void
    {
        $m = $this->makeMunicipality('Virac');

        $this->adminPage()->call('openEdit', $m->id)->call('save')->assertHasNoErrors();
    }

    public function test_edit_cannot_rename_into_an_existing_municipality(): void
    {
        $this->makeMunicipality('Virac');
        $bato = $this->makeMunicipality('Bato');

        $this->adminPage()
            ->call('openEdit', $bato->id)
            ->set('municipalityName', 'Virac')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'unique']);

        $this->assertDatabaseHas('municipalities', ['id' => $bato->id, 'name' => 'Bato']);
    }

    public function test_edit_validation_failure_keeps_original_data(): void
    {
        $m = $this->makeMunicipality('Virac');

        $this->adminPage()
            ->call('openEdit', $m->id)
            ->set('municipalityName', '')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'required']);

        $this->assertDatabaseHas('municipalities', ['id' => $m->id, 'name' => 'Virac']);
    }

    public function test_open_edit_on_missing_record_returns_404(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->adminPage()->call('openEdit', 99999);
    }

    // ── DELETE ───────────────────────────────────────────────────────

    public function test_delete_removes_municipality_and_writes_audit_log(): void
    {
        $m = $this->makeMunicipality('Virac');

        $this->adminPage()->call('delete', $m->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseMissing('municipalities', ['id' => $m->id]);
        $this->assertAudited('MUNICIPALITY_DELETED', $m->id);
    }

    /** WHY: barangays hang off municipalities; deleting the parent would orphan them. */
    public function test_delete_is_blocked_when_it_still_has_barangays(): void
    {
        $m = $this->makeMunicipality('Virac');
        Barangay::create(['name' => 'San Jose', 'municipality_id' => $m->id]);

        $this->adminPage()->call('delete', $m->id)->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('municipalities', ['id' => $m->id]);
    }

    public function test_delete_only_removes_the_targeted_municipality(): void
    {
        $gone = $this->makeMunicipality('Virac');
        $kept = $this->makeMunicipality('Bato');

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseMissing('municipalities', ['id' => $gone->id]);
        $this->assertDatabaseHas('municipalities', ['id' => $kept->id]);
    }
}
CRUD_FILE_EOF
echo "created tests/Feature/MunicipalityManagementTest.php"

mkdir -p "$(dirname 'tests/Feature/SkillCategoryManagementTest.php')"
cat > 'tests/Feature/SkillCategoryManagementTest.php' <<'CRUD_FILE_EOF'
<?php

namespace Tests\Feature;

use App\Livewire\SkillCategoryManagement;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    private function adminPage()
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

        $this->adminPage()->assertSee('Welding')->assertSee('Level 3')->assertSee('2');
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

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeCategory('Welding');

        $this->adminPage()->set('search', 'zzzz-nothing')->assertSee('No category found');
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

    public function test_create_rejects_overlong_fields(): void
    {
        $this->adminPage()
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

        $this->adminPage()
            ->set('name', 'Welding')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, SkillCategory::where('name', 'Welding')->count());
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

    public function test_open_edit_on_missing_record_returns_404(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->adminPage()->call('openEdit', 99999);
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

    public function test_delete_only_removes_the_targeted_category(): void
    {
        $gone = $this->makeCategory('Welding');
        $kept = $this->makeCategory('Cooking');

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseMissing('skill_categories', ['id' => $gone->id]);
        $this->assertDatabaseHas('skill_categories', ['id' => $kept->id]);
    }
}
CRUD_FILE_EOF
echo "created tests/Feature/SkillCategoryManagementTest.php"

mkdir -p "$(dirname 'tests/Feature/SkillManagementTest.php')"
cat > 'tests/Feature/SkillManagementTest.php' <<'CRUD_FILE_EOF'
<?php

namespace Tests\Feature;

use App\Livewire\SkillManagement;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    private function adminPage()
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

        $this->adminPage()->assertSee('Arc Welding')->assertSee('Welding')->assertSee('Level 3');
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

    public function test_category_filter_shows_only_that_categorys_skills(): void
    {
        $this->makeSkill('Arc Welding', $this->welding);
        $this->makeSkill('Baking Bread', $this->cooking);

        $this->adminPage()
            ->set('categoryFilter', (string) $this->cooking->id)
            ->assertSee('Baking Bread')
            ->assertDontSee('Arc Welding');
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

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeSkill('Arc Welding');

        $this->adminPage()->set('search', 'zzzz-nothing')->assertSee('No skill found');
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

    public function test_create_rejects_unknown_category(): void
    {
        $this->adminPage()
            ->set('name', 'Ghost Skill')
            ->set('skill_category_id', '99999')
            ->call('save')
            ->assertHasErrors(['skill_category_id' => 'exists']);
    }

    public function test_create_rejects_name_longer_than_255_chars(): void
    {
        $this->adminPage()
            ->set('name', str_repeat('a', 256))
            ->set('skill_category_id', (string) $this->welding->id)
            ->call('save')
            ->assertHasErrors(['name' => 'max']);
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

    public function test_open_edit_on_missing_record_returns_404(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->adminPage()->call('openEdit', 99999);
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

    public function test_delete_only_removes_the_targeted_skill(): void
    {
        $gone = $this->makeSkill('Arc Welding');
        $kept = $this->makeSkill('MIG Welding');

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseMissing('skills', ['id' => $gone->id]);
        $this->assertDatabaseHas('skills', ['id' => $kept->id]);
    }
}
CRUD_FILE_EOF
echo "created tests/Feature/SkillManagementTest.php"