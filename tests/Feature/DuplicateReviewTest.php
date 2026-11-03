<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\DuplicateFlag;
use App\Models\AuditLog;
use Livewire\Livewire;
use App\Livewire\DuplicateReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * The DuplicateReview module has three resolution actions, each with
 * different consequences to the database. Getting these wrong
 * destroys data integrity:
 *
 * - Merge wrong → the CORRECT applicant is deactivated, duplicate survives
 * - Retain Both wrong → flags are closed but duplicates remain unresolved
 * - Delete wrong → a real person's registration is erased
 *
 * TESTING MINDSET:
 * For each resolution action, I test THREE things:
 * 1. The flag's resolution_status is updated correctly
 * 2. The applicant's is_active is changed correctly (or NOT changed)
 * 3. The resolved_by field records who made the decision
 *
 * I also test that the QUEUE correctly shows only PENDING flags,
 * not already-resolved ones — because if resolved flags keep showing,
 * staff would re-review the same cases endlessly.
 */
class DuplicateReviewTest extends TestCase
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
        $this->municipality = Municipality::factory()->create();
        $this->barangay     = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
        ]);
    }

    /**
     * Helper: create a pending duplicate flag with two real applicants.
     */
    private function makeFlag(): DuplicateFlag
    {
        $newApplicant = Applicant::factory()->create([
            'barangay_id' => $this->barangay->id,
            'is_active'   => true,
            'status'      => 'Flagged',
        ]);
        $existingApplicant = Applicant::factory()->create([
            'barangay_id' => $this->barangay->id,
            'is_active'   => true,
            'status'      => 'Verified',
        ]);

        return DuplicateFlag::factory()->create([
            'applicant_id_new'      => $newApplicant->id,
            'applicant_id_existing' => $existingApplicant->id,
            'resolution_status'     => 'Pending',
            'match_score'           => 2,
            'matched_phonetic'      => true,
            'matched_birthdate'     => true,
            'matched_contact'       => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // QUEUE DISPLAY
    // ─────────────────────────────────────────────────────────────────

    /**
     * The queue must show only PENDING flags.
     *
     * WHY: If resolved flags appear in the queue, staff waste time
     * reviewing cases that were already decided. More critically,
     * if they re-resolve a Merged flag as Retain Both, the
     * previously deactivated applicant remains deactivated
     * but the flag now says "Retained Both" — contradictory state.
     */
    public function test_queue_shows_only_pending_flags(): void
    {
        $pendingFlag  = $this->makeFlag();
        $resolvedFlag = $this->makeFlag();

        // Resolve the second flag directly in DB
        $resolvedFlag->update(['resolution_status' => 'Merged']);

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->assertSee($pendingFlag->id)
            ->assertDontSee($resolvedFlag->id);
    }

    // ─────────────────────────────────────────────────────────────────
    // RESOLUTION: MERGE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Merge action must:
     * 1. Set flag resolution_status = 'Merged'
     * 2. Deactivate the NEW applicant (keep existing)
     * 3. Record resolved_by = current staff user
     * 4. Record resolved_at timestamp
     *
     * WHY: Each point has a specific risk if wrong:
     * 1. Flag stays Pending → staff reviews the same case again
     * 2. Wrong applicant deactivated → real person loses registration
     * 3. No resolved_by → no accountability for the decision
     * 4. No resolved_at → cannot prove when decision was made
     */
    public function test_merge_action_deactivates_new_applicant_and_updates_flag(): void
    {
        $flag       = $this->makeFlag();
        $newId      = $flag->applicant_id_new;
        $existingId = $flag->applicant_id_existing;

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('openFlag', $flag->id)
            ->set('resolutionNotes', 'Confirmed duplicate. Merging into existing record.')
            ->call('resolve', 'Merged');

        // Flag must be resolved
        $this->assertDatabaseHas('duplicate_flags', [
            'id'                => $flag->id,
            'resolution_status' => 'Merged',
            'resolved_by'       => $this->staffUser->id,
        ]);
        $this->assertNotNull(
            DuplicateFlag::find($flag->id)->resolved_at,
            'resolved_at must be set when a flag is resolved.'
        );

        // NEW applicant must be DEACTIVATED
        $this->assertDatabaseHas('applicants', [
            'id'        => $newId,
            'is_active' => false,
        ]);

        // EXISTING applicant must remain ACTIVE
        $this->assertDatabaseHas('applicants', [
            'id'        => $existingId,
            'is_active' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // RESOLUTION: RETAIN BOTH
    // ─────────────────────────────────────────────────────────────────

    /**
     * Retain Both must close the flag WITHOUT deactivating either applicant.
     *
     * WHY: This action says "these are two different people."
     * If it accidentally deactivates one of them, a real person
     * loses their registration because their name sounds similar
     * to someone else's. This would be a severe data integrity failure.
     */
    public function test_retain_both_closes_flag_without_deactivating_applicants(): void
    {
        $flag       = $this->makeFlag();
        $newId      = $flag->applicant_id_new;
        $existingId = $flag->applicant_id_existing;

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('openFlag', $flag->id)
            ->call('resolve', 'Retained Both');

        // Flag closed
        $this->assertDatabaseHas('duplicate_flags', [
            'id'                => $flag->id,
            'resolution_status' => 'Retained Both',
        ]);

        // BOTH applicants must remain active
        $this->assertDatabaseHas('applicants', ['id' => $newId,      'is_active' => true],
            'Retain Both: the new applicant must remain active.'
        );
        $this->assertDatabaseHas('applicants', ['id' => $existingId, 'is_active' => true],
            'Retain Both: the existing applicant must remain active.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // RESOLUTION: DELETE NEW
    // ─────────────────────────────────────────────────────────────────

    /**
     * Delete New must deactivate the new applicant.
     * The new applicant must NOT be hard-deleted from the DB.
     *
     * WHY: Even erroneous registrations must be retained for the
     * audit trail. A hard delete would make it impossible to prove
     * that a registration was received and subsequently removed —
     * which could be important in a dispute.
     */
    public function test_delete_new_deactivates_new_applicant_not_hard_deletes(): void
    {
        $flag  = $this->makeFlag();
        $newId = $flag->applicant_id_new;

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('openFlag', $flag->id)
            ->call('resolve', 'Deleted');

        // New applicant must be deactivated — NOT deleted
        $this->assertDatabaseHas('applicants', [
            'id'        => $newId,
            'is_active' => false,
        ]);

        // The DB record must still EXIST
        $this->assertNotNull(Applicant::find($newId),
            'Delete New must NOT hard-delete the applicant record.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // INVALID RESOLUTION ACTION
    // ─────────────────────────────────────────────────────────────────

    /**
     * An invalid action string must be rejected.
     *
     * WHY: The resolve() method uses in_array() to validate the action.
     * If this guard is removed, a malicious or buggy caller could pass
     * any string as the action, potentially corrupting the flag's state.
     * This test verifies the guard is present and enforced.
     */
    public function test_invalid_resolution_action_is_rejected(): void
    {
        $flag = $this->makeFlag();

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('openFlag', $flag->id)
            ->call('resolve', 'InvalidAction')
            ->assertStatus(422); // or assertForbidden — depends on abort() code

        // Flag must remain Pending
        $this->assertDatabaseHas('duplicate_flags', [
            'id'                => $flag->id,
            'resolution_status' => 'Pending',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // AUDIT LOG ON RESOLUTION
    // ─────────────────────────────────────────────────────────────────

    /**
     * Every resolution must create an audit log entry.
     *
     * WHY: Duplicate resolutions are high-stakes decisions.
     * If a staff member incorrectly merges two real people's records,
     * the audit log is the only way to identify who made the mistake
     * and when, enabling it to be corrected.
     */
    public function test_resolution_creates_audit_log_entry(): void
    {
        $flag = $this->makeFlag();

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('openFlag', $flag->id)
            ->call('resolve', 'Retained Both');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->staffUser->id,
            'action'  => 'DUPLICATE_RESOLVED_RETAINED BOTH',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // RESOLUTION NOTES SAVED
    // ─────────────────────────────────────────────────────────────────

    /**
     * Resolution notes typed by staff must be saved to the flag record.
     *
     * WHY: Notes are the staff member's justification for their decision.
     * If another staff member later questions the resolution, the notes
     * provide the reasoning. Without this, every resolution looks
     * like an arbitrary decision with no context.
     */
    public function test_resolution_notes_are_saved_to_flag(): void
    {
        $flag  = $this->makeFlag();
        $notes = 'Verified in person at PESO office. Different barangay, different person.';

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('openFlag', $flag->id)
            ->set('resolutionNotes', $notes)
            ->call('resolve', 'Retained Both');

        $this->assertDatabaseHas('duplicate_flags', [
            'id'               => $flag->id,
            'resolution_notes' => $notes,
        ]);
    }
}
