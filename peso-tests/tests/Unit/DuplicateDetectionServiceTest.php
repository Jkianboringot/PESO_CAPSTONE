<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * The DuplicateDetectionService contains the most critical
 * business logic in the entire system. It is a scoring algorithm.
 * Scoring algorithms have BOUNDARY CONDITIONS — the most dangerous
 * type of bug because they are silent: code runs, no error is thrown,
 * but the answer is wrong by exactly 1.
 *
 * WHAT WOULD HAPPEN IF THIS BROKE:
 * - Score threshold bug (1 instead of 2) → every new registrant gets flagged
 *   as a duplicate. Staff are overwhelmed with false flags. Real duplicates
 *   get buried. PESO loses trust in the system.
 * - Score threshold bug (3 instead of 2) → real duplicates slip through.
 *   PESO submits dirty data to DOLE. Two records for the same person
 *   count as two people, inflating workforce statistics.
 *
 * TESTING MINDSET FOR THIS FILE:
 * I am not testing "does the detect() method exist."
 * I am testing: "Is the algorithm mathematically correct at every boundary?"
 * Score 0: no flag. Score 1: no flag. Score 2: flag. Score 3: flag.
 * Each boundary gets its own test with EXACTLY the data that triggers it.
 */
class DuplicateDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    // The service under test — instantiated fresh for each test
    private DuplicateDetectionService $service;

    // Helper: creates a minimal applicant in the DB so detect() has
    // something to compare against. Returns the created model.
    private function makeApplicant(array $overrides = []): Applicant
    {
        // We need a real barangay FK to satisfy the DB constraint
        $municipality = Municipality::factory()->create();
        $barangay     = Barangay::factory()
            ->for($municipality)
            ->create();

        return Applicant::factory()->create(array_merge([
            'barangay_id'         => $barangay->id,
            'is_active'           => true,
            'status'              => 'Pending',
            'last_name_metaphone' => metaphone('Santos'), // default
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DuplicateDetectionService();
    }

    // ─────────────────────────────────────────────────────────────────
    // GROUP 1: SCORE BOUNDARY TESTS
    // These are the most important tests in the file.
    // They verify that the scoring threshold (score >= 2) is exact.
    // ─────────────────────────────────────────────────────────────────

    /**
     * SCORE = 0: No criteria match.
     * Completely different person. No flag should be created.
     *
     * WHY: If score 0 triggered a flag, every registration
     * would be a false duplicate. The whole system collapses.
     */
    public function test_no_flag_when_score_is_zero(): void
    {
        // Create an existing applicant in the DB
        $existing = $this->makeApplicant([
            'last_name'           => 'Santos',
            'last_name_metaphone' => metaphone('Santos'),
            'birthdate'           => '1990-05-15',
            'contact_number'      => '09171234567',
        ]);

        // Create a completely different new applicant
        $new = $this->makeApplicant([
            'last_name'           => 'Reyes',       // different name sound
            'last_name_metaphone' => metaphone('Reyes'),
            'birthdate'           => '1995-08-20',  // different birthdate
            'contact_number'      => '09289999999', // different contact
        ]);

        $flags = $this->service->detect($new);

        // ASSERTION: Zero flags created
        $this->assertCount(0, $flags,
            'Score 0: completely different person should produce NO duplicate flags.'
        );

        // ALSO CHECK: New applicant status should remain unchanged
        $this->assertEquals('Pending', $new->fresh()->status,
            'Status should NOT change to Flagged when score is 0.'
        );
    }

    /**
     * SCORE = 1: Only ONE criterion matches (phonetic name only).
     * Common surnames like Santos are shared by many people.
     * A single criterion match must NOT trigger a flag.
     *
     * WHY: If score 1 triggered flags, every "Santos" who registers
     * after another "Santos" would be flagged. There are hundreds of
     * Santos families in Catanduanes.
     */
    public function test_no_flag_when_score_is_one_phonetic_only(): void
    {
        $existing = $this->makeApplicant([
            'last_name'           => 'Santos',
            'last_name_metaphone' => metaphone('Santos'),
            'birthdate'           => '1990-05-15',
            'contact_number'      => '09171234567',
        ]);

        // Same sound (Santos vs Santoz) but different birthdate and contact
        $new = $this->makeApplicant([
            'last_name'           => 'Santoz',       // phonetically identical to Santos
            'last_name_metaphone' => metaphone('Santoz'),
            'birthdate'           => '1999-12-01',   // different
            'contact_number'      => '09289999999',  // different
        ]);

        $flags = $this->service->detect($new);

        $this->assertCount(0, $flags,
            'Score 1 (phonetic match only) must NOT create a flag.'
        );
    }

    /**
     * SCORE = 1: Only ONE criterion matches (birthdate only).
     * Same birthday, different name, different contact.
     * Should NOT flag — coincidence birthdays happen.
     */
    public function test_no_flag_when_score_is_one_birthdate_only(): void
    {
        $existing = $this->makeApplicant([
            'last_name'           => 'Cruz',
            'last_name_metaphone' => metaphone('Cruz'),
            'birthdate'           => '1995-06-15',
            'contact_number'      => '09171234567',
        ]);

        $new = $this->makeApplicant([
            'last_name'           => 'Garcia',       // different sound
            'last_name_metaphone' => metaphone('Garcia'),
            'birthdate'           => '1995-06-15',   // SAME birthdate
            'contact_number'      => '09289999999',  // different
        ]);

        $flags = $this->service->detect($new);

        $this->assertCount(0, $flags,
            'Score 1 (birthdate match only) must NOT create a flag.'
        );
    }

    /**
     * SCORE = 2: Phonetic name + exact birthdate match.
     * This is the MINIMUM threshold for flagging.
     * MUST create exactly one flag.
     *
     * WHY THIS IS THE KEY TEST:
     * This is the scenario the algorithm was designed for.
     * Same-sounding name AND same birthday = almost certainly the same person.
     * If this test fails, the entire duplicate detection system is broken.
     */
    public function test_flag_created_when_score_is_two_phonetic_and_birthdate(): void
    {
        $existing = $this->makeApplicant([
            'last_name'           => 'Dela Cruz',
            'last_name_metaphone' => metaphone('Dela Cruz'),
            'birthdate'           => '1988-03-22',
            'contact_number'      => '09171234567',
        ]);

        // Re-registration: same name sound, same birthday, different contact
        $new = $this->makeApplicant([
            'last_name'           => 'De la Cruz',   // variant spelling, same phonetic
            'last_name_metaphone' => metaphone('De la Cruz'),
            'birthdate'           => '1988-03-22',   // SAME
            'contact_number'      => '09289999999',  // different
        ]);

        $flags = $this->service->detect($new);

        // ASSERTION 1: Exactly one flag was created
        $this->assertCount(1, $flags,
            'Score 2 (phonetic + birthdate) MUST create exactly 1 duplicate flag.'
        );

        // ASSERTION 2: The flag records which criteria matched
        $flag = $flags[0];
        $this->assertTrue($flag->matched_phonetic,
            'matched_phonetic should be TRUE when names sound the same.'
        );
        $this->assertTrue($flag->matched_birthdate,
            'matched_birthdate should be TRUE when birthdates are identical.'
        );
        $this->assertFalse($flag->matched_contact,
            'matched_contact should be FALSE when contact numbers differ.'
        );

        // ASSERTION 3: Score is correctly recorded as 2
        $this->assertEquals(2, $flag->match_score,
            'match_score should be exactly 2 for phonetic + birthdate.'
        );

        // ASSERTION 4: The correct applicants are linked
        $this->assertEquals($new->id, $flag->applicant_id_new,
            'The NEW applicant should be linked as applicant_id_new.'
        );
        $this->assertEquals($existing->id, $flag->applicant_id_existing,
            'The EXISTING applicant should be linked as applicant_id_existing.'
        );

        // ASSERTION 5: New applicant's status changed to Flagged
        $this->assertEquals('Flagged', $new->fresh()->status,
            "New applicant status must change to 'Flagged' when a duplicate is detected."
        );
    }

    /**
     * SCORE = 2: Phonetic name + contact number match.
     * Same name sound + same phone = very strong signal.
     */
    public function test_flag_created_when_score_is_two_phonetic_and_contact(): void
    {
        $existing = $this->makeApplicant([
            'last_name'           => 'Reyes',
            'last_name_metaphone' => metaphone('Reyes'),
            'birthdate'           => '1992-07-10',
            'contact_number'      => '09171234567',
        ]);

        $new = $this->makeApplicant([
            'last_name'           => 'Reyes',
            'last_name_metaphone' => metaphone('Reyes'),
            'birthdate'           => '1995-11-25',   // different birthdate
            'contact_number'      => '09171234567',  // SAME contact
        ]);

        $flags = $this->service->detect($new);

        $this->assertCount(1, $flags,
            'Score 2 (phonetic + contact) MUST create a flag.'
        );
        $this->assertTrue($flags[0]->matched_phonetic);
        $this->assertFalse($flags[0]->matched_birthdate);
        $this->assertTrue($flags[0]->matched_contact);
        $this->assertEquals(2, $flags[0]->match_score);
    }

    /**
     * SCORE = 3: All three criteria match.
     * This is a near-certain duplicate.
     * Must create a flag with score = 3.
     */
    public function test_flag_created_when_score_is_three_all_criteria(): void
    {
        $existing = $this->makeApplicant([
            'last_name'           => 'Villanueva',
            'last_name_metaphone' => metaphone('Villanueva'),
            'birthdate'           => '1990-01-15',
            'contact_number'      => '09171234567',
        ]);

        $new = $this->makeApplicant([
            'last_name'           => 'Villanueva',
            'last_name_metaphone' => metaphone('Villanueva'),
            'birthdate'           => '1990-01-15',
            'contact_number'      => '09171234567',
        ]);

        $flags = $this->service->detect($new);

        $this->assertCount(1, $flags);
        $this->assertEquals(3, $flags[0]->match_score,
            'All three criteria matched: score must be exactly 3.'
        );
        $this->assertTrue($flags[0]->matched_phonetic);
        $this->assertTrue($flags[0]->matched_birthdate);
        $this->assertTrue($flags[0]->matched_contact);
    }

    // ─────────────────────────────────────────────────────────────────
    // GROUP 2: PHONETIC ALGORITHM EDGE CASES
    // The phonetic normalization handles Filipino name quirks.
    // These tests verify those specific cases.
    // ─────────────────────────────────────────────────────────────────

    /**
     * "Ma. Santos" and "Maria Santos" must be treated as the same name.
     *
     * WHY: Filipino names frequently use "Ma." as shorthand for Maria.
     * If the system doesn't expand this, a person who registers as
     * "Ma. Santos" and re-registers as "Maria Santos" won't be detected.
     */
    public function test_ma_abbreviation_expanded_for_phonetic_match(): void
    {
        $existing = $this->makeApplicant([
            'last_name'           => 'Ma. Santos',
            'last_name_metaphone' => metaphone('Ma. Santos'),
            'birthdate'           => '1985-04-12',
            'contact_number'      => '09171234567',
        ]);

        $new = $this->makeApplicant([
            'last_name'           => 'Maria Santos',
            'last_name_metaphone' => metaphone('Maria Santos'),
            'birthdate'           => '1985-04-12',  // same
            'contact_number'      => '09289999999',
        ]);

        $flags = $this->service->detect($new);

        // The phonetic expansion should cause name match
        // Combined with birthdate match → score >= 2 → flag
        $this->assertGreaterThanOrEqual(1, count($flags),
            '"Ma." should expand to "Maria" for phonetic comparison.'
        );
    }

    /**
     * Contact number matching must be prefix-agnostic.
     * +639171234567, 09171234567, and 9171234567 are all the same number.
     *
     * WHY: Residents may enter their number differently each time.
     * A contact match that fails because of prefix format
     * is a false negative — a real duplicate slips through.
     */
    public function test_contact_match_ignores_prefix_format(): void
    {
        $existing = $this->makeApplicant([
            'last_name'           => 'Ramos',
            'last_name_metaphone' => metaphone('Ramos'),
            'birthdate'           => '1993-08-30',
            'contact_number'      => '+639171234567', // international format
        ]);

        $new = $this->makeApplicant([
            'last_name'           => 'Ramos',
            'last_name_metaphone' => metaphone('Ramos'),
            'birthdate'           => '2000-01-01',     // different birthdate
            'contact_number'      => '09171234567',    // local format — same number
        ]);

        $flags = $this->service->detect($new);

        // Should still detect contact match because last 7 digits match
        $this->assertCount(1, $flags,
            'Contact match should work regardless of +63 vs 0 prefix format.'
        );
        $this->assertTrue($flags[0]->matched_contact);
    }

    // ─────────────────────────────────────────────────────────────────
    // GROUP 3: INACTIVE APPLICANT EXCLUSION
    // Detect() must NOT scan deactivated applicants.
    // ─────────────────────────────────────────────────────────────────

    /**
     * Deactivated applicants must NOT be considered as duplicate targets.
     *
     * WHY: If a Merged duplicate is deactivated (is_active=false),
     * and then the same person registers AGAIN, we don't want the
     * deactivated record to trigger a new flag — it's already been
     * resolved. We want the new registration to go through cleanly.
     */
    public function test_does_not_flag_against_inactive_applicants(): void
    {
        // An existing applicant who was DEACTIVATED (e.g., merged previously)
        $inactive = $this->makeApplicant([
            'last_name'           => 'Torres',
            'last_name_metaphone' => metaphone('Torres'),
            'birthdate'           => '1991-06-20',
            'contact_number'      => '09171234567',
            'is_active'           => false, // DEACTIVATED
        ]);

        // New registration that would score 3 against the inactive record
        $new = $this->makeApplicant([
            'last_name'           => 'Torres',
            'last_name_metaphone' => metaphone('Torres'),
            'birthdate'           => '1991-06-20',
            'contact_number'      => '09171234567',
            'is_active'           => true,
        ]);

        $flags = $this->service->detect($new);

        $this->assertCount(0, $flags,
            'Inactive applicants must be excluded from duplicate scanning.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // GROUP 4: SELF-COMPARISON GUARD
    // An applicant must never be compared against themselves.
    // ─────────────────────────────────────────────────────────────────

    /**
     * An applicant's own record must not trigger a self-flag.
     *
     * WHY: When detect() runs, the new applicant IS already in the DB.
     * Without an exclusion clause (WHERE id != $new->id), the algorithm
     * would compare the new applicant against themselves and always
     * score 3 — flagging every single registration. This would be
     * catastrophic and very easy to miss in manual testing.
     */
    public function test_applicant_not_compared_against_themselves(): void
    {
        $applicant = $this->makeApplicant([
            'last_name'           => 'Gonzales',
            'last_name_metaphone' => metaphone('Gonzales'),
            'birthdate'           => '1987-09-14',
            'contact_number'      => '09171234567',
            'is_active'           => true,
        ]);

        // Run detect on the applicant against themselves (no other applicants exist)
        $flags = $this->service->detect($applicant);

        $this->assertCount(0, $flags,
            'An applicant must never be flagged as a duplicate of themselves.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // GROUP 5: MULTIPLE DUPLICATES
    // What happens when one new registration matches multiple existing ones?
    // ─────────────────────────────────────────────────────────────────

    /**
     * If one new applicant matches two different existing applicants,
     * two flags must be created.
     *
     * WHY: The queue must show ALL potential matches so staff can
     * compare all candidates, not just the first one found.
     */
    public function test_multiple_flags_created_for_multiple_matches(): void
    {
        // Two existing applicants with the same data (already a messy DB)
        $existing1 = $this->makeApplicant([
            'last_name'           => 'Fernandez',
            'last_name_metaphone' => metaphone('Fernandez'),
            'birthdate'           => '1994-02-28',
            'contact_number'      => '09171111111',
        ]);

        $existing2 = $this->makeApplicant([
            'last_name'           => 'Fernandez',
            'last_name_metaphone' => metaphone('Fernandez'),
            'birthdate'           => '1994-02-28',
            'contact_number'      => '09172222222',
        ]);

        // New applicant matches both on phonetic + birthdate (score 2 each)
        $new = $this->makeApplicant([
            'last_name'           => 'Fernandez',
            'last_name_metaphone' => metaphone('Fernandez'),
            'birthdate'           => '1994-02-28',
            'contact_number'      => '09289999999',
        ]);

        $flags = $this->service->detect($new);

        $this->assertCount(2, $flags,
            'One new applicant matching two existing records should create two flags.'
        );
    }
}
