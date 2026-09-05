<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\AuditLog;
use Livewire\Livewire;
use App\Livewire\ApplicantManagement;
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

        $staffRole         = Role::factory()->create(['slug' => 'staff', 'name' => 'Staff']);
        $adminRole         = Role::factory()->create(['slug' => 'admin', 'name' => 'Administrator']);
        $this->staffUser   = User::factory()->create(['role_id' => $staffRole->id]);
        $this->adminUser   = User::factory()->create(['role_id' => $adminRole->id]);
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
     */
    public function test_search_by_name_filters_correctly(): void
    {
        $santos = $this->makeApplicant(['last_name' => 'Santos', 'first_name' => 'Juan']);
        $reyes  = $this->makeApplicant(['last_name' => 'Reyes',  'first_name' => 'Maria']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', 'Santos')
            ->assertSee('Santos')
            ->assertDontSee('Reyes');
    }

    /**
     * Searching by reference_id must find the exact applicant.
     *
     * WHY: Residents present their reference_id at the PESO office.
     * Staff must be able to look up an applicant by this ID instantly.
     */
    public function test_search_by_reference_id_finds_applicant(): void
    {
        $target = $this->makeApplicant(['reference_id' => 'PESO-TESTREF123']);
        $other  = $this->makeApplicant(['reference_id' => 'PESO-OTHERID456']);

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->set('search', 'PESO-TESTREF123')
            ->assertSee('PESO-TESTREF123')
            ->assertDontSee('PESO-OTHERID456');
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
        $flagged = $this->makeApplicant(['status' => 'Flagged']);
        $pending = $this->makeApplicant(['status' => 'Pending']);
        $verified= $this->makeApplicant(['status' => 'Verified']);

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
        $inactive = $this->makeApplicant(['is_active' => false]);

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
     */
    public function test_deactivation_soft_deletes_does_not_hard_delete(): void
    {
        $applicant = $this->makeApplicant(['is_active' => true]);
        $id        = $applicant->id;

        Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class)
            ->call('deactivate', $id);

        // Record must still exist in DB
        $this->assertDatabaseHas('applicants', ['id' => $id],
            'Deactivation must NOT hard-delete the record from the database.'
        );

        // But must be marked inactive
        $this->assertDatabaseHas('applicants', [
            'id'        => $id,
            'is_active' => false,
            'status'    => 'Inactive',
        ]);
    }

    /**
     * Deactivation must create an audit log entry.
     */
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
     * who is on page 3 of results and then types a new search term
     * would see page 3 of the new (smaller) result set — which might
     * be empty even if results exist on page 1.
     */
    public function test_search_change_resets_pagination_to_page_one(): void
    {
        // Create enough applicants to have multiple pages
        for ($i = 0; $i < 25; $i++) {
            $this->makeApplicant(['last_name' => "Applicant{$i}"]);
        }

        $component = Livewire::actingAs($this->staffUser)
            ->test(ApplicantManagement::class);

        // Simulate being on page 2
        $component->call('nextPage');

        // Now change search term
        $component->set('search', 'Applicant1');

        // Page should reset to 1
        $this->assertEquals(1, $component->get('page'),
            'Pagination must reset to page 1 when the search term changes.'
        );
    }
}
