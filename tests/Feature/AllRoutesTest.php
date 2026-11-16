<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * Every page in the system is a route. A page that returns 404 or 500
 * is broken for real users, and nobody notices until someone clicks it.
 * This test visits EVERY page route automatically, so a new or changed
 * route that crashes is caught on the next test run, with no new test
 * needed per page.
 *
 * TESTING MINDSET:
 * I do not check page content here. I only check that nothing is broken
 * (no 404, no 500) for each kind of visitor: guest, staff and admin.
 *
 * LIMITS:
 * - Only GET routes are visited (POST/PUT/DELETE would change data).
 * - Routes with a parameter, like /applicants/{id}, are skipped because
 *   there is no real ID to use. The skipped list is printed when the
 *   test runs so you can see exactly what was not covered.
 *
 * Run: php artisan test --filter=AllRoutesTest
 */
class AllRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;
    private User $adminUser;

    /** URL starts that are not real pages. */
    private array $ignoredPrefixes = [
        '_', 'up', 'livewire', 'sanctum', 'storage', 'logout', 'broadcasting',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // RoleSeeder creates the Spatie roles (admin, staff) and one user for each.
        $this->seed(RoleSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->adminUser = User::where('email', 'admin@peso-catanduanes.gov.ph')->firstOrFail();
        $this->staffUser = User::where('email', 'staff@peso-catanduanes.gov.ph')->firstOrFail();
    }

    /** @return array{0: string[], 1: string[]} [visitable, skipped] */
    private function collectRoutes(): array
    {
        $visit   = [];
        $skipped = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods())) {
                continue;
            }

            $uri = ltrim($route->uri(), '/');

            foreach ($this->ignoredPrefixes as $prefix) {
                if ($uri === $prefix || str_starts_with($uri, $prefix . '/') || str_starts_with($uri, $prefix)) {
                    continue 2;
                }
            }

            if (str_contains($uri, '{')) {
                $skipped[] = '/' . $uri;
                continue;
            }

            $visit[] = '/' . $uri;
        }

        return [array_values(array_unique($visit)), array_values(array_unique($skipped))];
    }

    /**
     * Visit every route and return the ones that failed.
     * $allowForbidden = true means a 403 counts as OK (user not allowed there on purpose).
     */
    private function visitAll(array $uris, bool $allowForbidden, string $label): array
    {
        $failed = [];
        $lines  = [];

        foreach ($uris as $uri) {
            $status = $this->get($uri)->getStatusCode();

            $ok = $status < 400 || ($allowForbidden && $status === 403);

            $lines[] = sprintf('  %s %d  %s', $ok ? 'OK  ' : 'FAIL', $status, $uri);

            if (! $ok) {
                $failed[] = "{$uri} => {$status}";
            }
        }

        // Show every route that was tested for this visitor.
        fwrite(STDERR, "\nRoutes tested as {$label} (" . count($uris) . "):\n" . implode("\n", $lines) . "\n");

        return $failed;
    }

    // ─────────────────────────────────────────────────────────────────
    // GUEST
    // ─────────────────────────────────────────────────────────────────

    /**
     * A guest must never hit a 404 or 500 on any route.
     *
     * WHY: Public pages (login, register) must load, and protected pages
     * must redirect to login. A crash or missing page here is the first
     * thing a new visitor sees.
     */
    public function test_guest_gets_no_broken_pages(): void
    {
        [$uris] = $this->collectRoutes();

        $failed = $this->visitAll($uris, allowForbidden: true, label: 'guest');

        $this->assertEmpty($failed, "Broken routes for guest:\n" . implode("\n", $failed));
    }

    // ─────────────────────────────────────────────────────────────────
    // STAFF
    // ─────────────────────────────────────────────────────────────────

    /**
     * Staff must not hit a 404 or 500 anywhere.
     * A 403 is allowed, because some pages are admin only on purpose.
     */
    public function test_staff_gets_no_broken_pages(): void
    {
        [$uris] = $this->collectRoutes();

        $this->actingAs($this->staffUser);

        $failed = $this->visitAll($uris, allowForbidden: true, label: 'staff');

        $this->assertEmpty($failed, "Broken routes for staff:\n" . implode("\n", $failed));
    }

    // ─────────────────────────────────────────────────────────────────
    // ADMIN
    // ─────────────────────────────────────────────────────────────────

    /**
     * Admin should reach every page, so even a 403 counts as a failure.
     *
     * Also prints the routes that were skipped (they have a parameter),
     * so you know what still needs a manual or dedicated test.
     */
    public function test_admin_gets_no_broken_pages(): void
    {
        [$uris, $skipped] = $this->collectRoutes();

        $this->actingAs($this->adminUser);

        $failed = $this->visitAll($uris, allowForbidden: false, label: 'admin');

        if (! empty($skipped)) {
            fwrite(STDERR, "\nSkipped (have a parameter, not tested):\n" . implode("\n", $skipped) . "\n");
        }

        $this->assertEmpty($failed, "Broken routes for admin:\n" . implode("\n", $failed));
    }
}