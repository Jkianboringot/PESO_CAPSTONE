<?php

namespace Tests\Feature;

use App\Livewire\GeographicalManagement;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Barangay CRUD (GeographicalManagement).
 *
 * WHY: barangays feed applicant registration dropdowns and the geographic
 * reports. A wrong save or an unguarded delete orphans applicant records and
 * silently breaks DOLE reporting.
 *
 * NOTE ON PERMISSIONS
 * ───────────────────
 * Only admins may manage barangays. mount() aborts with 403 for anyone else,
 * and Livewire::test() does not give a working component to a user that mount()
 * refuses. So the staff and no-role tests call the write methods directly and
 * check that they throw 403 before touching the database.
 *
 * NOTE ON KNOWN BUGS
 * ──────────────────
 * Tests marked KNOWN BUG are expected to FAIL until the app code is fixed.
 * Do not weaken their assertions.
 */
class GeographicalManagementTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private Municipality $virac;
    private Municipality $bato;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        // A logged-in user with no admin or staff role
        $this->outsider = User::factory()->create();

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

    /**
     * Returns true only when the callable throws an HttpException with status 403.
     * Returns false if it runs without throwing, or throws any other exception type.
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
        $this->actingAs($this->admin)->get(route('admin.geographical'))->assertOk();
    }

    public function test_staff_cannot_open_page(): void
    {
        $this->actingAs($this->staff)->get(route('admin.geographical'))->assertForbidden();
    }

    public function test_user_without_role_cannot_open_page(): void
    {
        $this->actingAs($this->outsider)->get(route('admin.geographical'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.geographical'))->assertRedirect(route('login'));
    }

    // ── LIST / SEARCH ────────────────────────────────────────────────

    public function test_lists_barangays_with_their_municipality(): void
    {
        $this->makeBarangay('San Jose');

        $this->adminPage()->assertSee('San Jose')->assertSee('Virac');
    }

    public function test_lists_barangays_in_name_order(): void
    {
        $this->makeBarangay('Calatagan');
        $this->makeBarangay('San Jose');
        $this->makeBarangay('Antipolo');

        $this->adminPage()->assertSeeInOrder(['Antipolo', 'Calatagan', 'San Jose']);
    }

    public function test_shows_empty_state_when_there_are_no_barangays(): void
    {
        $this->adminPage()->assertSee('No barangay found');
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

    public function test_search_by_province_name(): void
    {
        $albay = Municipality::create(['name' => 'Legazpi', 'province' => 'Albay']);
        $this->makeBarangay('San Jose', $this->virac);
        $this->makeBarangay('Bonga', $albay);

        $this->adminPage()
            ->set('search', 'Albay')
            ->assertSee('Bonga')
            ->assertDontSee('San Jose');
    }

    public function test_search_ignores_surrounding_spaces(): void
    {
        $this->makeBarangay('San Jose');
        $this->makeBarangay('Calatagan');

        $this->adminPage()
            ->set('search', '   San Jose   ')
            ->assertSee('San Jose')
            ->assertDontSee('Calatagan');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeBarangay('San Jose');

        $this->adminPage()
            ->set('search', 'zzzz-nothing')
            ->assertSee('No barangay found');
    }

    /**
     * KNOWN BUG — expected to FAIL.
     *
     * The search term goes into LIKE without escaping, so "%" matches every row.
     * Same root cause as SRCH-1 in the applicant module.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin and open /admin/geographical with at least one barangay.
     *   2. Type % in the search box.
     *   3. BUG: every barangay is listed. Expected: "No barangay found".
     *
     * FIX: in render(), escape !, %, and _ before building the LIKE pattern,
     * and add ESCAPE '!' to each LIKE clause (see the applicant SRCH-1 fix).
     */
    public function test_search_treats_percent_sign_as_literal_text(): void
    {
        $this->makeBarangay('San Jose');
        $this->makeBarangay('Calatagan');

        $this->adminPage()
            ->set('search', '%')
            ->assertDontSee('San Jose')
            ->assertDontSee('Calatagan');
    }

    /**
     * KNOWN BUG — expected to FAIL. Same cause as the % test.
     *
     * "_" is a single-character LIKE wildcard, so it matches any name with
     * at least one character.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin with at least one barangay.
     *   2. Type _ in the search box.
     *   3. BUG: barangays are listed. Expected: "No barangay found".
     */
    public function test_search_treats_underscore_as_literal_text(): void
    {
        $this->makeBarangay('San Jose');

        $this->adminPage()
            ->set('search', '_')
            ->assertDontSee('San Jose');
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

    public function test_list_shows_twenty_per_page_with_remainder_on_next_page(): void
    {
        foreach (range(1, 21) as $i) {
            $this->makeBarangay(sprintf('Barangay %02d', $i));
        }

        $component = $this->adminPage();

        $this->assertCount(20, $component->viewData('viewBarangays'));

        $component->call('gotoPage', 2);

        $this->assertCount(1, $component->viewData('viewBarangays'));
    }

    public function test_list_shows_how_many_applicants_each_barangay_has(): void
    {
        $b = $this->makeBarangay('San Jose');
        Applicant::factory()->count(3)->create(['barangay_id' => $b->id, 'password' => bcrypt('password')]);

        $component = $this->adminPage();

        $this->assertSame(3, $component->viewData('viewBarangays')->firstWhere('id', $b->id)->applicants_count);
    }

    /**
     * The municipality dropdown is inside the Add/Edit modal, which only
     * renders while showModal is true. The modal must be opened first.
     */
    public function test_municipality_dropdown_lists_all_municipalities(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->assertSee('Virac')
            ->assertSee('Bato');
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

    public function test_open_create_clears_errors_from_a_failed_save(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors()
            ->call('openCreate')
            ->assertHasNoErrors();
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

    public function test_whitespace_only_name_is_rejected(): void
    {
        $this->adminPage()
            ->set('barangayName', '     ')
            ->set('municipalityID', (string) $this->virac->id)
            ->call('save')
            ->assertHasErrors(['barangayName' => 'required']);

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

    public function test_create_rejects_non_numeric_municipality(): void
    {
        $this->adminPage()
            ->set('barangayName', 'Ghost')
            ->set('municipalityID', 'abc')
            ->call('save')
            ->assertHasErrors(['municipalityID']);

        $this->assertDatabaseCount('barangays', 0);
    }

    public function test_create_rejects_name_longer_than_100_chars(): void
    {
        $this->adminPage()
            ->set('barangayName', str_repeat('a', 101))
            ->set('municipalityID', (string) $this->virac->id)
            ->call('save')
            ->assertHasErrors(['barangayName' => 'max']);
    }

    public function test_create_accepts_name_of_exactly_100_chars(): void
    {
        $name = str_repeat('a', 100);

        $this->adminPage()
            ->set('barangayName', $name)
            ->set('municipalityID', (string) $this->virac->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('barangays', ['name' => $name]);
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

    public function test_failed_create_writes_no_audit_log(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'BARANGAY_CREATED']);
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

    public function test_edit_trims_spaces_from_name(): void
    {
        $b = $this->makeBarangay('San Jose');

        $this->adminPage()
            ->call('openEdit', $b->id)
            ->set('barangayName', '  San Jose Trimmed  ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('barangays', ['id' => $b->id, 'name' => 'San Jose Trimmed']);
    }

    public function test_edit_can_move_barangay_to_another_municipality_with_same_name(): void
    {
        $this->makeBarangay('San Jose', $this->bato);
        $virac = $this->makeBarangay('San Jose', $this->virac);

        $this->adminPage()
            ->call('openEdit', $virac->id)
            ->set('municipalityID', (string) $this->bato->id)
            ->call('save')
            ->assertHasErrors(['barangayName' => 'unique']);

        $this->assertDatabaseHas('barangays', ['id' => $virac->id, 'municipality_id' => $this->virac->id]);
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

    public function test_edit_with_cleared_municipality_is_rejected(): void
    {
        $b = $this->makeBarangay('San Jose', $this->virac);

        $this->adminPage()
            ->call('openEdit', $b->id)
            ->set('municipalityID', '')
            ->call('save')
            ->assertHasErrors(['municipalityID' => 'required']);

        $this->assertDatabaseHas('barangays', ['id' => $b->id, 'municipality_id' => $this->virac->id]);
    }

    public function test_failed_edit_writes_no_audit_log(): void
    {
        $b = $this->makeBarangay('San Jose');

        $this->adminPage()
            ->call('openEdit', $b->id)
            ->set('barangayName', '')
            ->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'BARANGAY_UPDATED']);
    }

    public function test_edit_on_a_barangay_deleted_meanwhile_shows_error_toast(): void
    {
        $b = $this->makeBarangay('San Jose');

        $component = $this->adminPage()->call('openEdit', $b->id);

        // another admin deletes it while this modal is still open
        $b->delete();

        $component
            ->set('barangayName', 'Renamed')
            ->call('save')
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('barangays', ['name' => 'Renamed']);
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

    public function test_blocked_delete_writes_no_audit_log(): void
    {
        $b = $this->makeBarangay('San Jose');
        Applicant::factory()->create(['barangay_id' => $b->id, 'password' => bcrypt('password')]);

        $this->adminPage()->call('delete', $b->id);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'BARANGAY_DELETED']);
    }

    public function test_delete_only_removes_the_targeted_barangay(): void
    {
        $gone = $this->makeBarangay('San Jose');
        $kept = $this->makeBarangay('Calatagan');

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseMissing('barangays', ['id' => $gone->id]);
        $this->assertDatabaseHas('barangays', ['id' => $kept->id]);
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

        $page->set('barangayName', 'New Barangay')
            ->set('municipalityID', (string) $this->virac->id)
            ->call('save')
            ->assertHasNoErrors();

        $created = Barangay::where('name', 'New Barangay')->firstOrFail();

        $page->call('openEdit', $created->id)
            ->set('barangayName', 'Renamed Barangay')
            ->call('save')
            ->assertHasNoErrors();

        $page->call('delete', $created->id);

        $this->assertDatabaseMissing('barangays', ['id' => $created->id]);
    }

    // ── PERMISSIONS: STAFF ───────────────────────────────────────────

    public function test_staff_cannot_mount_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new GeographicalManagement())->mount()),
            'Staff mount() should be refused with 403.'
        );
    }

    public function test_staff_cannot_create_barangay(): void
    {
        $this->actingAs($this->staff);

        $component = new GeographicalManagement();
        $component->barangayName = 'Sneaky';
        $component->municipalityID = (string) $this->virac->id;

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() should be refused with 403.'
        );

        $this->assertDatabaseCount('barangays', 0);
    }

    public function test_staff_cannot_edit_barangay(): void
    {
        $b = $this->makeBarangay('San Jose');
        $this->actingAs($this->staff);

        $component = new GeographicalManagement();
        $component->editingId = $b->id;
        $component->barangayName = 'Hacked';
        $component->municipalityID = (string) $this->virac->id;

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() on an existing barangay should be refused with 403.'
        );

        $this->assertDatabaseHas('barangays', ['id' => $b->id, 'name' => 'San Jose']);
    }

    public function test_staff_cannot_delete_barangay(): void
    {
        $b = $this->makeBarangay('San Jose');
        $this->actingAs($this->staff);

        $component = new GeographicalManagement();

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->delete($b->id, app(AuditLogService::class))),
            'Staff delete() should be refused with 403.'
        );

        $this->assertDatabaseHas('barangays', ['id' => $b->id]);
    }

    public function test_staff_writes_leave_no_audit_trail(): void
    {
        $b = $this->makeBarangay('San Jose');
        $this->actingAs($this->staff);

        $audit = app(AuditLogService::class);

        $create = new GeographicalManagement();
        $create->barangayName = 'Sneaky';
        $create->municipalityID = (string) $this->virac->id;
        $this->refusedWith403(fn () => $create->save($audit));

        $edit = new GeographicalManagement();
        $edit->editingId = $b->id;
        $edit->barangayName = 'Hacked';
        $edit->municipalityID = (string) $this->virac->id;
        $this->refusedWith403(fn () => $edit->save($audit));

        $this->refusedWith403(fn () => (new GeographicalManagement())->delete($b->id, $audit));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->staff->id]);
    }

    // ── PERMISSIONS: NO ROLE ─────────────────────────────────────────

    public function test_user_without_role_cannot_mount_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new GeographicalManagement())->mount()),
            'User without a role: mount() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_create_barangay(): void
    {
        $this->actingAs($this->outsider);

        $component = new GeographicalManagement();
        $component->barangayName = 'Sneaky';
        $component->municipalityID = (string) $this->virac->id;

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() should be refused with 403.'
        );

        $this->assertDatabaseCount('barangays', 0);
    }

    public function test_user_without_role_cannot_edit_barangay(): void
    {
        $b = $this->makeBarangay('San Jose');
        $this->actingAs($this->outsider);

        $component = new GeographicalManagement();
        $component->editingId = $b->id;
        $component->barangayName = 'Hacked';
        $component->municipalityID = (string) $this->virac->id;

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() on an existing barangay should be refused with 403.'
        );

        $this->assertDatabaseHas('barangays', ['id' => $b->id, 'name' => 'San Jose']);
    }

    public function test_user_without_role_cannot_delete_barangay(): void
    {
        $b = $this->makeBarangay('San Jose');
        $this->actingAs($this->outsider);

        $component = new GeographicalManagement();

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->delete($b->id, app(AuditLogService::class))),
            'User without a role: delete() should be refused with 403.'
        );

        $this->assertDatabaseHas('barangays', ['id' => $b->id]);
    }
}