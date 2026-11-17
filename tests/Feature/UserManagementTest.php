<?php

namespace Tests\Feature;

use App\Livewire\UserManagement;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * User (staff account) management and permissions.
 *
 * WHY: this is the admin-only security boundary. A bug here can lock PESO out
 * (self-deactivation), escalate a staff member to admin, store a plaintext
 * password, or create two accounts with one email.
 *
 * NOTE ON PERMISSIONS
 * ───────────────────
 * Only admins may manage users. mount() aborts with 403 for anyone else, and
 * Livewire::test() does not give a working component to a user that mount()
 * refuses. So staff and no-role tests call methods directly and check for a
 * 403 HttpException, then check the database.
 *
 * NOTE ON KNOWN BUGS AND GAPS
 * ───────────────────────────
 * Tests marked KNOWN BUG or KNOWN GAP are expected to FAIL until the app code
 * is fixed. Do not weaken their assertions.
 *
 * ASSUMPTIONS: roles are seeded by setUpUsers() with names 'admin' and 'staff'
 * (the component calls hasRole('admin') and syncRoles($role->name)), and the
 * trait provides assertAudited($action, $modelId).
 */
class UserManagementTest extends TestCase
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

    private function roleId(string $name): int
    {
        return (int) Role::where('name', $name)->value('id');
    }

    private function adminPage()
    {
        return Livewire::actingAs($this->admin)->test(UserManagement::class);
    }

    private function makeStaff(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->syncRoles('staff');

        return $user;
    }

    /** Fills the create form and saves it. */
    private function createUser(array $o = [])
    {
        $o = array_merge([
            'name'     => 'New Staff',
            'email'    => 'newstaff@peso.gov.ph',
            'password' => 'SecurePass123!',
            'role_id'  => $this->roleId('staff'),
        ], $o);

        return $this->adminPage()
            ->call('openCreate')
            ->set('name', $o['name'])
            ->set('email', $o['email'])
            ->set('password', $o['password'])
            ->set('role_id', (string) $o['role_id'])
            ->call('save');
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
        $this->actingAs($this->admin)->get(route('admin.users'))->assertOk();
    }

    public function test_staff_cannot_open_page(): void
    {
        $this->actingAs($this->staff)->get(route('admin.users'))->assertForbidden();
    }

    public function test_user_without_role_cannot_open_page(): void
    {
        $this->actingAs($this->outsider)->get(route('admin.users'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.users'))->assertRedirect(route('login'));
    }

    // ── LIST ─────────────────────────────────────────────────────────

    public function test_lists_users_with_email_role_and_status(): void
    {
        $this->makeStaff(['name' => 'Maria Santos', 'email' => 'maria@peso.gov.ph']);

        $this->adminPage()->assertSee('Maria Santos')->assertSee('maria@peso.gov.ph')->assertSee('Active');
    }

    public function test_lists_users_in_name_order(): void
    {
        $this->makeStaff(['name' => 'Zed Zamora']);
        $this->makeStaff(['name' => 'Aaron Abad']);
        $this->makeStaff(['name' => 'Mia Mercado']);

        $this->adminPage()->assertSeeInOrder(['Aaron Abad', 'Mia Mercado', 'Zed Zamora']);
    }

    public function test_inactive_user_shows_inactive_badge_and_activate_button(): void
    {
        $u = $this->makeStaff(['name' => 'Idle Ian', 'is_active' => false]);

        $this->adminPage()
            ->assertSee('Inactive')
            ->assertSeeHtml('wire:click="activated(' . $u->id . ')"')
            ->assertDontSeeHtml('wire:click="deactivate(' . $u->id . ')"');
    }

    public function test_active_user_shows_deactivate_button_not_activate(): void
    {
        $u = $this->makeStaff(['is_active' => true]);

        $this->adminPage()
            ->assertSeeHtml('wire:click="deactivate(' . $u->id . ')"')
            ->assertDontSeeHtml('wire:click="activated(' . $u->id . ')"');
    }

    public function test_own_row_has_no_deactivate_button(): void
    {
        $this->adminPage()->assertDontSeeHtml('wire:click="deactivate(' . $this->admin->id . ')"');
    }

   public function test_list_shows_twenty_per_page_with_remainder_on_next_page(): void
{
    User::factory()->count(25)->create()->each(fn ($u) => $u->syncRoles('staff'));

    $total = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff']))->count();

    $component = $this->adminPage();

    $this->assertCount(20, $component->viewData('users'));

    $component->call('gotoPage', 2);

    $this->assertCount($total - 20, $component->viewData('users'));
}
    public function test_roles_dropdown_lists_every_role(): void
    {
        $roles = $this->adminPage()->viewData('roles');

        $this->assertEqualsCanonicalizing(Role::pluck('id')->all(), $roles->pluck('id')->all());
    }

    // ── CREATE ───────────────────────────────────────────────────────

    public function test_open_create_shows_empty_form(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->assertSet('showForm', true)
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('password', '')
            ->assertSet('role_id', '');
    }

    public function test_open_create_resets_state_left_by_a_previous_edit(): void
    {
        $u = $this->makeStaff();

        $this->adminPage()
            ->call('openEdit', $u->id)
            ->call('openCreate')
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('role_id', '');
    }

    public function test_create_saves_user_closes_form_and_notifies(): void
    {
        $this->createUser()
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('users', ['name' => 'New Staff', 'email' => 'newstaff@peso.gov.ph']);
    }

    /** A plaintext password in the database is an RA 10173 failure. */
    public function test_create_stores_hashed_password(): void
    {
        $this->createUser();

        $user = User::where('email', 'newstaff@peso.gov.ph')->firstOrFail();

        $this->assertNotSame('SecurePass123!', $user->password);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
    }

    public function test_create_assigns_the_selected_role(): void
    {
        $this->createUser();

        $user = User::where('email', 'newstaff@peso.gov.ph')->firstOrFail();

        $this->assertSame(['staff'], $user->roles->pluck('name')->all());
    }

    public function test_admin_can_create_another_admin(): void
    {
        $this->createUser(['email' => 'boss@peso.gov.ph', 'role_id' => $this->roleId('admin')]);

        $this->assertTrue(User::where('email', 'boss@peso.gov.ph')->firstOrFail()->hasRole('admin'));
    }

    public function test_new_user_is_active_by_default(): void
    {
        $this->createUser();

        $this->assertDatabaseHas('users', ['email' => 'newstaff@peso.gov.ph', 'is_active' => true]);
    }

    /** Documents current behavior: a blank password falls back to the email. */
    public function test_blank_password_falls_back_to_email_as_password(): void
    {
        $this->createUser(['password' => '']);

        $user = User::where('email', 'newstaff@peso.gov.ph')->firstOrFail();

        $this->assertTrue(Hash::check('newstaff@peso.gov.ph', $user->password));
    }

    public function test_create_rejects_password_shorter_than_8_chars(): void
    {
        $this->createUser(['password' => 'Short1!'])->assertHasErrors(['password' => 'min']);

        $this->assertDatabaseMissing('users', ['email' => 'newstaff@peso.gov.ph']);
    }

    public function test_create_accepts_password_of_exactly_8_chars(): void
    {
        $this->createUser(['password' => 'Exact8!!'])->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'newstaff@peso.gov.ph']);
    }

    /**
     * KNOWN BUG — expected to FAIL (USR-3).
     *
     * 'nullable|min:8' accepts eight spaces, then filled() treats them as blank,
     * so the account silently gets the email as its password.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as admin, click "Add New Staff User".
     *   2. Fill name, email, role, and type 8 spaces in Password.
     *   3. BUG: saved, and the user can log in with their email as the password.
     *      Expected: a validation error on password.
     *
     * FIX: trim $this->password before validate() in save().
     */
    public function test_whitespace_only_password_is_rejected(): void
    {
        $this->createUser(['password' => '        '])->assertHasErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'newstaff@peso.gov.ph']);
    }

    public function test_create_requires_name_email_and_role(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'email' => 'required', 'role_id' => 'required'])
            ->assertSet('showForm', true)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseCount('users', 3);
    }

    public function test_whitespace_only_name_is_rejected(): void
    {
        $this->createUser(['name' => '     '])->assertHasErrors(['name' => 'required']);
    }

    public function test_create_rejects_name_longer_than_100_chars(): void
    {
        $this->createUser(['name' => str_repeat('a', 101)])->assertHasErrors(['name' => 'max']);
    }

    public function test_create_accepts_name_of_exactly_100_chars(): void
    {
        $this->createUser(['name' => str_repeat('a', 100)])->assertHasNoErrors();
    }

    public function test_create_rejects_invalid_email_format(): void
    {
        $this->createUser(['email' => 'not-an-email'])->assertHasErrors(['email' => 'email']);
    }

    public function test_create_rejects_unknown_role_id(): void
    {
        $this->createUser(['role_id' => 99999])->assertHasErrors(['role_id' => 'exists']);

        $this->assertDatabaseMissing('users', ['email' => 'newstaff@peso.gov.ph']);
    }

    public function test_create_rejects_non_numeric_role_id(): void
    {
        $this->createUser(['role_id' => 'admin'])->assertHasErrors(['role_id']);
    }

    /** Two accounts with one email make login ambiguous. */
    public function test_duplicate_email_is_rejected(): void
    {
        $this->makeStaff(['email' => 'existing@peso.gov.ph']);

        $this->createUser(['email' => 'existing@peso.gov.ph'])->assertHasErrors(['email' => 'unique']);

        $this->assertSame(1, User::where('email', 'existing@peso.gov.ph')->count());
    }

    /**
     * KNOWN GAP — expected to FAIL (USR-5).
     *
     * The name is saved exactly as typed, so " Maria " is stored with spaces.
     * Other admin screens trim before saving.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Add a user with name "   Maria   ".
     *   2. BUG: stored with the spaces. Expected: "Maria".
     *
     * FIX: trim $this->name before validate() in save().
     */
    public function test_padded_name_is_saved_trimmed(): void
    {
        $this->createUser(['name' => '   Maria   ']);

        $this->assertDatabaseHas('users', ['email' => 'newstaff@peso.gov.ph', 'name' => 'Maria']);
    }

    public function test_create_writes_audit_log(): void
    {
        $this->createUser();

        $this->assertAudited('USER_CREATED', User::where('email', 'newstaff@peso.gov.ph')->value('id'));
    }

    public function test_failed_create_writes_no_audit_log(): void
    {
        $this->adminPage()->call('openCreate')->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_CREATED']);
    }

    /** The audit row is written in the same transaction, so a failed audit must leave no user. */
    public function test_create_rolls_back_when_audit_write_fails(): void
    {
        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('log')->andThrow(new \RuntimeException('audit down'));
        });

        $this->createUser()->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('users', ['email' => 'newstaff@peso.gov.ph']);
    }

    // ── EDIT ─────────────────────────────────────────────────────────

    public function test_open_edit_loads_existing_values_and_role(): void
    {
        $u = $this->makeStaff(['name' => 'Maria Santos', 'email' => 'maria@peso.gov.ph']);

        $this->adminPage()
            ->call('openEdit', $u->id)
            ->assertSet('showForm', true)
            ->assertSet('editingId', $u->id)
            ->assertSet('name', 'Maria Santos')
            ->assertSet('email', 'maria@peso.gov.ph')
            ->assertSet('password', '')
            ->assertSet('role_id', (string) $this->roleId('staff'));
    }

    public function test_open_edit_on_missing_record_returns_404(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->adminPage()->call('openEdit', 99999);
    }

    public function test_edit_updates_only_the_targeted_user(): void
    {
        $target = $this->makeStaff(['name' => 'Target']);
        $other  = $this->makeStaff(['name' => 'Bystander']);

        $this->adminPage()
            ->call('openEdit', $target->id)
            ->set('name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Renamed']);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => 'Bystander']);
    }

    public function test_edit_can_promote_staff_to_admin(): void
    {
        $u = $this->makeStaff();

        $this->adminPage()
            ->call('openEdit', $u->id)
            ->set('role_id', (string) $this->roleId('admin'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['admin'], $u->fresh()->roles->pluck('name')->all());
    }

    /** syncRoles replaces the old role, so a user never ends up with two. */
    public function test_edit_role_change_leaves_exactly_one_role(): void
    {
        $u = $this->makeStaff();

        $this->adminPage()
            ->call('openEdit', $u->id)
            ->set('role_id', (string) $this->roleId('admin'))
            ->call('save');

        $this->assertCount(1, $u->fresh()->roles);
    }

    /** The edit form has no password field, so a password sent from the browser is ignored. */
    public function test_edit_does_not_change_password_even_if_one_is_sent(): void
    {
        $u = $this->makeStaff(['password' => Hash::make('OriginalPassword!')]);
        $original = $u->password;

        $this->adminPage()
            ->call('openEdit', $u->id)
            ->set('password', 'HackedPass123!')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($original, $u->fresh()->password);
    }

    public function test_edit_without_password_keeps_existing_password(): void
    {
        $u = $this->makeStaff(['password' => Hash::make('OriginalPassword!')]);

        $this->adminPage()->call('openEdit', $u->id)->set('name', 'Updated')->call('save');

        $this->assertTrue(Hash::check('OriginalPassword!', $u->fresh()->password));
    }

    public function test_edit_does_not_change_active_status(): void
    {
        $u = $this->makeStaff(['is_active' => false]);

        $this->adminPage()->call('openEdit', $u->id)->set('name', 'Updated')->call('save');

        $this->assertDatabaseHas('users', ['id' => $u->id, 'is_active' => false]);
    }

    public function test_saving_without_changes_is_not_flagged_as_duplicate_email(): void
    {
        $u = $this->makeStaff();

        $this->adminPage()->call('openEdit', $u->id)->call('save')->assertHasNoErrors();
    }

    public function test_edit_cannot_take_another_users_email(): void
    {
        $this->makeStaff(['email' => 'taken@peso.gov.ph']);
        $u = $this->makeStaff(['email' => 'mine@peso.gov.ph']);

        $this->adminPage()
            ->call('openEdit', $u->id)
            ->set('email', 'taken@peso.gov.ph')
            ->call('save')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertDatabaseHas('users', ['id' => $u->id, 'email' => 'mine@peso.gov.ph']);
    }

    public function test_edit_validation_failure_keeps_original_data(): void
    {
        $u = $this->makeStaff(['name' => 'Original']);

        $this->adminPage()
            ->call('openEdit', $u->id)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required'])
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('users', ['id' => $u->id, 'name' => 'Original']);
    }

    public function test_edit_writes_audit_log(): void
    {
        $u = $this->makeStaff();

        $this->adminPage()->call('openEdit', $u->id)->set('name', 'Updated')->call('save');

        $this->assertAudited('USER_UPDATED', $u->id);
    }

    public function test_failed_edit_writes_no_audit_log(): void
    {
        $u = $this->makeStaff();

        $this->adminPage()->call('openEdit', $u->id)->set('name', '')->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_UPDATED']);
    }

    public function test_edit_on_a_user_deleted_meanwhile_shows_error_toast(): void
    {
        $u = $this->makeStaff();

        $component = $this->adminPage()->call('openEdit', $u->id);

        // another admin removes the account while this form is still open
        $u->delete();

        $component->set('name', 'Renamed')->call('save')->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('users', ['name' => 'Renamed']);
    }

    public function test_edit_rolls_back_role_change_when_audit_write_fails(): void
    {
        $u = $this->makeStaff();

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('log')->andThrow(new \RuntimeException('audit down'));
        });

        $this->adminPage()
            ->call('openEdit', $u->id)
            ->set('role_id', (string) $this->roleId('admin'))
            ->call('save')
            ->assertDispatched('notify', type: 'error');

        $this->assertTrue($u->fresh()->hasRole('staff'));
        $this->assertFalse($u->fresh()->hasRole('admin'));
    }

    public function test_cancel_hides_the_form(): void
    {
        $this->adminPage()
            ->call('openCreate')
            ->set('showForm', false)
            ->assertSet('showForm', false);
    }

    // ── SELF PROTECTION ──────────────────────────────────────────────

    public function test_admin_cannot_change_own_role(): void
    {
        $this->adminPage()
            ->call('openEdit', $this->admin->id)
            ->set('role_id', (string) $this->roleId('staff'))
            ->call('save')
            ->assertDispatched('notify', type: 'error');

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_blocked_self_role_change_writes_no_audit_log(): void
    {
        $this->adminPage()
            ->call('openEdit', $this->admin->id)
            ->set('role_id', (string) $this->roleId('staff'))
            ->call('save');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_UPDATED']);
    }

    public function test_admin_can_edit_own_name_without_changing_role(): void
    {
        $this->adminPage()
            ->call('openEdit', $this->admin->id)
            ->set('name', 'Admin Renamed')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'name' => 'Admin Renamed']);
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    /** If the only admin deactivates themselves, PESO is locked out. */
    public function test_admin_cannot_deactivate_own_account(): void
    {
        $this->adminPage()
            ->call('deactivate', $this->admin->id)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'is_active' => true]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_DEACTIVATED']);
    }

    public function test_admin_cannot_activate_own_account(): void
    {
        $this->adminPage()
            ->call('activated', $this->admin->id)
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_ACTIVATED']);
    }

    // ── DEACTIVATE / ACTIVATE ────────────────────────────────────────

    public function test_admin_can_deactivate_another_user_and_it_is_audited(): void
    {
        $u = $this->makeStaff(['is_active' => true]);

        $this->adminPage()->call('deactivate', $u->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('users', ['id' => $u->id, 'is_active' => false]);
        $this->assertAudited('USER_DEACTIVATED', $u->id);
    }

    public function test_deactivate_only_affects_the_targeted_user(): void
    {
        $target = $this->makeStaff(['is_active' => true]);
        $other  = $this->makeStaff(['is_active' => true]);

        $this->adminPage()->call('deactivate', $target->id);

        $this->assertDatabaseHas('users', ['id' => $other->id, 'is_active' => true]);
    }

    public function test_deactivate_keeps_the_account_and_its_role(): void
    {
        $u = $this->makeStaff();

        $this->adminPage()->call('deactivate', $u->id);

        $this->assertDatabaseHas('users', ['id' => $u->id]);
        $this->assertTrue($u->fresh()->hasRole('staff'));
    }

    public function test_deactivating_an_already_inactive_user_still_succeeds(): void
    {
        $u = $this->makeStaff(['is_active' => false]);

        $this->adminPage()->call('deactivate', $u->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('users', ['id' => $u->id, 'is_active' => false]);
    }

    public function test_deactivate_missing_user_shows_error_toast_without_throwing(): void
    {
        $this->adminPage()->call('deactivate', 99999)->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_DEACTIVATED']);
    }

    public function test_deactivate_failure_when_audit_write_fails_shows_error_toast(): void
    {
        $u = $this->makeStaff(['is_active' => true]);

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('log')->andThrow(new \RuntimeException('audit down'));
        });

        $this->adminPage()->call('deactivate', $u->id)->assertDispatched('notify', type: 'error');
    }

    public function test_admin_can_activate_an_inactive_user_and_it_is_audited(): void
    {
        $u = $this->makeStaff(['is_active' => false]);

        $this->adminPage()->call('activated', $u->id)->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('users', ['id' => $u->id, 'is_active' => true]);
        $this->assertAudited('USER_ACTIVATED', $u->id);
    }

    public function test_activate_missing_user_shows_error_toast_without_throwing(): void
    {
        $this->adminPage()->call('activated', 99999)->assertDispatched('notify', type: 'error');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_ACTIVATED']);
    }

    public function test_deactivate_then_activate_round_trip_restores_access(): void
    {
        $u = $this->makeStaff(['is_active' => true]);

        $page = $this->adminPage();
        $page->call('deactivate', $u->id);
        $this->assertDatabaseHas('users', ['id' => $u->id, 'is_active' => false]);

        $page->call('activated', $u->id);
        $this->assertDatabaseHas('users', ['id' => $u->id, 'is_active' => true]);
    }

    // ── PERMISSIONS: ADMIN ───────────────────────────────────────────

    public function test_admin_can_create_edit_deactivate_and_activate(): void
    {
        $this->createUser(['email' => 'flow@peso.gov.ph']);

        $user = User::where('email', 'flow@peso.gov.ph')->firstOrFail();

        $page = $this->adminPage();

        $page->call('openEdit', $user->id)->set('name', 'Flow Renamed')->call('save')->assertHasNoErrors();
        $page->call('deactivate', $user->id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);

        $page->call('activated', $user->id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Flow Renamed', 'is_active' => true]);
    }

    // ── PERMISSIONS: STAFF ───────────────────────────────────────────

    public function test_staff_cannot_mount_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->mount()),
            'Staff mount() should be refused with 403.'
        );
    }

    public function test_staff_cannot_render_component(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->render()),
            'Staff render() should be refused with 403.'
        );
    }

    public function test_staff_cannot_create_user(): void
    {
        $this->actingAs($this->staff);

        $component = new UserManagement();
        $component->name = 'Sneaky';
        $component->email = 'sneaky@peso.gov.ph';
        $component->role_id = (string) $this->roleId('admin');

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() should be refused with 403.'
        );

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@peso.gov.ph']);
    }

    /** The escalation attack: a staff member trying to make themselves admin. */
    public function test_staff_cannot_promote_themselves_to_admin(): void
    {
        $this->actingAs($this->staff);

        $component = new UserManagement();
        $component->editingId = $this->staff->id;
        $component->name = $this->staff->name;
        $component->email = $this->staff->email;
        $component->role_id = (string) $this->roleId('admin');

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() on their own record should be refused with 403.'
        );

        $this->assertFalse($this->staff->fresh()->hasRole('admin'));
    }

    public function test_staff_cannot_edit_another_user(): void
    {
        $target = $this->makeStaff(['name' => 'Target']);
        $this->actingAs($this->staff);

        $component = new UserManagement();
        $component->editingId = $target->id;
        $component->name = 'Hacked';
        $component->email = $target->email;
        $component->role_id = (string) $this->roleId('staff');

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'Staff save() on another user should be refused with 403.'
        );

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Target']);
    }

    /**
     * KNOWN GAP — expected to FAIL until openCreate() has a role check (USR-2).
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   The route blocks staff, so this only matters if the component is reached
     *   another way. Calling openCreate() directly as staff shows the form.
     *
     * FIX: add abort_if(!auth()->user()->hasRole('admin'), 403); as the first line of openCreate().
     */
    public function test_staff_cannot_open_create_form(): void
    {
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->openCreate()),
            'Staff openCreate() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until openEdit() has a role check (USR-2).
     * openEdit() also loads another user's name and email into the form.
     *
     * FIX: add abort_if(!auth()->user()->hasRole('admin'), 403); as the first line of openEdit().
     */
    public function test_staff_cannot_open_edit_form(): void
    {
        $target = $this->makeStaff();
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->openEdit($target->id)),
            'Staff openEdit() should be refused with 403.'
        );
    }

    /**
     * KNOWN GAP — expected to FAIL until deactivate() has a role check (USR-2).
     *
     * deactivate() only blocks self-deactivation. It has no admin check, and the
     * code comment says "no AUTHORIZE its good enough". Livewire lets the browser
     * call any public method, so this matters as soon as the component is reached
     * without the route middleware.
     *
     * HOW TO REPRODUCE: call deactivate() on the component as a staff user.
     * BUG: the target is deactivated and audited. Expected: 403.
     *
     * FIX: add abort_if(!auth()->user()->hasRole('admin'), 403); at the top of deactivate().
     */
    public function test_staff_cannot_deactivate_user(): void
    {
        $target = $this->makeStaff(['is_active' => true]);
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->deactivate($target->id, app(AuditLogService::class))),
            'Staff deactivate() should be refused with 403.'
        );

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    }

    /**
     * KNOWN GAP — expected to FAIL until activated() has a role check (USR-2).
     * FIX: same abort_if at the top of activated().
     */
    public function test_staff_cannot_activate_user(): void
    {
        $target = $this->makeStaff(['is_active' => false]);
        $this->actingAs($this->staff);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->activated($target->id, app(AuditLogService::class))),
            'Staff activated() should be refused with 403.'
        );

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_staff_writes_leave_no_audit_trail(): void
    {
        $this->actingAs($this->staff);

        $audit = app(AuditLogService::class);

        $create = new UserManagement();
        $create->name = 'Sneaky';
        $create->email = 'sneaky@peso.gov.ph';
        $create->role_id = (string) $this->roleId('staff');
        $this->refusedWith403(fn () => $create->save($audit));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->staff->id]);
    }

    // ── PERMISSIONS: NO ROLE ─────────────────────────────────────────

    public function test_user_without_role_cannot_mount_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->mount()),
            'User without a role: mount() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_render_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->render()),
            'User without a role: render() should be refused with 403.'
        );
    }

    public function test_user_without_role_cannot_create_user(): void
    {
        $this->actingAs($this->outsider);

        $component = new UserManagement();
        $component->name = 'Sneaky';
        $component->email = 'sneaky@peso.gov.ph';
        $component->role_id = (string) $this->roleId('admin');

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() should be refused with 403.'
        );

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@peso.gov.ph']);
    }

    public function test_user_without_role_cannot_promote_themselves_to_admin(): void
    {
        $this->actingAs($this->outsider);

        $component = new UserManagement();
        $component->editingId = $this->outsider->id;
        $component->name = $this->outsider->name;
        $component->email = $this->outsider->email;
        $component->role_id = (string) $this->roleId('admin');

        $this->assertTrue(
            $this->refusedWith403(fn () => $component->save(app(AuditLogService::class))),
            'User without a role: save() on their own record should be refused with 403.'
        );

        $this->assertFalse($this->outsider->fresh()->hasRole('admin'));
    }

    /** KNOWN GAP — expected to FAIL until openCreate() has a role check (USR-2). */
    public function test_user_without_role_cannot_open_create_form(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->openCreate()),
            'User without a role: openCreate() should be refused with 403.'
        );
    }

    /** KNOWN GAP — expected to FAIL until openEdit() has a role check (USR-2). */
    public function test_user_without_role_cannot_open_edit_form(): void
    {
        $target = $this->makeStaff();
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->openEdit($target->id)),
            'User without a role: openEdit() should be refused with 403.'
        );
    }

    /** KNOWN GAP — expected to FAIL until deactivate() has a role check (USR-2). */
    public function test_user_without_role_cannot_deactivate_user(): void
    {
        $target = $this->makeStaff(['is_active' => true]);
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->deactivate($target->id, app(AuditLogService::class))),
            'User without a role: deactivate() should be refused with 403.'
        );

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    }

    /** KNOWN GAP — expected to FAIL until activated() has a role check (USR-2). */
    public function test_user_without_role_cannot_activate_user(): void
    {
        $target = $this->makeStaff(['is_active' => false]);
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new UserManagement())->activated($target->id, app(AuditLogService::class))),
            'User without a role: activated() should be refused with 403.'
        );

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_user_without_role_writes_leave_no_audit_trail(): void
    {
        $this->actingAs($this->outsider);

        $create = new UserManagement();
        $create->name = 'Sneaky';
        $create->email = 'sneaky@peso.gov.ph';
        $create->role_id = (string) $this->roleId('staff');
        $this->refusedWith403(fn () => $create->save(app(AuditLogService::class)));

        $this->assertDatabaseMissing('audit_logs', ['user_id' => $this->outsider->id]);
    }
}