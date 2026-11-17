<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\{Applicant, Barangay, Municipality, User};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\SetsUpAdminAndStaff;
use Tests\TestCase;

/**
 * Dashboard overview: the four stat cards and role access.
 *
 * WHY: these numbers are the first thing staff see, and the flagged count
 * tells them what needs attention. A wrong count sends staff to the wrong
 * work or hides a backlog.
 *
 * ASSUMPTIONS
 * - The dashboard route is named 'dashboard'. Adjust if yours differs.
 * - DuplicateFlag has a factory and a pending() scope.
 *
 * KNOWN BUG / KNOWN GAP tests are expected to FAIL until the code is fixed.
 * Do not weaken their assertions.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase, SetsUpAdminAndStaff;

    private User $outsider;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUsers();

        $this->outsider = User::factory()->create();

        $municipality   = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay = Barangay::factory()->create(['municipality_id' => $municipality->id]);

        Carbon::setTestNow('2026-11-17 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeApplicant(array $attrs = []): Applicant
    {
        return Applicant::factory()->create(array_merge([
            'barangay_id' => $this->barangay->id,
            'is_active'   => true,
            'status'      => 'Pending',
        ], $attrs));
    }

    private function stats(): array
    {
        return Livewire::actingAs($this->staff)
            ->test(Dashboard::class)
            ->viewData('stats');
    }

    /** True only when the callable throws an HttpException with status 403. */
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

    public function test_admin_can_open_dashboard(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
    }

    public function test_staff_can_open_dashboard(): void
    {
        $this->actingAs($this->staff)->get(route('dashboard'))->assertOk();
    }

    public function test_user_without_role_cannot_open_dashboard(): void
    {
        $this->actingAs($this->outsider)->get(route('dashboard'))->assertForbidden();
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /**
     * KNOWN GAP (DSH-2): Dashboard::render() has no role check. The route is the
     * only protection.
     *
     * HOW TO REPRODUCE: call render() as a user with no role.
     * BUG: the view is returned. Expected: 403.
     *
     * FIX: add abort_if(!auth()->user()?->hasRole(['admin', 'staff']), 403); as the first line of render().
     */
    public function test_user_without_role_cannot_render_dashboard_component(): void
    {
        $this->actingAs($this->outsider);

        $this->assertTrue(
            $this->refusedWith403(fn () => (new Dashboard())->render()),
            'User without a role: dashboard render() should be refused with 403.'
        );
    }

    // ── EDGE CASES ───────────────────────────────────────────────────

    public function test_empty_database_shows_zero_on_every_card(): void
    {
        $stats = $this->stats();

        $this->assertSame(0, $stats['total_applicants']);
        $this->assertSame(0, $stats['this_month']);
        $this->assertSame(0, $stats['pending_duplicates']);
        $this->assertSame(0, $stats['flagged']);
    }

    public function test_total_counts_only_active_applicants(): void
    {
        $this->makeApplicant(['is_active' => true]);
        $this->makeApplicant(['is_active' => true]);
        $this->makeApplicant(['is_active' => false]);

        $this->assertSame(2, $this->stats()['total_applicants']);
    }

    public function test_this_month_counts_registrations_from_the_current_month(): void
    {
        $this->makeApplicant(['created_at' => '2026-11-02 09:00:00']);
        $this->makeApplicant(['created_at' => '2026-11-15 09:00:00']);

        $this->assertSame(2, $this->stats()['this_month']);
    }

    public function test_this_month_excludes_previous_month(): void
    {
        $this->makeApplicant(['created_at' => '2026-10-31 23:59:59']);

        $this->assertSame(0, $this->stats()['this_month']);
    }

    public function test_this_month_excludes_inactive_applicants(): void
    {
        $this->makeApplicant(['created_at' => '2026-11-05 09:00:00', 'is_active' => false]);

        $this->assertSame(0, $this->stats()['this_month']);
    }

    /**
     * KNOWN BUG (DSH-1): this_month uses whereMonth() without a year, so a
     * registration from November 2025 is counted as this month.
     *
     * HOW TO REPRODUCE: the clock is 2026-11-17. Register an applicant on
     * 2025-11-10. BUG: the card shows 1. Expected: 0.
     *
     * FIX: use whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]).
     */
    public function test_this_month_ignores_same_month_from_previous_year(): void
    {
        $this->makeApplicant(['created_at' => '2025-11-10 10:00:00']);

        $this->assertSame(0, $this->stats()['this_month']);
    }

    public function test_flagged_counts_only_flagged_applicants(): void
    {
        $this->makeApplicant(['status' => 'Flagged']);
        $this->makeApplicant(['status' => 'Flagged']);
        $this->makeApplicant(['status' => 'Verified']);
        $this->makeApplicant(['status' => 'Pending']);

        $this->assertSame(2, $this->stats()['flagged']);
    }

    /**
     * KNOWN BUG (DSH-3): flagged counts deactivated applicants, while the other
     * cards count active applicants only. A deactivated flagged record still
     * shows as "Requires attention".
     *
     * HOW TO REPRODUCE: create one active and one inactive applicant, both
     * Flagged. BUG: the card shows 2. Expected: 1.
     *
     * FIX: use Applicant::active()->where('status', 'Flagged')->count().
     */
    public function test_flagged_excludes_deactivated_applicants(): void
    {
        $this->makeApplicant(['status' => 'Flagged', 'is_active' => true]);
        $this->makeApplicant(['status' => 'Flagged', 'is_active' => false]);

        $this->assertSame(1, $this->stats()['flagged']);
    }

    public function test_pending_duplicates_counts_pending_flags_only(): void
    {
        \App\Models\DuplicateFlag::factory()->count(2)->create(['resolution_status' => 'Pending']);
        \App\Models\DuplicateFlag::factory()->create(['resolution_status' => 'Merged']);

        $this->assertSame(2, $this->stats()['pending_duplicates']);
    }

    public function test_stats_has_all_four_keys(): void
    {
        $this->assertSame(
            ['total_applicants', 'this_month', 'pending_duplicates', 'flagged'],
            array_keys($this->stats())
        );
    }
}