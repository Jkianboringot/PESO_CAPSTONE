<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * Authentication and role-based access control are the security
 * foundation of the entire system. If they fail, a resident could
 * access PESO staff dashboards, or a staff member could access
 * admin-only user management.
 *
 * TESTING MINDSET:
 * I am testing PERMISSIONS, not just "does login work."
 * The key question for every test is: "Does the system correctly
 * ALLOW what should be allowed and BLOCK what should be blocked?"
 *
 * The most dangerous failure mode is a false ALLOW:
 * - Guest accesses staff page → data breach
 * - Staff accesses admin page → unauthorized user creation
 * These failures could go unnoticed for months.
 *
 * I also test the DEACTIVATED account scenario specifically because
 * this is a real operational scenario: a staff member leaves PESO,
 * their account is deactivated. They must not be able to log in
 * with old credentials and access sensitive workforce data.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    // Helper: create a user with a specific role slug
    private function makeUser(string $roleSlug, bool $isActive = true): User
    {
        $role = Role::factory()->create(['slug' => $roleSlug]);
        return User::factory()->create([
            'role_id'   => $role->id,
            'is_active' => $isActive,
            'password'  => Hash::make('correctpassword'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // LOGIN FLOW
    // ─────────────────────────────────────────────────────────────────

    /**
     * A valid staff user with correct credentials must be redirected
     * to the dashboard after login.
     */
    public function test_valid_staff_user_can_login_and_reaches_dashboard(): void
    {
        $user = $this->makeUser('staff');

        $response = $this->post(route('login.post'), [
            'email'    => $user->email,
            'password' => 'correctpassword',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Wrong password must NOT authenticate.
     * The user must remain a guest and see an error.
     *
     * WHY: This tests that the authentication guard is actually
     * checking the password, not just the email existence.
     */
    public function test_wrong_password_fails_login(): void
    {
        $user = $this->makeUser('staff');

        $response = $this->post(route('login.post'), [
            'email'    => $user->email,
            'password' => 'wrongpassword',
        ]);

        // Must NOT be authenticated
        $this->assertGuest();

        // Must redirect back with error
        $response->assertSessionHasErrors('email');
    }

    /**
     * A deactivated account must NOT be able to login even with
     * correct credentials.
     *
     * WHY: The staff member left PESO. Their account was deactivated.
     * If Laravel's Auth::attempt() alone is the only check and it
     * does not check is_active, the deactivated user can still log in.
     * The AuthController has an explicit is_active check AFTER attempt().
     * This test verifies that second check exists and works.
     */
    public function test_deactivated_account_cannot_login(): void
    {
        $user = $this->makeUser('staff', isActive: false);

        $response = $this->post(route('login.post'), [
            'email'    => $user->email,
            'password' => 'correctpassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * Non-existent email must fail gracefully.
     * Should return an error, not a 500 or DB exception.
     */
    public function test_nonexistent_email_fails_login(): void
    {
        $response = $this->post(route('login.post'), [
            'email'    => 'nobody@peso.gov.ph',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * Authenticated users visiting /login must be redirected away.
     * They should not see the login form while already logged in.
     */
    public function test_authenticated_user_redirected_from_login_page(): void
    {
        $user = $this->makeUser('staff');
        $this->actingAs($user);

        $response = $this->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Logout must clear the session and redirect to login.
     */
    public function test_logout_clears_session_and_redirects(): void
    {
        $user = $this->makeUser('staff');
        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    // ─────────────────────────────────────────────────────────────────
    // GUEST ACCESS CONTROL
    // Unauthenticated users must be redirected to login
    // for ALL protected routes.
    // ─────────────────────────────────────────────────────────────────

    /**
     * A guest (not logged in) cannot access the dashboard.
     * They must be redirected to login.
     *
     * WHY: This is the most basic auth protection test. If this fails,
     * the auth middleware is not applied and ALL protected routes are open.
     */
    public function test_guest_redirected_to_login_from_dashboard(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Guests cannot access any protected staff route.
     * Testing multiple routes to ensure middleware is applied to all groups.
     */
    public function test_guest_cannot_access_any_staff_route(): void
    {
        $protectedRoutes = [
            route('dashboard'),
            route('applicants'),
            route('duplicates'),
            route('analytics'),
            route('reports'),
            route('skills-gap'),
        ];

        foreach ($protectedRoutes as $routeUrl) {
            $response = $this->get($routeUrl);
            $response->assertRedirect(route('login'),
                "Guest should be redirected to login from: {$routeUrl}"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // ROLE-BASED ACCESS CONTROL
    // Staff cannot access admin-only routes.
    // ─────────────────────────────────────────────────────────────────

    /**
     * A staff user cannot access the admin-only user management page.
     * Must receive HTTP 403 Forbidden.
     *
     * WHY: If staff can access /admin/users, they could create new
     * admin accounts, effectively escalating their own privileges.
     * This is a privilege escalation vulnerability.
     */
    public function test_staff_cannot_access_admin_only_user_management(): void
    {
        $user = $this->makeUser('staff');
        $this->actingAs($user);

        $response = $this->get(route('admin.users'));

        $response->assertStatus(403,
            'Staff role must receive 403 on admin-only routes.'
        );
    }

    /**
     * An admin CAN access the user management page.
     *
     * WHY: We need to verify the positive case too.
     * If the middleware is too restrictive, even admins get 403 —
     * which would lock PESO out of user management entirely.
     */
    public function test_admin_can_access_user_management(): void
    {
        $user = $this->makeUser('admin');
        $this->actingAs($user);

        $response = $this->get(route('admin.users'));

        $response->assertStatus(200,
            'Admin role must be allowed access to /admin/users.'
        );
    }

    /**
     * Admin can also access all staff routes (inheritance check).
     *
     * WHY: The route group uses middleware('role:staff,admin').
     * Admin must match that OR condition. If admin is excluded from
     * staff routes, they cannot do basic work like viewing applicants.
     */
    public function test_admin_can_access_staff_routes(): void
    {
        $user = $this->makeUser('admin');
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        $response = $this->get(route('applicants'));
        $response->assertStatus(200);
    }

    /**
     * A deactivated user who is somehow still in a session (e.g.,
     * session not invalidated) must be blocked on the next request.
     *
     * WHY: In the real world, when an admin deactivates a staff account,
     * the staff member might already be logged in. The CheckRole middleware
     * checks is_active on EVERY request. This ensures they are kicked out
     * on their next page load, not just at login.
     */
    public function test_deactivated_user_blocked_on_next_request_even_if_session_exists(): void
    {
        $user = $this->makeUser('staff');
        $this->actingAs($user);

        // Confirm they can access the dashboard right now
        $this->get(route('dashboard'))->assertStatus(200);

        // Admin deactivates the account
        $user->update(['is_active' => false]);

        // On the NEXT request, they must be kicked out
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));

        // Must also be logged out
        $this->assertGuest();
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC ROUTES — Must be accessible without authentication
    // ─────────────────────────────────────────────────────────────────

    /**
     * The registration form at /register must be accessible to guests.
     *
     * WHY: This is the public-facing module for residents.
     * If auth middleware accidentally covers this route, NO resident
     * can register. Every visitor gets a login page instead.
     */
    public function test_registration_form_accessible_to_guests(): void
    {
        $response = $this->get(route('register'));
        $response->assertStatus(200,
            '/register must be publicly accessible without authentication.'
        );
    }

    /**
     * The landing page must be publicly accessible.
     */
    public function test_welcome_page_accessible_to_guests(): void
    {
        $response = $this->get(route('welcome'));
        $response->assertStatus(200);
    }
}
