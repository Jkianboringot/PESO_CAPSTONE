<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Livewire\ApplicantManagement;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * PERMISSION TESTS FOR APPLICANT MANAGEMENT
 * ─────────────────────────────────────────
 * Who is allowed to touch applicant records:
 *   - admin:  everything on the applicants page, plus admin-only pages
 *   - staff:  everything on the applicants page, NOT admin-only pages
 *   - no role (any logged-in user without admin/staff): nothing
 *   - guest:  nothing (redirected to login)
 *
 * Two layers are tested separately:
 *   1. The ROUTE (role middleware in routes/web.php) blocks the page.
 *   2. The COMPONENT (canManage() / abort_if in ApplicantManagement) blocks
 *      each action. Livewire tests call the component directly and skip
 *      route middleware, so the component checks must be tested on their own.
 *
 * NOTE: Tests marked KNOWN GAP or KNOWN BUG are expected to FAIL. Do not
 * weaken these tests; fix the app code instead.
 */
class ApplicantManagementPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $staffUser;
    private User $outsider;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->adminUser = User::where('email', 'admin@peso-catanduanes.gov.ph')->firstOrFail();
        $this->staffUser = User::where('email', 'staff@peso-catanduanes.gov.ph')->firstOrFail();

        // A logged-in user with no admin or staff role
        $this->outsider = User::factory()->create();

        $municipality   = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay = Barangay::factory()->create([
            'municipality_id' => $municipality->id,
            'name'            => 'San Jose',
        ]);
    }

    private function makeApplicant(array $overrides = []): Applicant
    {
        $applicant = Applicant::factory()->create(array_merge([
            'barangay_id' => $this->barangay->id,
            'is_active'   => true,
            'status'      => 'Pending',
        ], $overrides));

        return $applicant->refresh();
    }

    // ─────────────────────────────────────────────────────────────────
    // ROUTE LEVEL: who can open the applicants page
    // ─────────────────────────────────────────────────────────────────

    public function test_admin_can_open_applicants_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('applicants'))
            ->assertOk();
    }

    public function test_staff_can_open_applicants_page(): void
    {
        $this->actingAs($this->staffUser)
            ->get(route('applicants'))
            ->assertOk();
    }

    public function test_user_without_role_gets_forbidden_on_applicants_page(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('applicants'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_away_from_applicants_page(): void
    {
        $this->get(route('applicants'))
            ->assertRedirect(route('login'));
    }

    // ─────────────────────────────────────────────────────────────────
    // ROUTE LEVEL: staff must not reach admin-only pages
    // ─────────────────────────────────────────────────────────────────

    public function test_staff_cannot_open_admin_users_page(): void
    {
        $this->actingAs($this->staffUser)
            ->get(route('admin.users'))
            ->assertForbidden();
    }

    public function test_staff_cannot_open_audit_logs_page(): void
    {
        $this->actingAs($this->staffUser)
            ->get(route('admin.audit-logs'))
            ->assertForbidden();
    }

    public function test_admin_can_open_audit_logs_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs'))
            ->assertOk();
    }

    public function test_user_without_role_cannot_open_admin_pages(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('admin.users'))
            ->assertForbidden();

        $this->actingAs($this->outsider)
            ->get(route('admin.audit-logs'))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────
    // COMPONENT LEVEL: user without a role cannot change anything
    // ─────────────────────────────────────────────────────────────────

    public function test_outsider_cannot_verify_applicant(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Pending']);

        Livewire::actingAs($this->outsider)
            ->test(ApplicantManagement::class)
            ->call('verified', $applicant->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('applicants', [
            'id'     => $applicant->id,
            'status' => 'Pending',
        ]);
    }

    public function test_outsider_cannot_deactivate_applicant(): void
    {
        $applicant = $this->makeApplicant(['is_active' => true]);

        Livewire::actingAs($this->outsider)
            ->test(ApplicantManagement::class)
            ->call('deactivate', $applicant->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'is_active' => true,
        ]);
    }

    /**
     * Inactive rows use status 'Pending' because the applicants.status CHECK
     * constraint rejects 'Inactive'. See the ROOT CAUSE note in
     * ApplicantManagementTest::test_deactivation_creates_audit_log.
     */
    public function test_outsider_cannot_activate_applicant(): void
    {
        $applicant = $this->makeApplicant(['is_active' => false, 'status' => 'Pending']);

        Livewire::actingAs($this->outsider)
            ->test(ApplicantManagement::class)
            ->call('activate', $applicant->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'is_active' => false,
            'status'    => 'Pending',
        ]);
    }

    public function test_outsider_cannot_save_edit(): void
    {
        $applicant = $this->makeApplicant(['last_name' => 'Santos']);

        Livewire::actingAs($this->outsider)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.last_name', 'Hacked')
            ->call('saveEdit')
            ->assertDispatched('notify');

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'last_name' => 'Santos',
        ]);
    }

    /**
     * openView() calls abort(403) for users without a role. Livewire does not
     * rethrow that in tests, so check the observable result: the modal stays closed.
     */
    public function test_outsider_cannot_open_read_only_view(): void
    {
        $applicant = $this->makeApplicant();

        Livewire::actingAs($this->outsider)
            ->test(ApplicantManagement::class)
            ->call('openView', $applicant->id)
            ->assertSet('showView', false);
    }

    public function test_outsider_actions_leave_no_audit_trail(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Pending', 'is_active' => true]);

        Livewire::actingAs($this->outsider)
            ->test(ApplicantManagement::class)
            ->call('verified', $applicant->id)
            ->call('deactivate', $applicant->id)
            ->set('editData.last_name', 'Hacked')
            ->call('openEdit', $applicant->id)
            ->call('saveEdit');

        // Nothing should be attributed to the outsider
        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $this->outsider->id,
        ]);
    }

    /**
     * KNOWN GAP — expected to FAIL.
     *
     * The route blocks an outsider from the page, but ApplicantManagement::render()
     * has no authorization check, so the component still loads the applicant
     * table for any user who reaches it. Livewire tests skip route middleware,
     * so this shows the gap directly.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   The route blocks this, so you need to bypass it to see the gap:
     *   1. Log in as a user with no role (create one in tinker:
     *      App\Models\User::factory()->create(['email' => 'norole@test.com', 'password' => bcrypt('password')]))
     *   2. Visit /applicants. You should get a 403 page. This is the route guard working.
     *   3. BUG: the route guard is the only thing protecting the data. Any future route,
     *      alias, or Livewire endpoint that mounts this component without the middleware
     *      would expose the table.
     *
     * FIX: add an authorization check at the top of render() that returns
     * abort(403) or an empty result when canManage() is false.
     */
    public function test_outsider_cannot_see_applicant_records_in_component(): void
    {
        $applicant = $this->makeApplicant();

        Livewire::actingAs($this->outsider)
            ->test(ApplicantManagement::class)
            ->assertDontSee($applicant->reference_id);
    }

    // ─────────────────────────────────────────────────────────────────
    // COMPONENT LEVEL: staff and admin can do what they are allowed to
    // ─────────────────────────────────────────────────────────────────

    public function test_staff_can_verify_applicant(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Pending']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('verified', $applicant->id);

        $this->assertDatabaseHas('applicants', [
            'id'     => $applicant->id,
            'status' => 'Verified',
        ]);
    }

    /**
     * KNOWN BUG — expected to FAIL for staff and admin.
     *
     * deactivate() sets status = 'Inactive', which the applicants.status CHECK
     * constraint rejects. The exception is caught in deactivate()'s try/catch,
     * so is_active stays true and an error toast is shown. Same root cause as
     * ApplicantManagementTest::test_deactivation_creates_audit_log.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as staff (staff@peso-catanduanes.gov.ph) and go to /applicants.
     *   2. Click Deactivate on any Active row and confirm.
     *   3. BUG: a red error toast appears ("Could not deactivate the applicant.")
     *      and the row stays in the Active tab.
     *      Expected: a green "Applicant deactivated." toast and the row moves to Inactive.
     *   4. Check storage/logs/laravel.log for "CHECK constraint failed: status".
     *
     * FIX: allow 'Inactive' in the applicants.status CHECK constraint, or change
     * deactivate() to update only is_active.
     */
    public function test_staff_can_deactivate_applicant(): void
    {
        $applicant = $this->makeApplicant(['is_active' => true]);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('deactivate', $applicant->id);

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'is_active' => false,
        ]);
    }

    public function test_staff_can_open_read_only_view(): void
    {
        $applicant = $this->makeApplicant();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openView', $applicant->id)
            ->assertSet('showView', true)
            ->assertSet('viewingId', $applicant->id);
    }

    public function test_admin_can_verify_applicant(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Pending']);

        Livewire::actingAs($this->adminUser)
            ->test(ApplicantManagement::class)
            ->call('verified', $applicant->id);

        $this->assertDatabaseHas('applicants', [
            'id'     => $applicant->id,
            'status' => 'Verified',
        ]);
    }

    /**
     * KNOWN BUG — expected to FAIL for admin. Same cause as
     * test_staff_can_deactivate_applicant; see the note above it.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin (admin@peso-catanduanes.gov.ph) and go to /applicants.
     *   2. Click Deactivate on any Active row and confirm.
     *   3. BUG: red error toast, row stays Active.
     *      Expected: green toast, row moves to Inactive.
     */
    public function test_admin_can_deactivate_applicant(): void
    {
        $applicant = $this->makeApplicant(['is_active' => true]);

        Livewire::actingAs($this->adminUser)
            ->test(ApplicantManagement::class)
            ->call('deactivate', $applicant->id);

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_save_edit(): void
    {
        $applicant = $this->makeApplicant(['last_name' => 'Santos']);

        Livewire::actingAs($this->adminUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.last_name', 'Santos-Reyes')
            ->call('saveEdit');

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'last_name' => 'Santos-Reyes',
        ]);
    }
}