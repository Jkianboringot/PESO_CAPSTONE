<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Applicant;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * RA 10173 (Data Privacy Act) requires that every access and modification
 * of personal data is logged. AuditLogService is the single mechanism
 * that satisfies this legal requirement.
 *
 * TESTING MINDSET:
 * I am not testing "does AuditLog::create() work" — that is Laravel's job.
 * I am testing: "Does AuditLogService correctly CAPTURE what is needed
 * for a legal audit trail?" That means:
 * - Is the correct action string recorded?
 * - Is the model type and ID linked?
 * - Is the authenticated user's ID captured?
 * - Is the before/after diff stored correctly?
 * - Does guest (public) registration correctly store NULL for user_id?
 *
 * If any of these fails, PESO cannot demonstrate RA 10173 compliance
 * during a data privacy investigation.
 */
class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuditLogService();
    }

    /**
     * The basic log() method must create exactly one audit_log row
     * with the correct action string and all required fields.
     */
    public function test_log_creates_audit_log_entry_with_correct_action(): void
    {
        // Simulate a logged-in staff member
        $role = Role::factory()->create(['slug' => 'staff']);
        $user = User::factory()->create(['role_id' => $role->id]);
        Auth::login($user);

        $this->service->log('TEST_ACTION');

        // Assert exactly one audit log exists
        $this->assertDatabaseCount('audit_logs', 1);

        // Assert the content is correct
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action'  => 'TEST_ACTION',
        ]);
    }

    /**
     * When a resident submits a registration (no authenticated user),
     * user_id must be NULL — not crash, not throw, not use a default.
     *
     * WHY THIS IS CRITICAL:
     * If the code tries to get Auth::id() when no user is logged in
     * and the method cannot handle null, the entire registration
     * submission would throw a 500 error. Every resident would see
     * an error page when they try to register.
     */
    public function test_guest_action_stores_null_user_id(): void
    {
        // No Auth::login() — simulating a public (guest) action
        Auth::logout();

        $this->service->log('APPLICANT_CREATED');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'action'  => 'APPLICANT_CREATED',
        ]);
    }

    /**
     * When logging an update, the changes array (before/after diff)
     * must be stored correctly as JSON.
     *
     * WHY: The changes column is the actual evidence in an audit trail.
     * "User X changed status from Pending to Verified on this date"
     * is only provable if the before and after states are stored.
     * Without this, the audit log is just a timestamp with no evidence.
     */
    public function test_changes_array_stored_as_json(): void
    {
        $role = Role::factory()->create(['slug' => 'staff']);
        $user = User::factory()->create(['role_id' => $role->id]);
        Auth::login($user);

        $changes = [
            'before' => ['status' => 'Pending', 'contact_number' => '09171234567'],
            'after'  => ['status' => 'Verified', 'contact_number' => '09171234567'],
        ];

        $this->service->log('APPLICANT_UPDATED', null, $changes);

        // Read back from DB
        $log = AuditLog::first();

        // Laravel casts JSON columns to arrays automatically
        $this->assertEquals('Pending',  $log->changes['before']['status']);
        $this->assertEquals('Verified', $log->changes['after']['status']);
    }

    /**
     * logApplicantCreated() must link the correct model type and ID.
     *
     * WHY: Without model_type and model_id, you cannot look up
     * "show me all audit entries for Applicant #42" — which is exactly
     * what a data privacy officer would ask during an investigation.
     */
    public function test_log_applicant_created_links_model_correctly(): void
    {
        $role      = Role::factory()->create(['slug' => 'staff']);
        $user      = User::factory()->create(['role_id' => $role->id]);
        $applicant = Applicant::factory()->create();
        Auth::login($user);

        $this->service->logApplicantCreated($applicant);

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'APPLICANT_CREATED',
            'model_type' => 'Applicant',
            'model_id'   => $applicant->id,
        ]);
    }

    /**
     * logApplicantUpdated() must record the before/after diff.
     */
    public function test_log_applicant_updated_stores_diff(): void
    {
        $role      = Role::factory()->create(['slug' => 'staff']);
        $user      = User::factory()->create(['role_id' => $role->id]);
        $applicant = Applicant::factory()->create(['status' => 'Pending']);
        Auth::login($user);

        $before = ['status' => 'Pending'];
        $after  = ['status' => 'Verified'];

        $this->service->logApplicantUpdated($applicant, [
            'before' => $before,
            'after'  => $after,
        ]);

        $log = AuditLog::where('action', 'APPLICANT_UPDATED')->first();
        $this->assertNotNull($log);
        $this->assertEquals('Pending',  $log->changes['before']['status']);
        $this->assertEquals('Verified', $log->changes['after']['status']);
    }

    /**
     * logLogin() must record USER_LOGIN with the correct user ID.
     *
     * WHY: Login events are the most important security events.
     * If someone unauthorized accesses the system, the login log
     * is the first place investigators look.
     */
    public function test_log_login_records_correct_user(): void
    {
        $role = Role::factory()->create(['slug' => 'staff']);
        $user = User::factory()->create(['role_id' => $role->id]);
        Auth::login($user);

        $this->service->logLogin($user);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'USER_LOGIN',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Multiple log entries for the same model must all be persisted.
     * The audit log must NEVER overwrite — only append.
     *
     * WHY: An audit log that overwrites is not an audit log.
     * If edit #1 overwrites the original creation record,
     * the history is incomplete and legally useless.
     */
    public function test_multiple_log_entries_all_persisted(): void
    {
        $role      = Role::factory()->create(['slug' => 'staff']);
        $user      = User::factory()->create(['role_id' => $role->id]);
        $applicant = Applicant::factory()->create();
        Auth::login($user);

        // Simulate: created, then edited twice
        $this->service->logApplicantCreated($applicant);
        $this->service->logApplicantUpdated($applicant, ['before' => ['status' => 'Pending'], 'after' => ['status' => 'Verified']]);
        $this->service->logApplicantUpdated($applicant, ['before' => ['status' => 'Verified'], 'after' => ['status' => 'Flagged']]);

        // All 3 entries must exist — never overwritten
        $count = AuditLog::where('model_type', 'Applicant')
            ->where('model_id', $applicant->id)
            ->count();

        $this->assertEquals(3, $count,
            'Each log call must create a NEW row. Audit logs must never overwrite.'
        );
    }
}
