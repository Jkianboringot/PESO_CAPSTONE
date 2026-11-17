<?php

namespace Tests\Feature;

use App\Livewire\MunicipalityManagement;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Municipality CRUD and permissions.
 *
 * WHY: municipalities are the parent of every barangay. Deleting or
 * mis-saving one breaks the whole geographic hierarchy used in reports.
 *
 * NOTE ON PERMISSIONS
 * ───────────────────
 * Only admins may manage municipalities. mount() aborts with 403 for anyone
 * else, and Livewire::test() does not give a working component to a user that
 * mount() refuses. So staff and no-role tests call methods directly and check
 * for a 403 HttpException, and check the database afterwards.
 *
 * NOTE ON KNOWN BUGS
 * ──────────────────
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions.
 */
class MunicipalityManagementTest extends TestCase
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

    private function makeMunicipality(string $name, string $province = 'Catanduanes'): Municipality
    {
        return Municipality::create(['name' => $name, 'province' => $province]);
    }

    private function adminPage()
    {
        return Livewire::actingAs($this->admin)->test(MunicipalityManagement::class);
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
        $this->actingAs($this->admin)->get(route('admin.municipalities'))->assertOk();
    }

    public function test_staff_cannot_open_page(): void
    {
        $this->actingAs($this->staff)->get(route('admin.municipalities'))->assertForbidden();
    }

    public function test_user_without_role_cannot_open_page(): void
    {
        $this->actingAs($this->outsider)->get(route('admin.municipalities'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.municipalities'))->assertRedirect(route('login'));
    }

    // ── LIST / SEARCH ────────────────────────────────────────────────

    public function test_lists_municipalities_with_province(): void
    {
        $this->makeMunicipality('Virac', 'Catanduanes');

        $this->adminPage()->assertSee('Virac')->assertSee('Catanduanes');
    }

    public function test_lists_municipalities_in_name_order(): void
    {
        $this->makeMunicipality('Calatagan');
        $this->makeMunicipality('Virac');
        $this->makeMunicipality('Bato');

        $this->adminPage()->assertSeeInOrder(['Bato', 'Calatagan', 'Virac']);
    }

    public function test_barangay_count_is_correct_per_municipality(): void
    {
        $virac = $this->makeMunicipality('Virac');
        $bato  = $this->makeMunicipality('Bato');

        foreach (['Brgy A', 'Brgy B', 'Brgy C'] as $name) {
            Barangay::create(['name' => $name, 'municipality_id' => $virac->id]);
        }

        $rows = $this->adminPage()->viewData('viewMunicipalities');

        $this->assertSame(3, $rows->firstWhere('id', $virac->id)->barangays_count);
        $this->assertSame(0, $rows->firstWhere('id', $bato->id)->barangays_count);
    }

    public function test_shows_empty_state_when_there_are_no_municipalities(): void
    {
        $this->adminPage()->assertSee('No municipality found');
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

    public function test_search_ignores_surrounding_spaces(): void
    {
        $this->makeMunicipality('Virac');
        $this->makeMunicipality('Bato');

        $this->adminPage()
            ->set('search', '   Virac   ')
            ->assertSee('Virac')
            ->assertDontSee('Bato');
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->makeMunicipality('Virac');

        $this->adminPage()->set('search', 'zzzz-nothing')->assertSee('No municipality found');
    }

    /**
     * KNOWN BUG — expected to FAIL (SRCH-2).
     *
     * The search term goes into LIKE without escaping, so "%" matches every row.
     * Same root cause as SRCH-1 in the applicant and barangay modules.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin and open /admin/municipalities with at least one municipality.
     *   2. Type % in the search box.
     *   3. BUG: every municipality is listed. Expected: "No municipality found".
     *
     * FIX: in render(), escape !, %, and _ before building the LIKE pattern, and
     * add ESCAPE '!' to each LIKE clause (same fix as the barangay search).
     */
    public function test_search_treats_percent_sign_as_literal_text(): void
    {
        $this->makeMunicipality('Virac');
        $this->makeMunicipality('Bato');

        $this->adminPage()
            ->set('search', '%')
            ->assertDontSee('Virac')
            ->assertDontSee('Bato');
    }

    /**
     * KNOWN BUG — expected to FAIL (SRCH-2). Same cause as the % test.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin with at least one municipality.
     *   2. Type _ in the search box.
     *   3. BUG: municipalities are listed. Expected: "No municipality found".
     */
    public function test_search_treats_underscore_as_literal_text(): void
    {
        $this->makeMunicipality('Virac');

        $this->adminPage()
            ->set('search', '_')
            ->assertDontSee('Virac');
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

    public function test_list_shows_twenty_per_page_with_remainder_on_next_page(): void
    {
        foreach (range(1, 21) as $i) {
            $this->makeMunicipality(sprintf('Town %02d', $i));
        }

        $component = $this->adminPage();

        $this->assertCount(20, $component->viewData('viewMunicipalities'));

        $component->call('gotoPage', 2);

        $this->assertCount(1, $component->viewData('viewMunicipalities'));
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
            ->set('municipalityName', '  Pandan  ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('municipalities', ['name' => 'Pandan', 'province' => 'Catanduanes']);
    }

    public function test_create_saves_province_entered_by_user(): void
    {
        $this->adminPage()
            ->set('municipalityName', 'Legazpi')
            ->set('province', 'Albay')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('municipalities', ['name' => 'Legazpi', 'province' => 'Albay']);
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

    public function test_whitespace_only_name_is_rejected(): void
    {
        $this->adminPage()
            ->set('municipalityName', '     ')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'required']);

        $this->assertDatabaseCount('municipalities', 0);
    }

    public function test_whitespace_only_province_is_rejected(): void
    {
        $this->adminPage()
            ->set('municipalityName', 'Pandan')
            ->set('province', '     ')
            ->call('save')
            ->assertHasErrors(['province' => 'required']);

        $this->assertDatabaseCount('municipalities', 0);
    }

    public function test_create_rejects_name_longer_than_100_chars(): void
    {
        $this->adminPage()
            ->set('municipalityName', str_repeat('a', 101))
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'max']);
    }

    public function test_create_accepts_name_of_exactly_100_chars(): void
    {
        $name = str_repeat('a', 100);

        $this->adminPage()
            ->set('municipalityName', $name)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('municipalities', ['name' => $name]);
    }

    public function test_create_rejects_province_longer_than_100_chars(): void
    {
        $this->adminPage()
            ->set('municipalityName', 'Pandan')
            ->set('province', str_repeat('p', 101))
            ->call('save')
            ->assertHasErrors(['province' => 'max']);
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

    /**
     * KNOWN BUG — expected to FAIL (PROV-1).
     *
     * The unique rule checks the name as typed, including spaces, but the saved
     * value is trimmed. "  Virac  " passes the check, then saves as "Virac",
     * creating a real duplicate.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin and add municipality "Virac", province "Catanduanes".
     *   2. Add another with name "  Virac  " (leading and trailing spaces), province "Catanduanes".
     *   3. BUG: saved, and the list now shows two "Virac" rows.
     *      Expected: "The municipality name has already been taken."
     *
     * FIX: in save(), trim municipalityName and province before validate().
     */
    public function test_padded_name_cannot_create_a_duplicate(): void
    {
        $this->makeMunicipality('Virac', 'Catanduanes');

        $this->adminPage()
            ->call('openCreate')
            ->set('municipalityName', '  Virac  ')
            ->set('province', 'Catanduanes')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'unique']);

        $this->assertSame(1, Municipality::where('name', 'Virac')->count());
    }

    /**
     * KNOWN BUG — expected to FAIL (PROV-1). Same cause as the padded-name test.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Add "Virac" in province "Catanduanes".
     *   2. Add "Virac" again with province "  Catanduanes  " (spaces).
     *   3. BUG: saved as a second Virac in Catanduanes.
     */
    public function test_padded_province_cannot_create_a_duplicate(): void
    {
        $this->makeMunicipality('Virac', 'Catanduanes');

        $this->adminPage()
            ->set('municipalityName', 'Virac')
            ->set('province', '  Catanduanes  ')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'unique']);

        $this->assertSame(1, Municipality::where('name', 'Virac')->count());
    }

    public function test_failed_create_writes_no_audit_log(): void
    {
        $this->adminPage()->call('openCreate')->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'MUNICIPALITY_CREATED']);
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

    public function test_edit_can_change_province(): void
    {
        $m = $this->makeMunicipality('Bato', 'Catanduanes');

        $this->adminPage()
            ->call('openEdit', $m->id)
            ->set('province', 'Albay')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('municipalities', ['id' => $m->id, 'province' => 'Albay']);
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

    public function test_edit_cannot_move_into_a_province_that_already_has_the_name(): void
    {
        $this->makeMunicipality('Virac', 'Albay');
        $bato = $this->makeMunicipality('Virac', 'Catanduanes');

        $this->adminPage()
            ->call('openEdit', $bato->id)
            ->set('province', 'Albay')
            ->call('save')
            ->assertHasErrors(['municipalityName' => 'unique']);

        $this->assertDatabaseHas('municipalities', ['id' => $bato->id, 'province' => 'Catanduanes']);
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

    public function test_failed_edit_writes_no_audit_log(): void
    {
        $m = $this->makeMunicipality('Virac');

        $this->adminPage()
            ->call('openEdit', $m->id)
            ->set('municipalityName', '')
            ->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'MUNICIPALITY_UPDATED']);
    }

    public function test_edit_on_a_municipality_deleted_meanwhile_shows_error_toast(): void
    {
        $m = $this->makeMunicipality('Virac');

        $component = $this->adminPage()->call('openEdit', $m->id);

        // another admin deletes it while this modal is still open
        $m->delete();

        $component
            ->set('municipalityName', 'Renamed')
            ->call('save')
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('municipalities', ['name' => 'Renamed']);
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

    public function test_blocked_delete_writes_no_audit_log(): void
    {
        $m = $this->makeMunicipality('Virac');
        Barangay::create(['name' => 'San Jose', 'municipality_id' => $m->id]);

        $this->adminPage()->call('delete', $m->id);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'MUNICIPALITY_DELETED']);
    }

    public function test_delete_only_removes_the_targeted_municipality(): void
    {
        $gone = $this->makeMunicipality('Virac');
        $kept = $this->makeMunicipality('Bato');

        $this->adminPage()->call('delete', $gone->id);

        $this->assertDatabaseMissing('municipalities', ['id' => $gone->id]);
        $this->assertDatabaseHas('municipalities', ['id' => $kept->id]);
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
            ->set('municipalityName', 'New Town')
            ->call('save')
            ->assertHasNoErrors();

        $created = Municipality::where('name', 'New Town')->firstOrFail();

        $page->call('openEdit', $created->id)
            ->set('municipalityName', 'Renamed Town')
            ->call('save')
            ->assertHasNoErrors();

        $page->call('delete', $created->id);

        $this->assertDatabaseMissing('municipalities', ['id' => $created->id]);
    }

    // ── PERMISSIONS: STAFF ───────────────────────────────────────────

    public function test_staff_cannot_mount_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new MunicipalityManagement())->mount()),
            'Staff mount() should be refused with 403.'
        );
    }

    public function test_staff_cannot_render_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new MunicipalityManagement())->render()),
            'Staff render() should be refused with 403.'
        );
    }

    public function test_staff_cannot_create_municipality(): void
    {
        $this->actingAs($this->staff);

        $component = new MunicipalityManagement();
        $component->municipalityName = 'Sneaky';
        $component->province = 'Catanduanes';

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() should be refused with 403.'
        );

        $this->assertDatabaseMissing('municipalities', ['name' => 'Sneaky']);
    }

    public function test_staff_cannot_edit_municipality(): void
    {
        $m = $this->makeMunicipality('Virac');
        $this->actingAs($this->staff);

        $component = new MunicipalityManagement();
        $component->editingId = $m->id;
        $component->municipalityName = 'Hacked';
        $component->province = 'Catanduanes';

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() on an existing municipality should be refused with 403.'
        );

        $this->assertDatabaseHas('municipalities', ['id' => $m->id, 'name' => 'Virac']);
    }

    public function test_staff_cannot_delete_municipality(): void
    {
        $m = $this->makeMunicipality('Virac');
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new MunicipalityManagement())->delete($m->id, app(AuditLogService::class))),
            'Staff delete() should be refused with 403.'
        );

        $this->assertDatabaseHas('municipalities', ['id' => $m->id]);
    }

    /**
     * KNOWN GAP — expected to FAIL until openCreate() has a role check (GEO-1 equivalent).
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as staff and visit /admin/municipalities directly.
     *   2. Expected: 403. (The route guard blocks this, so use the component directly
     *      to see the gap: the modal opens for staff if the route check were bypassed.)
     *
     * FIX: add abort_if(!auth()->user()->hasRole('admin'), 403); as the first line of openCreate().
     */
    public function test_staff_cannot_open_create_form(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new MunicipalityManagement())->openCreate()),
            'Staff openCreate() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until openEdit() has a role check (GEO-1 equivalent).
     *
     * FIX: add abort_if(!auth()->user()->hasRole('admin'), 403); as the first line of openEdit().
     */
    public function test_staff_cannot_open_edit_form(): void
    {
        $m = $this->makeMunicipality('Virac');
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new MunicipalityManagement())->openEdit($m->id)),
            'Staff openEdit() should be refused with 403.'
        );
    }

    public function test_staff_writes_leave_no_audit_trail(): void
    {
        $m = $this->makeMunicipality('Virac');
        $this->actingAs($this->staff);

        $audit = app(AuditLogService::class);

        $create = new MunicipalityManagement();
        $create->municipalityName = 'Sneaky';
        $create->province = 'Catanduanes';
        $this->refusedWith403(fn () => $create->save($audit));

        $edit = new MunicipalityManagement();
        $edit->editingId = $m->id;
        $edit->municipalityName = 'Hacked';
        $edit->province = 'Catanduanes';
        $this->refusedWith403(fn () => $edit->save($audit));

        $this->refusedWith403(fn () => (new MunicipalityManagement())->delete($m->id, $audit));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->staff->id]);
    }

    // ── PERMISSIONS: NO ROLE ─────────────────────────────────────────

    public function test_user_without_role_cannot_mount_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new MunicipalityManagement())->mount()),
            'User without a role: mount() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_render_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new MunicipalityManagement())->render()),
            'User without a role: render() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_create_municipality(): void
    {
        $this->actingAs($this->outsider);

        $component = new MunicipalityManagement();
        $component->municipalityName = 'Sneaky';
        $component->province = 'Catanduanes';

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() should be refused with 403.'
        );

        $this->assertDatabaseMissing('municipalities', ['name' => 'Sneaky']);
    }

    public function test_user_without_role_cannot_edit_municipality(): void
    {
        $m = $this->makeMunicipality('Virac');
        $this->actingAs($this->outsider);

        $component = new MunicipalityManagement();
        $component->editingId = $m->id;
        $component->municipalityName = 'Hacked';
        $component->province = 'Catanduanes';

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() on an existing municipality should be refused with 403.'
        );

        $this->assertDatabaseHas('municipalities', ['id' => $m->id, 'name' => 'Virac']);
    }

    public function test_user_without_role_cannot_delete_municipality(): void
    {
        $m = $this->makeMunicipality('Virac');
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new MunicipalityManagement())->delete($m->id, app(AuditLogService::class))),
            'User without a role: delete() should be refused with 403.'
        );

        $this->assertDatabaseHas('municipalities', ['id' => $m->id]);
    }

    public function test_user_without_role_writes_leave_no_audit_trail(): void
    {
        $m = $this->makeMunicipality('Virac');
        $this->actingAs($this->outsider);

        $audit = app(AuditLogService::class);

        $create = new MunicipalityManagement();
        $create->municipalityName = 'Sneaky';
        $create->province = 'Catanduanes';
        $this->refusedWith403(fn () => $create->save($audit));

        $this->refusedWith403(fn () => (new MunicipalityManagement())->delete($m->id, $audit));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->outsider->id]);
    }
}