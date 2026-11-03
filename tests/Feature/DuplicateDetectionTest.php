<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\DuplicateFlag;
use App\Models\Municipality;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * The duplicate scan decides which residents get flagged for staff review.
 * Bugs here cause two problems:
 *
 * - False negatives: a real duplicate is never flagged, so the same person
 *   can hold two records and receive two referrals.
 * - False positives or repeats: different people are flagged, or the same
 *   pair keeps reappearing, which wastes staff time and erodes trust.
 *
 * The rules under test are the ones from the design:
 * - A pair is flagged when 2 of 3 criteria match (name, birthdate, contact).
 * - The scan never changes a Verified record's status.
 * - Inactive applicants are ignored.
 * - A pair that already has a flag (any status) is never flagged again.
 */
class DuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->municipality = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay     = Barangay::factory()->create([
            'municipality_id' => $this->municipality->id,
            'name'            => 'San Jose',
        ]);
    }

    private function service(): DuplicateDetectionService
    {
        return app(DuplicateDetectionService::class);
    }

    /**
     * Every field the scan compares is set explicitly, and the defaults are
     * unique, so an unrelated applicant never matches by accident.
     */
    private function makeApplicant(array $overrides = []): Applicant
    {
        return Applicant::factory()->create(array_merge([
            'barangay_id'    => $this->barangay->id,
            'first_name'     => 'Juan',
            'last_name'      => fake()->unique()->lastName(),
            'birthdate'      => fake()->unique()->date('Y-m-d', '2000-01-01'),
            'sex'            => 'Male',
            'civil_status'   => 'Single',
            'contact_number' => '0917' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'is_active'      => true,
            'status'         => 'Pending',
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────
    // MATCHING RULES
    // ─────────────────────────────────────────────────────────────────

    /**
     * Name, birthdate and contact all match (3/3). One flag is created,
     * the newer record (higher ID) is the "new" one, and it becomes Flagged.
     *
     * WHY: This is the core behavior. Formatting differences in the
     * contact number must not hide a real duplicate.
     */
    public function test_three_matching_criteria_create_one_flag_on_newer_record(): void
    {
        $older = $this->makeApplicant([
            'last_name'      => 'Dela Cruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '09171234567',
        ]);
        $newer = $this->makeApplicant([
            'last_name'      => 'Delacruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '+63 917-123-4567',
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(1, $result['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 1);
        $this->assertDatabaseHas('duplicate_flags', [
            'applicant_id_new'      => $newer->id,
            'applicant_id_existing' => $older->id,
            'match_score'           => 3,
            'resolution_status'     => 'Pending',
        ]);
        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'status' => 'Flagged']);
        $this->assertDatabaseHas('applicants', ['id' => $older->id, 'status' => 'Pending']);
    }

    /**
     * Birthdate and contact match, but the names are unrelated (2/3).
     * This must still be flagged.
     *
     * WHY: Two criteria are enough. Name alone is never required.
     */
    public function test_two_criteria_without_name_match_still_creates_flag(): void
    {
        $a = $this->makeApplicant([
            'last_name'      => 'Garcia',
            'birthdate'      => '1985-02-10',
            'contact_number' => '09181112222',
        ]);
        $b = $this->makeApplicant([
            'last_name'      => 'Villanueva',
            'birthdate'      => '1985-02-10',
            'contact_number' => '09181112222',
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(1, $result['flagged']);
        $this->assertDatabaseHas('duplicate_flags', [
            'match_score'           => 2,
            'matched_phonetic'      => false,
            'matched_birthdate'     => true,
            'matched_contact'       => true,
        ]);
    }

    /**
     * Only the name matches (1/3). No flag.
     *
     * WHY: A common surname alone must never flag two people.
     */
    public function test_name_only_match_does_not_create_flag(): void
    {
        $this->makeApplicant([
            'last_name'      => 'Santos',
            'birthdate'      => '1988-03-02',
            'contact_number' => '09981112222',
        ]);
        $this->makeApplicant([
            'last_name'      => 'Santos',
            'birthdate'      => '1992-07-01',
            'contact_number' => '09175550000',
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(0, $result['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 0);
    }

    /**
     * Blank contact numbers must never count as a match, even when they
     * are equal. Here two people share only a similar name and a blank contact.
     *
     * WHY: Without this guard, two empty values would look identical and
     * add a false point.
     */
    public function test_blank_contact_numbers_never_match(): void
    {
        $this->makeApplicant([
            'last_name'      => 'Reyes',
            'birthdate'      => '1975-01-01',
            'contact_number' => '',
        ]);
        $this->makeApplicant([
            'last_name'      => 'Reyes',
            'birthdate'      => '1980-01-01',
            'contact_number' => '',
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(0, $result['flagged']);
    }

    /**
     * A single active applicant has nothing to compare with.
     *
     * WHY: The scan must never flag an applicant against itself.
     */
    public function test_single_applicant_is_never_flagged_against_itself(): void
    {
        $this->makeApplicant([
            'last_name'      => 'Solo',
            'birthdate'      => '1990-01-01',
            'contact_number' => '09170000001',
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(1, $result['scanned']);
        $this->assertSame(0, $result['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 0);
    }

    // ─────────────────────────────────────────────────────────────────
    // INACTIVE APPLICANTS
    // ─────────────────────────────────────────────────────────────────

    /**
     * An inactive applicant that matches on all criteria is ignored,
     * both as a subject and as a candidate.
     *
     * WHY: Deactivated records (for example, after "Delete New Record")
     * must not come back as duplicates.
     */
    public function test_inactive_applicants_are_ignored(): void
    {
        $this->makeApplicant([
            'last_name'      => 'Mendoza',
            'birthdate'      => '1990-05-14',
            'contact_number' => '09171234567',
        ]);
        $this->makeApplicant([
            'last_name'      => 'Mendoza',
            'birthdate'      => '1990-05-14',
            'contact_number' => '09171234567',
            'is_active'      => false,
            'status'         => 'Duplicated',
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(1, $result['scanned']);
        $this->assertSame(0, $result['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 0);
    }

    // ─────────────────────────────────────────────────────────────────
    // SKIPPING EXISTING PAIRS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Running the scan twice must not create a second flag for the same pair.
     *
     * WHY: Without this, every click on the button would add duplicate
     * entries to the queue.
     */
    public function test_running_scan_twice_does_not_create_repeat_flags(): void
    {
        $this->makeApplicant([
            'last_name'      => 'Dela Cruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '09171234567',
        ]);
        $this->makeApplicant([
            'last_name'      => 'Delacruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '+63 917-123-4567',
        ]);

        $this->service()->scanPending();
        $second = $this->service()->scanPending();

        $this->assertSame(0, $second['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    /**
     * A pair already resolved as "Retained Both" is never flagged again.
     *
     * WHY: Staff already decided these are two different people. Flagging
     * them again would undo that decision and waste their time.
     */
    public function test_resolved_pair_is_not_flagged_again(): void
    {
        $a = $this->makeApplicant([
            'last_name'      => 'Dela Cruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '09171234567',
        ]);
        $b = $this->makeApplicant([
            'last_name'      => 'Delacruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '+63 917-123-4567',
        ]);

        DuplicateFlag::factory()->resolved('Retained Both')->create([
            'applicant_id_new'      => $b->id,
            'applicant_id_existing' => $a->id,
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(0, $result['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    /**
     * An existing flag blocks the pair even when the IDs are stored in the
     * opposite order.
     *
     * WHY: The pair is unordered. A check in one direction only would let
     * the same match slip through as a new flag.
     */
    public function test_existing_flag_blocks_pair_in_either_direction(): void
    {
        $older = $this->makeApplicant([
            'last_name'      => 'Dela Cruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '09171234567',
        ]);
        $newer = $this->makeApplicant([
            'last_name'      => 'Delacruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '+63 917-123-4567',
        ]);

        // Stored with the older record as "new" (reverse of what the scan would do)
        DuplicateFlag::factory()->create([
            'applicant_id_new'      => $older->id,
            'applicant_id_existing' => $newer->id,
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(0, $result['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    // ─────────────────────────────────────────────────────────────────
    // VERIFIED RECORDS (the gap the scan was extended to close)
    // ─────────────────────────────────────────────────────────────────

    /**
     * A verified applicant edits their birthdate and contact to match an
     * existing verified applicant. The next scan must flag the pair, and
     * the verified status must stay Verified.
     *
     * WHY: This is the case the scan was extended to catch. It also checks
     * that verified records are not downgraded to Flagged.
     */
    public function test_verified_applicant_edit_is_caught_and_status_is_kept(): void
    {
        $existing = $this->makeApplicant([
            'last_name'      => 'Santos',
            'birthdate'      => '1988-03-02',
            'contact_number' => '09981112222',
            'status'         => 'Verified',
        ]);
        $editing = $this->makeApplicant([
            'last_name'      => 'Santos',
            'birthdate'      => '1992-07-01',
            'contact_number' => '09175550000',
            'status'         => 'Verified',
        ]);

        // Before the edit: name only, no flag
        $this->assertSame(0, $this->service()->scanPending()['flagged']);

        // The applicant edits their details
        $editing->update([
            'birthdate'      => '1988-03-02',
            'contact_number' => '09981112222',
        ]);

        $result = $this->service()->scanPending();

        $this->assertSame(1, $result['flagged']);
        $this->assertDatabaseHas('duplicate_flags', [
            'applicant_id_new'      => $editing->id,
            'applicant_id_existing' => $existing->id,
            'match_score'           => 3,
        ]);
        $this->assertDatabaseHas('applicants', ['id' => $editing->id, 'status' => 'Verified']);
    }

    // ─────────────────────────────────────────────────────────────────
    // RETURN VALUE
    // ─────────────────────────────────────────────────────────────────

    /**
     * The scanned count includes every active applicant, not only Pending ones.
     *
     * WHY: The success message shown to staff uses this number. It must
     * match what was actually checked.
     */
    public function test_scanned_count_includes_all_active_applicants(): void
    {
        $this->makeApplicant(['status' => 'Pending']);
        $this->makeApplicant(['status' => 'Verified']);
        $this->makeApplicant(['status' => 'Flagged']);
        $this->makeApplicant(['status' => 'Pending', 'is_active' => false]);

        $result = $this->service()->scanPending();

        $this->assertSame(3, $result['scanned']);
    }
}   