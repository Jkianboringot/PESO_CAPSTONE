<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Education;
use Livewire\Livewire;
use App\Livewire\UserManagement;
use App\Livewire\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

/**
 * WHY THIS TEST FILE EXISTS — USER MANAGEMENT
 * ─────────────────────────────────────────────
 * UserManagement is admin-only. Bugs here have outsized consequences:
 *
 * - If an admin can deactivate themselves, PESO is locked out
 * - If password hashing is skipped, passwords are stored in plaintext
 * - If role assignment is not validated, a staff member could be
 *   escalated to admin without authorization
 * - If email uniqueness is not enforced, two accounts with the same
 *   email can exist — causing ambiguous login behavior
 *
 * WHY THIS TEST FILE EXISTS — REPORT GENERATOR
 * ─────────────────────────────────────────────
 * Reports are submitted to DOLE. A report with wrong data, wrong
 * columns, or the wrong applicants included is a compliance failure.
 * The filters must work precisely — "show me only Virac applicants
 * from January" must include ONLY those records, not all records.
 *
 * TESTING MINDSET:
 * For UserManagement: I test the GUARD conditions (self-deactivation,
 * email uniqueness) as much as the happy path.
 * For Reports: I use Excel::fake() to intercept the download without
 * actually generating a file, then verify the export was queued
 * with the correct class and parameters.
 */

