<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use App\Livewire\ApplicantManagement;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * ApplicantManagement is where PESO staff interact with resident data
 * every single working day. Bugs here have direct consequences:
 *
 * - Filter bug → staff sees wrong records → wrong person gets referred to employer
 * - Edit saves wrong fields → applicant data corrupted → DOLE report is wrong
 * - Deactivation hard-deletes instead of soft-deletes → data lost forever,
 *   RA 10173 audit trail broken
 * - Audit log not written on edit → no evidence trail for investigations
 *
 * TESTING MINDSET:
 * I test the FILTERS as a group because they all use Eloquent scopes
 * from the Applicant model. The risk is that one scope breaks
 * and the analytics and management views silently return wrong data.
 * I also test the SIDE EFFECTS of edit and deactivate — the audit log
 * and the is_active flag — not just the happy path response.
 *
 * NOTE ON REFERENCE IDS
 * ─────────────────────
 * The Applicant model generates its own reference_id on create, so any
 * value passed to the factory is overwritten. Tests read the stored value
 * with ->refresh()->reference_id instead of hard-coding one.
 *
 * NOTE ON FAILING TESTS
 * ─────────────────────
 * Some tests below are EXPECTED TO FAIL. Each one documents a real bug in
 * the application, not a test problem. Each has a "KNOWN BUG" block above it
 * with the reason and steps to reproduce it manually in the browser.
 * Do not "fix" these tests by weakening their assertions; fix the app code.
 */
class ApplicantManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;
    private User $adminUser;
    private Municipality $municipality;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles are Spatie roles (name + guard_name). RoleSeeder creates the
        // admin and staff roles plus one user for each, already assigned.
        $this->seed(RoleSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->adminUser = User::where('email', 'admin@peso-catanduanes.gov.ph')->firstOrFail();
        $this->staffUser = User::where('email', 'staff@peso-catanduanes.gov.ph')->firstOrFail();

        $this->municipality = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay     = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
            'name'            => 'San Jose',
        ]);
    }

    private function makeApplicant(array $overrides = []): Applicant
    {
        return Applicant::factory()->create(array_merge([
            'barangay_id' => $this->barangay->id,
            'is_active'   => true,
            'status'      => 'Pending',
        ], $overrides));
    }

    /**
     * Inactive rows are created with is_active = false and status 'Pending'.
     * The applicants.status CHECK constraint rejects 'Inactive' (see KNOWN BUG
     * on test_deactivation_creates_audit_log). The Inactive tab filters on
     * is_active, so these tests still check the right behavior.
     */
    private function makeInactiveApplicant(array $overrides = []): Applicant
    {
        return $this->makeApplicant(array_merge([
            'is_active' => false,
            'status'    => 'Pending',
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────
    // PAGE ACCESS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Staff can access the applicant management page.
     */
    public function test_staff_can_access_applicant_management_page(): void
    {
        $this->actingAs($this->staffUser);
        $this->get(route('applicants'))->assertStatus(200);
    }

    /**
     * Guest cannot access the page.
     */
    public function test_guest_cannot_access_applicant_management(): void
    {
        $this->get(route('applicants'))->assertRedirect(route('login'));
    }

    // ─────────────────────────────────────────────────────────────────
    // SEARCH FILTER
    // ─────────────────────────────────────────────────────────────────

    /**
     * Searching by last name must return only matching applicants.
     *
     * WHY: Staff use search constantly to find a specific applicant.
     * If the search returns everyone regardless of the query,
     * staff would have to manually scan hundreds of rows.
     *
     * FIX NOTE: The Santos applicant's full name renders with the factory's
     * middle name, so the name "Reyes" can appear on the Santos row. The
     * negative check therefore uses the reference ID, which is unique.
     */
    public function test_search_by_name_filters_correctly(): void
    {
        $santos = $this->makeApplicant(['last_name' => 'Santos', 'first_name' => 'Juan']);
        $reyes  = $this->makeApplicant(['last_name' => 'Reyes',  'first_name' => 'Maria']);

        $santos->refresh();
        $reyes->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', 'Santos')
            ->assertSee($santos->reference_id)
            ->assertDontSee($reyes->reference_id);
    }

    /**
     * Searching by reference_id must find the exact applicant.
     *
     * WHY: Residents present their reference_id at the PESO office.
     * Staff must be able to look up an applicant by this ID instantly.
     *
     * FIX NOTE: The model generates reference_id on create, overwriting the
     * value passed to the factory. The test reads the stored value instead.
     */
    public function test_search_by_reference_id_finds_applicant(): void
    {
        $target = $this->makeApplicant();
        $other  = $this->makeApplicant();

        $target->refresh();
        $other->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', $target->reference_id)
            ->assertSee($target->reference_id)
            ->assertDontSee($other->reference_id);
    }

    // ─────────────────────────────────────────────────────────────────
    // STATUS FILTER
    // ─────────────────────────────────────────────────────────────────

    /**
     * Filtering by status 'Flagged' must show only flagged applicants.
     *
     * WHY: Staff use the status filter to quickly pull up all
     * flagged applicants for batch review. If the filter is broken,
     * they see all applicants and have to manually identify flags.
     */
    public function test_status_filter_shows_only_matching_status(): void
    {
        $flagged  = $this->makeApplicant(['status' => 'Flagged']);
        $pending  = $this->makeApplicant(['status' => 'Pending']);
        $verified = $this->makeApplicant(['status' => 'Verified']);

        $flagged->refresh();
        $pending->refresh();
        $verified->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('filterStatus', 'Flagged')
            ->assertSee($flagged->reference_id)
            ->assertDontSee($pending->reference_id)
            ->assertDontSee($verified->reference_id);
    }

    // ─────────────────────────────────────────────────────────────────
    // INACTIVE APPLICANTS EXCLUDED
    // ─────────────────────────────────────────────────────────────────

    /**
     * Deactivated applicants must NOT appear in the management table.
     *
     * WHY: The table uses the active() scope. If it leaks inactive
     * records, staff would see deactivated applicants in searches
     * and might accidentally re-verify or re-refer a deactivated record.
     */
    public function test_inactive_applicants_not_shown_in_table(): void
    {
        $active   = $this->makeApplicant(['is_active' => true]);
        $inactive = $this->makeInactiveApplicant();

        $active->refresh();
        $inactive->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->assertSee($active->reference_id)
            ->assertDontSee($inactive->reference_id);
    }

    // ─────────────────────────────────────────────────────────────────
    // EDIT
    // ─────────────────────────────────────────────────────────────────

    /**
     * Editing an applicant must update the correct fields in the DB.
     *
     * WHY: An incorrect update (saving to wrong record or wrong field)
     * would corrupt data. This test verifies the UPDATE is precisely
     * targeted and contains the correct values.
     */
    public function test_edit_saves_correct_data_to_database(): void
    {
        $applicant = $this->makeApplicant([
            'contact_number' => '09171234567',
            'status'         => 'Pending',
        ]);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.contact_number', '09289876543')  // update contact
            ->set('editData.status', 'Verified')             // update status
            ->call('saveEdit');

        $this->assertDatabaseHas('applicants', [
            'id'             => $applicant->id,
            'contact_number' => '09289876543',
            'status'         => 'Verified',
        ]);
    }

    /**
     * Editing an applicant must create an audit log entry.
     *
     * WHY: RA 10173 requires that modifications to personal data
     * are logged. Without this, there is no evidence of who changed
     * what and when — the audit trail is broken.
     */
    public function test_edit_creates_audit_log_entry(): void
    {
        $applicant = $this->makeApplicant();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.status', 'Verified')
            ->call('saveEdit');

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'APPLICANT_UPDATED',
            'model_type' => 'Applicant',
            'model_id'   => $applicant->id,
            'user_id'    => $this->staffUser->id,
        ]);
    }

    /**
     * Edit validation must block saving with an empty last name.
     *
     * WHY: The edit modal has validation rules. If they are not
     * enforced server-side, a staff member could accidentally clear
     * a required field and save a nameless record.
     */
    public function test_edit_validation_blocks_empty_last_name(): void
    {
        $applicant = $this->makeApplicant(['last_name' => 'Santos']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.last_name', '')  // EMPTY — invalid
            ->call('saveEdit')
            ->assertHasErrors(['editData.last_name']);

        // Original name must be unchanged
        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'last_name' => 'Santos',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // DEACTIVATION (Soft Delete)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Deactivation must set is_active = false and status = 'Inactive'.
     * It must NOT delete the record from the database.
     *
     * WHY: This is the most critical behavioral requirement.
     * Government records must be retained. If deactivation performs
     * a hard delete, PESO violates data retention obligations and
     * breaks the RA 10173 audit trail for that applicant.
     *
     * FIX NOTE: The earlier version passed a message string as the third
     * argument to assertDatabaseHas(), which Laravel reads as a connection
     * name. The existence check now uses assertModelExists().
     *
     * NOTE: This test also depends on the status CHECK constraint issue.
     * If it fails with "CHECK constraint failed: status", see the ROOT CAUSE
     * comment above test_deactivation_creates_audit_log.
     */
    public function test_deactivation_soft_deletes_does_not_hard_delete(): void
    {
        $applicant = $this->makeApplicant(['is_active' => true]);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('deactivate', $applicant->id);

        // Record must still exist in DB (soft-delete, not hard-delete)
        $this->assertModelExists($applicant);

        // But must be marked inactive
        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'is_active' => false,
            'status'    => 'Inactive',
        ]);
    }

    /**
     * KNOWN BUG — expected to FAIL.
     *
     * Deactivating an applicant does not write an APPLICANT_DEACTIVATED row
     * to audit_logs. Editing does write an audit row through the same
     * AuditLogService, so the service itself works.
     *
     * WHY IT MATTERS: RA 10173 requires an audit trail for changes to
     * personal data. Deactivations with no record cannot be investigated.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as staff (staff@peso-catanduanes.gov.ph).
     *   2. Go to /applicants, keep the "Active" tab, and click Deactivate on any row.
     *   3. Confirm the browser prompt. A red error toast should appear
     *      (or, if the bug is not present, a green "Applicant deactivated." toast).
     *   4. Log out, then log in as admin (admin@peso-catanduanes.gov.ph).
     *   5. Go to /admin/audit-logs and filter for APPLICANT_DEACTIVATED.
     *   6. BUG: no row appears for the applicant you deactivated.
     *   7. Check storage/logs/laravel.log for a QueryException mentioning
     *      "CHECK constraint failed: status" around the time of step 2.
     */
    // ROOT CAUSE (likely): deactivate() sets status = 'Inactive', which the
    // applicants.status CHECK constraint rejects. The exception is caught and
    // shown as an error toast, so no audit row is written and is_active stays true.
    // Check the constraint in the migration and decide whether 'Inactive' should be
    // an allowed status, or whether deactivate() should only change is_active.
    // If test_deactivation_soft_deletes_does_not_hard_delete also fails, it is the same bug.
    public function test_deactivation_creates_audit_log(): void
    {
        $applicant = $this->makeApplicant();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('deactivate', $applicant->id);

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'APPLICANT_DEACTIVATED',
            'model_type' => 'Applicant',
            'model_id'   => $applicant->id,
            'user_id'    => $this->staffUser->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // PAGINATION RESET ON FILTER CHANGE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Changing the search term must reset pagination to page 1.
     *
     * WHY: If pagination is not reset on filter change, a staff member
     * who is on page 2 of results and then types a new search term
     * would see page 2 of the new (smaller) result set — which might
     * be empty even if results exist on page 1.
     *
     * FIX NOTE: Livewire's WithPagination keeps the page number in the
     * paginator state, not in a public "page" property, so the test reads
     * it with getPage(). It also checks that the test starts on page 2, so
     * the reset is actually being exercised.
     */
    public function test_search_change_resets_pagination_to_page_one(): void
    {
        // Create enough applicants to have multiple pages
        for ($i = 0; $i < 25; $i++) {
            $this->makeApplicant(['last_name' => "Applicant{$i}"]);
        }

        $component = Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class);

        // Move to page 2 and confirm we are actually there
        $component->call('nextPage');
        $this->assertEquals(2, $component->instance()->getPage(),
            'Precondition failed: the test must start on page 2.'
        );

        // Now change the search term
        $component->set('search', 'Applicant1');

        // Page should reset to 1
        $this->assertEquals(1, $component->instance()->getPage(),
            'Pagination must reset to page 1 when the search term changes.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // EDGE CASES: SEARCH
    // ─────────────────────────────────────────────────────────────────

    /**
     * An empty search must not filter anything out.
     */
    public function test_empty_search_returns_all_active_applicants(): void
    {
        $a = $this->makeApplicant();
        $b = $this->makeApplicant();

        $a->refresh();
        $b->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', '')
            ->assertSee($a->reference_id)
            ->assertSee($b->reference_id);
    }

    /**
     * Partial names should match, since staff often type only part of a name.
     */
    public function test_search_matches_partial_first_name(): void
    {
        $target = $this->makeApplicant(['first_name' => 'Benedicto']);
        $other  = $this->makeApplicant(['first_name' => 'Carla']);

        $target->refresh();
        $other->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', 'Bene')
            ->assertSee($target->reference_id)
            ->assertDontSee($other->reference_id);
    }

    /**
     * KNOWN BUG — expected to FAIL.
     *
     * LIKE wildcards in user input are not escaped. Searching for "%" builds
     * LIKE '%%%', which matches every row, so the search returns everyone.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as staff and go to /applicants with at least two applicants.
     *   2. Type % in the Search box and press Enter (or click Filter).
     *   3. BUG: every applicant appears. Expected: no rows (no name contains "%").
     *
     * FIX: in ApplicantManagement::render(), escape the term before LIKE, e.g.
     *   $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search);
     *   and use $term in each like clause.
     */
    public function test_search_treats_percent_sign_as_literal_text(): void
    {
        $a = $this->makeApplicant(['last_name' => 'Santos']);
        $b = $this->makeApplicant(['last_name' => 'Reyes']);

        $a->refresh();
        $b->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', '%')
            ->assertDontSee($a->reference_id)
            ->assertDontSee($b->reference_id);
    }

    /**
     * KNOWN BUG — expected to FAIL.
     *
     * Same root cause as the % test. "_" is a single-character LIKE wildcard,
     * so the search for "_" matches any name with at least one character.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as staff and go to /applicants with at least one applicant.
     *   2. Type _ in the Search box and press Enter.
     *   3. BUG: the applicant appears. Expected: no rows.
     *
     * FIX: same as the % test (escape the term before LIKE).
     */
    public function test_search_treats_underscore_as_literal_text(): void
    {
        $a = $this->makeApplicant(['last_name' => 'Santos']);
        $a->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', '_')
            ->assertDontSee($a->reference_id);
    }

    // ─────────────────────────────────────────────────────────────────
    // EDGE CASES: FILTERS AND TABS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Multiple filters are combined with AND, so a record must match all of them.
     */
    public function test_combined_filters_must_all_match(): void
    {
        $otherBarangay = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
            'name'            => 'Bagumbayan',
        ]);

        $match         = $this->makeApplicant(['status' => 'Flagged']);
        $wrongStatus   = $this->makeApplicant(['status' => 'Pending']);
        $wrongBarangay = $this->makeApplicant(['status' => 'Flagged', 'barangay_id' => $otherBarangay->id]);

        $match->refresh();
        $wrongStatus->refresh();
        $wrongBarangay->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('filterStatus', 'Flagged')
            ->set('filterBarangay', (string) $this->barangay->id)
            ->assertSee($match->reference_id)
            ->assertDontSee($wrongStatus->reference_id)
            ->assertDontSee($wrongBarangay->reference_id);
    }

    /**
     * The barangay filter alone should return only applicants in that barangay.
     */
    public function test_barangay_filter_limits_results_to_that_barangay(): void
    {
        $otherBarangay = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
            'name'            => 'Bagumbayan',
        ]);

        $inBarangay  = $this->makeApplicant();
        $outBarangay = $this->makeApplicant(['barangay_id' => $otherBarangay->id]);

        $inBarangay->refresh();
        $outBarangay->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('filterBarangay', (string) $this->barangay->id)
            ->assertSee($inBarangay->reference_id)
            ->assertDontSee($outBarangay->reference_id);
    }

    /**
     * Clear must reset search and status filters and show everyone again.
     */
    public function test_clear_filters_resets_search_and_status(): void
    {
        $pending = $this->makeApplicant(['status' => 'Pending']);
        $flagged = $this->makeApplicant(['status' => 'Flagged']);

        $pending->refresh();
        $flagged->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('filterStatus', 'Flagged')
            ->set('search', 'zzz-no-match')
            ->assertDontSee($pending->reference_id)
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('filterStatus', '')
            ->assertSee($pending->reference_id)
            ->assertSee($flagged->reference_id);
    }

    /**
     * The Inactive tab must show only deactivated applicants.
     */
    public function test_inactive_tab_shows_only_deactivated_applicants(): void
    {
        $active   = $this->makeApplicant(['is_active' => true]);
        $inactive = $this->makeInactiveApplicant();

        $active->refresh();
        $inactive->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('setViewStatus', 'inactive')
            ->assertSee($inactive->reference_id)
            ->assertDontSee($active->reference_id);
    }

    /**
     * The All tab must show both active and deactivated applicants.
     */
    public function test_all_tab_shows_active_and_inactive_applicants(): void
    {
        $active   = $this->makeApplicant(['is_active' => true]);
        $inactive = $this->makeInactiveApplicant();

        $active->refresh();
        $inactive->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('setViewStatus', 'all')
            ->assertSee($active->reference_id)
            ->assertSee($inactive->reference_id);
    }

    /**
     * An unknown tab value from the client must fall back to Active.
     */
    public function test_invalid_view_status_falls_back_to_active(): void
    {
        $active   = $this->makeApplicant(['is_active' => true]);
        $inactive = $this->makeInactiveApplicant();

        $active->refresh();
        $inactive->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('setViewStatus', 'bogus')
            ->assertSet('viewStatus', 'active')
            ->assertSee($active->reference_id)
            ->assertDontSee($inactive->reference_id);
    }

    /**
     * A deactivated applicant must not be found even by exact reference ID.
     *
     * FIX NOTE: The earlier version searched for a hard-coded reference ID,
     * which the model overwrote on create, so the test passed for the wrong
     * reason. It now searches for the stored value.
     */
    public function test_deactivated_applicant_not_found_by_reference_id_search(): void
    {
        $applicant = $this->makeInactiveApplicant();

        $applicant->refresh();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', $applicant->reference_id)
            ->assertDontSee($applicant->reference_id);
    }

    // ─────────────────────────────────────────────────────────────────
    // EDGE CASES: PAGINATION
    // ─────────────────────────────────────────────────────────────────

    /**
     * 21 records should give a full first page of 20 and one record on page 2.
     */
    public function test_pagination_gives_twenty_per_page_with_remainder_on_next_page(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->makeApplicant();
        }

        $component = Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class);

        $this->assertCount(20, $component->viewData('applicants'));

        $component->call('gotoPage', 2);

        $this->assertCount(1, $component->viewData('applicants'));
    }

    // ─────────────────────────────────────────────────────────────────
    // EDGE CASES: AUTHORIZATION
    // ─────────────────────────────────────────────────────────────────

    /**
     * openView() calls abort(403) for users without a role. Livewire does not
     * rethrow that in tests, so check the observable result: the modal stays closed.
     */
    public function test_user_without_role_cannot_open_view(): void
    {
        $outsider  = User::factory()->create();
        $applicant = $this->makeApplicant();

        Livewire::actingAs($outsider)
            ->test(ApplicantManagement::class)
            ->call('openView', $applicant->id)
            ->assertSet('showView', false)
            ->assertSet('viewingId', null);
    }

    /**
     * A user with no role must not be able to verify an applicant.
     */
    public function test_user_without_role_cannot_verify(): void
    {
        $outsider  = User::factory()->create();
        $applicant = $this->makeApplicant(['status' => 'Pending']);

        Livewire::actingAs($outsider)
            ->test(ApplicantManagement::class)
            ->call('verified', $applicant->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('applicants', [
            'id'     => $applicant->id,
            'status' => 'Pending',
        ]);
    }

    /**
     * A user with no role must not be able to deactivate an applicant.
     */
    public function test_user_without_role_cannot_deactivate(): void
    {
        $outsider  = User::factory()->create();
        $applicant = $this->makeApplicant(['is_active' => true]);

        Livewire::actingAs($outsider)
            ->test(ApplicantManagement::class)
            ->call('deactivate', $applicant->id);

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'is_active' => true,
        ]);
    }

    /**
     * A user with no role must not be able to save edits.
     */
    public function test_user_without_role_cannot_save_edit(): void
    {
        $outsider  = User::factory()->create();
        $applicant = $this->makeApplicant(['last_name' => 'Santos']);

        Livewire::actingAs($outsider)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.last_name', 'Hacked')
            ->call('saveEdit');

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'last_name' => 'Santos',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // EDGE CASES: VERIFY RULES
    // ─────────────────────────────────────────────────────────────────

    /**
     * A Pending applicant should move to Verified.
     */
    public function test_pending_applicant_can_be_verified(): void
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
     * A Flagged applicant must be reviewed first, so verify must refuse.
     */
    public function test_flagged_applicant_cannot_be_verified(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Flagged']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('verified', $applicant->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('applicants', [
            'id'     => $applicant->id,
            'status' => 'Flagged',
        ]);
    }

    /**
     * Verifying an already-Verified applicant must be refused.
     */
    public function test_already_verified_applicant_is_not_reverified(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Verified']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('verified', $applicant->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('applicants', [
            'id'     => $applicant->id,
            'status' => 'Verified',
        ]);
    }

    /**
     * Verifying a non-existent ID must show an error and not throw.
     */
    public function test_verifying_nonexistent_applicant_dispatches_error_without_throwing(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('verified', 999999)
            ->assertDispatched('notify');
    }

    /**
     * KNOWN BUG — expected to FAIL.
     *
     * verified() refuses to verify a Flagged applicant, but saveEdit() accepts
     * any status in its validation list. A Flagged applicant can be set to
     * Verified through the edit path, skipping the review that verified() enforces.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   The edit modal has no status dropdown, so this needs the browser console.
     *   1. Log in as staff and go to /applicants. Filter Status = Flagged.
     *      Keep one Flagged row in view.
     *   2. Open DevTools (F12) → Console. Find the component id on the page:
     *        document.querySelector('[wire:id]').getAttribute('wire:id')
     *      Note: if the page has several components, pick the one that contains the table.
     *   3. Click Edit on the Flagged row so the modal opens.
     *   4. In the Console, run (replace COMPONENT_ID):
     *        Livewire.find('COMPONENT_ID').set('editData.status', 'Verified')
     *        Livewire.find('COMPONENT_ID').call('saveEdit')
     *   5. Reload the page and filter for that applicant.
     *   6. BUG: the applicant shows as Verified. Expected: still Flagged.
     *
     * FIX: in saveEdit(), refuse the change if the stored status is Flagged
     * and the new status is Verified, or remove 'Verified' from the editable list.
     */
    public function test_edit_cannot_bypass_flagged_check_to_verify(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Flagged']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.status', 'Verified')
            ->call('saveEdit');

        $this->assertDatabaseHas('applicants', [
            'id'     => $applicant->id,
            'status' => 'Flagged',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // EDGE CASES: ACTIVATE AND EDIT VALIDATION
    // ─────────────────────────────────────────────────────────────────

    /**
     * Activating a deactivated applicant must restore them to Pending for re-verification.
     */
    public function test_activate_restores_deactivated_applicant_to_pending(): void
    {
        $applicant = $this->makeInactiveApplicant();

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('activate', $applicant->id);

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'is_active' => true,
            'status'    => 'Pending',
        ]);
    }

    /**
     * A status outside the allowed list must be rejected by validation.
     */
    public function test_edit_rejects_status_outside_allowed_list(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Pending']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.status', 'Hacked')
            ->call('saveEdit')
            ->assertHasErrors(['editData.status']);

        $this->assertDatabaseHas('applicants', [
            'id'     => $applicant->id,
            'status' => 'Pending',
        ]);
    }

    /**
     * Contact number over 20 characters must be rejected.
     */
    public function test_edit_rejects_contact_number_longer_than_twenty_chars(): void
    {
        $applicant = $this->makeApplicant(['contact_number' => '09171234567']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.contact_number', str_repeat('9', 21))
            ->call('saveEdit')
            ->assertHasErrors(['editData.contact_number']);

        $this->assertDatabaseHas('applicants', [
            'id'             => $applicant->id,
            'contact_number' => '09171234567',
        ]);
    }

    /**
     * First name over 100 characters must be rejected.
     */
    public function test_edit_rejects_first_name_longer_than_one_hundred_chars(): void
    {
        $applicant = $this->makeApplicant(['first_name' => 'Juan']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.first_name', str_repeat('A', 101))
            ->call('saveEdit')
            ->assertHasErrors(['editData.first_name']);
    }

    /**
     * KNOWN BUG — expected to FAIL.
     *
     * "Duplicated" appears in the status filter but not in the edit validation
     * list. saveEdit() submits the record's current status, so validation fails
     * on status for any Duplicated applicant. The edit modal has no status
     * field, so the user sees only the generic "Please fix the highlighted
     * fields" toast and no highlighted field.
     *
     * HOW TO REPRODUCE IN THE BROWSER:
     *   1. Log in as staff and go to /applicants.
     *   2. Set Status filter to Duplicated and click Filter. Note: the record
     *      must already be Duplicated in the database. If none exist, set one
     *      in the DB or through tinker:
     *        php artisan tinker
     *        App\Models\Applicant::first()->update(['status' => 'Duplicated']);
     *   3. Click Edit on that row and change the Last Name slightly.
     *   4. Click Save Changes.
     *   5. BUG: the red toast "Please fix the highlighted fields." appears, nothing
     *      is highlighted, and the change is not saved.
     *      Expected: the change saves, or the status choice is handled explicitly.
     *
     * FIX: add 'Duplicated' to the 'editData.status' in:() list in saveEdit(),
     * or exclude status from the validation when the status field is not shown.
     */
    public function test_duplicated_applicant_can_be_saved_from_edit(): void
    {
        $applicant = $this->makeApplicant(['status' => 'Duplicated', 'last_name' => 'Santos']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('openEdit', $applicant->id)
            ->set('editData.last_name', 'Santos-Reyes')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applicants', [
            'id'        => $applicant->id,
            'last_name' => 'Santos-Reyes',
        ]);
    }
}