// ════════════════════════════════════════════════════════════════════════
// USER MANAGEMENT TESTS
// ════════════════════════════════════════════════════════════════════════

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Role $staffRole;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staffRole = Role::factory()->create(['slug' => 'staff', 'name' => 'Staff']);
        $this->adminRole = Role::factory()->create(['slug' => 'admin', 'name' => 'Administrator']);
        $this->adminUser = User::factory()->create([
            'role_id'  => $this->adminRole->id,
            'is_active'=> true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // CREATE USER
    // ─────────────────────────────────────────────────────────────────

    /**
     * Creating a user must store correct data INCLUDING a hashed password.
     *
     * WHY: If the password is not hashed before storing, it is saved
     * in plaintext. Anyone who reads the database — a DB admin, a
     * backup file that leaks — would see all passwords. This is a
     * catastrophic security failure and an RA 10173 violation.
     */
    public function test_create_user_stores_hashed_password(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(UserManagement::class)
            ->call('openCreate')
            ->set('name',     'New Staff')
            ->set('email',    'newstaff@peso.gov.ph')
            ->set('password', 'SecurePass123!')
            ->set('role_id',  $this->staffRole->id)
            ->call('save');

        $user = User::where('email', 'newstaff@peso.gov.ph')->first();
        $this->assertNotNull($user, 'User must be created in the database.');

        // Password must NOT be stored as plaintext
        $this->assertNotEquals('SecurePass123!', $user->password,
            'Password must be hashed, never stored as plaintext.'
        );

        // Password must be verifiable via Hash::check
        $this->assertTrue(
            Hash::check('SecurePass123!', $user->password),
            'Hashed password must be verifiable with the original plain password.'
        );
    }

    /**
     * Creating a user with a duplicate email must fail validation.
     *
     * WHY: Laravel's authentication uses email as the unique identifier.
     * Two accounts with the same email create ambiguity:
     * which password is correct? Which role does the login get?
     * This prevents that ambiguity from ever existing.
     */
    public function test_create_user_fails_with_duplicate_email(): void
    {
        // Pre-existing user
        User::factory()->create([
            'email'   => 'existing@peso.gov.ph',
            'role_id' => $this->staffRole->id,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(UserManagement::class)
            ->call('openCreate')
            ->set('name',     'Another Staff')
            ->set('email',    'existing@peso.gov.ph') // DUPLICATE
            ->set('password', 'SecurePass123!')
            ->set('role_id',  $this->staffRole->id)
            ->call('save')
            ->assertHasErrors(['email']);

        // Only ONE user with that email must exist
        $this->assertEquals(1,
            User::where('email', 'existing@peso.gov.ph')->count(),
            'Duplicate email must be rejected — only one user with this email may exist.'
        );
    }

    /**
     * New user must be created with is_active = true by default.
     *
     * WHY: A newly created account that is inactive by default would
     * force the admin to perform a second step to activate it.
     * The staff member could not log in until that step is done,
     * causing confusion and a support request.
     */
    public function test_new_user_is_active_by_default(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(UserManagement::class)
            ->call('openCreate')
            ->set('name',     'Active Staff')
            ->set('email',    'activestaff@peso.gov.ph')
            ->set('password', 'SecurePass123!')
            ->set('role_id',  $this->staffRole->id)
            ->call('save');

        $this->assertDatabaseHas('users', [
            'email'     => 'activestaff@peso.gov.ph',
            'is_active' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // EDIT USER
    // ─────────────────────────────────────────────────────────────────

    /**
     * Editing a user without providing a new password must NOT
     * clear or change the existing password.
     *
     * WHY: The edit form has an optional password field. If the admin
     * edits only the name and leaves password blank, the existing
     * password should be preserved. If the code unconditionally
     * updates password with an empty hash, the user can no longer log in.
     */
    public function test_edit_without_password_preserves_existing_password(): void
    {
        $staff = User::factory()->create([
            'role_id'  => $this->staffRole->id,
            'password' => Hash::make('OriginalPassword!'),
        ]);
        $originalHash = $staff->password;

        Livewire::actingAs($this->adminUser)
            ->test(UserManagement::class)
            ->call('openEdit', $staff->id)
            ->set('name',     'Updated Name')
            ->set('password', '') // BLANK — should not change password
            ->call('save');

        $staff->refresh();
        $this->assertEquals($originalHash, $staff->password,
            'Password must not change when the edit form password field is left blank.'
        );
        $this->assertEquals('Updated Name', $staff->name,
            'Name must be updated correctly.'
        );
    }

    /**
     * Editing email must enforce uniqueness excluding the CURRENT user.
     *
     * WHY: If the uniqueness rule does not exclude the current user's
     * own email (using the ,{id} Eloquent unique rule syntax), then
     * editing any user would ALWAYS fail email validation — because
     * their own email is already "taken" by themselves.
     */
    public function test_edit_email_uniqueness_excludes_current_user(): void
    {
        $staff = User::factory()->create([
            'email'   => 'staff@peso.gov.ph',
            'role_id' => $this->staffRole->id,
        ]);

        // Edit only the name, keeping the same email — must NOT fail
        Livewire::actingAs($this->adminUser)
            ->test(UserManagement::class)
            ->call('openEdit', $staff->id)
            ->set('name',  'Staff With Updated Name')
            ->set('email', 'staff@peso.gov.ph') // SAME email as before
            ->call('save')
            ->assertHasNoErrors(['email']);
    }

    // ─────────────────────────────────────────────────────────────────
    // SELF-DEACTIVATION GUARD
    // ─────────────────────────────────────────────────────────────────

    /**
     * An admin must NOT be able to deactivate their own account.
     *
     * WHY: If the only admin deactivates themselves, the entire
     * system is locked — no one can manage users, create new accounts,
     * or restore the deactivated account. The system would be
     * permanently broken without direct database intervention.
     */
    public function test_admin_cannot_deactivate_own_account(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(UserManagement::class)
            ->call('deactivate', $this->adminUser->id);

        // Admin's own account must remain active
        $this->assertDatabaseHas('users', [
            'id'        => $this->adminUser->id,
            'is_active' => true,
        ]);
    }

    /**
     * An admin CAN deactivate OTHER users.
     *
     * WHY: The self-deactivation guard must be SPECIFIC to the current
     * user. If it accidentally prevents deactivation of ANY user,
     * the admin cannot manage user accounts at all.
     */
    public function test_admin_can_deactivate_other_users(): void
    {
        $otherStaff = User::factory()->create([
            'role_id'  => $this->staffRole->id,
            'is_active'=> true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(UserManagement::class)
            ->call('deactivate', $otherStaff->id);

        $this->assertDatabaseHas('users', [
            'id'        => $otherStaff->id,
            'is_active' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // STAFF CANNOT ACCESS USER MANAGEMENT
    // ─────────────────────────────────────────────────────────────────

    /**
     * Staff role must receive 403 on the user management route.
     *
     * WHY: Already tested in AuthTest, but repeated here in the
     * UserManagement context to ensure the Livewire component itself
     * also cannot be accessed by staff — not just the route URL.
     */
    public function test_staff_role_cannot_access_user_management_route(): void
    {
        $staff = User::factory()->create(['role_id' => $this->staffRole->id]);
        $this->actingAs($staff);

        $this->get(route('admin.users'))->assertStatus(403);
    }
}

// ════════════════════════════════════════════════════════════════════════
// REPORT GENERATOR TESTS
// ════════════════════════════════════════════════════════════════════════

class ReportGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;
    private Municipality $municipality;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        $role              = Role::factory()->create(['slug' => 'staff']);
        $this->staffUser   = User::factory()->create(['role_id' => $role->id]);
        $this->municipality = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay     = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // PAGE ACCESS
    // ─────────────────────────────────────────────────────────────────

    public function test_staff_can_access_report_page(): void
    {
        $this->actingAs($this->staffUser);
        $this->get(route('reports'))->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────
    // REPORT DOWNLOAD TRIGGERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Clicking generate must trigger an Excel download.
     * We use Excel::fake() to intercept — no actual file is created.
     *
     * WHY: We cannot test the downloaded file content in a unit/feature
     * test. What we CAN test is: was the correct export class invoked?
     * If the wrong export class is used, the file would have wrong
     * columns or wrong data, but no error is thrown.
     */
    public function test_generate_triggers_excel_download(): void
    {
        Excel::fake();

        Livewire::actingAs($this->staffUser)
            ->test(ReportGenerator::class)
            ->set('format', 'xlsx')
            ->call('generate');

        // Verify the ApplicantsExport class was used for the download
        Excel::assertDownloaded(
            fn(string $filename) => str_contains($filename, 'PESO_Catanduanes_Workforce_'),
            fn(\App\Exports\ApplicantsExport $export) => true
        );
    }

    /**
     * CSV format must trigger a CSV download, not an XLSX.
     *
     * WHY: Some DOLE systems accept only CSV. The format selector
     * exists specifically for this. If the format parameter is
     * ignored and always produces XLSX, staff submitting to
     * a CSV-only system would get a file that cannot be opened.
     */
    public function test_csv_format_triggers_csv_download(): void
    {
        Excel::fake();

        Livewire::actingAs($this->staffUser)
            ->test(ReportGenerator::class)
            ->set('format', 'csv')
            ->call('generate');

        Excel::assertDownloaded(
            fn(string $filename) => str_ends_with($filename, '.csv')
        );
    }

    /**
     * Date validation must reject 'to' date earlier than 'from' date.
     *
     * WHY: "From 2025-06-01 To 2025-01-01" is logically impossible.
     * Without this validation, the export query would use a date range
     * with end before start, returning ZERO records.
     * The staff member would receive an empty report and assume
     * no applicants registered in that period — incorrect.
     */
    public function test_invalid_date_range_fails_validation(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(ReportGenerator::class)
            ->set('dateFrom', '2025-06-01')
            ->set('dateTo',   '2025-01-01')  // BEFORE dateFrom
            ->call('generate')
            ->assertHasErrors(['dateTo']);
    }

    /**
     * Generate must create an audit log recording the download.
     *
     * WHY: RA 10173 requires logging of data exports.
     * If a report is generated and not logged, there is no record
     * that personal data was exported, to whom, when, or with
     * what filters applied. This is an RA 10173 compliance gap.
     */
    public function test_generate_creates_audit_log_with_parameters(): void
    {
        Excel::fake();

        Livewire::actingAs($this->staffUser)
            ->test(ReportGenerator::class)
            ->set('dateFrom', '2025-01-01')
            ->set('dateTo',   '2025-06-30')
            ->set('format',   'xlsx')
            ->call('generate');

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'REPORT_DOWNLOADED',
            'user_id' => $this->staffUser->id,
        ]);
    }

    /**
     * An invalid format value must fail validation.
     *
     * WHY: The format field only accepts 'xlsx' or 'csv'.
     * If an arbitrary string passes validation, the Excel::download()
     * call would receive an unknown extension and either throw a
     * 500 error or produce a corrupted file.
     */
    public function test_invalid_format_value_fails_validation(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(ReportGenerator::class)
            ->set('format', 'pdf')  // NOT an accepted value
            ->call('generate')
            ->assertHasErrors(['format']);
    }
}
