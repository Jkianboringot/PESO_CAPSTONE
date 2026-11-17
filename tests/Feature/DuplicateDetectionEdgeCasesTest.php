<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\DuplicateFlag;
use App\Models\Municipality;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * The main behavior is covered in DuplicateDetectionTest. This file covers
 * only the edge cases, where the matching logic is most likely to break:
 *
 * - Name normalization: accents, letter case, suffixes, and names with no
 *   Latin letters.
 * - Typo tolerance boundaries: short names must match exactly.
 * - Contact normalization: formats that must match, and values that must not.
 * - Birthdate: a one-day difference must not count as a match.
 * - Scoring: a flag needs 2 of 3 criteria, and one pair gets one flag.
 * - Repeat scans and prior decisions: new pairs only, and any resolved status
 *   blocks the pair.
 *
 * Name cases that expect "no match" were chosen so their phonetic (Metaphone)
 * codes differ. Otherwise the test would pass or fail for the wrong reason.
 */
class DuplicateDetectionEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        $municipality   = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay = Barangay::factory()->create([
            'municipality_id' => $municipality->id,
            'name'            => 'San Jose',
        ]);
    }

    private function service(): DuplicateDetectionService
    {
        return app(DuplicateDetectionService::class);
    }

    /**
     * Defaults are unique, so a background applicant never matches by accident.
     */
    private function makeApplicant(array $overrides = []): Applicant
{
    return Applicant::factory()->create(array_merge([
        'barangay_id'    => $this->barangay->id,
        'first_name'     => 'Juan',
        'last_name'      => $overrides['last_name'] ?? fake()->unique()->lastName(),
        'birthdate'      => $overrides['birthdate'] ?? fake()->unique()->date('Y-m-d', '2000-01-01'),
        'contact_number' => '0917' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
        'sex'            => 'Male',
        'civil_status'   => 'Single',
        'is_active'      => true,
        'status'         => 'Pending',
    ], $overrides));
}

    // ─────────────────────────────────────────────────────────────────
    // NAME NORMALIZATION
    // ─────────────────────────────────────────────────────────────────

    /**
     * Accents and letter case are ignored: "Peña" matches "PENA".
     *
     * WHY: Staff type names with and without accents and in any case.
     * A missed match here leaves a real duplicate unflagged.
     */
    public function test_accents_and_letter_case_are_ignored_in_last_name(): void
    {
        $this->makeApplicant(['last_name' => 'Peña', 'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'PENA', 'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', [
            'match_score'       => 2,
            'matched_phonetic'  => true,
            'matched_birthdate' => true,
            'matched_contact'   => false,
        ]);
    }

    /**
     * A suffix is dropped before comparing: "Santos Jr." matches "Santos".
     *
     * WHY: Family members with the same name often differ only by Jr. or Sr.
     * The suffix should not hide the match.
     */
    public function test_name_suffix_is_ignored_when_comparing(): void
    {
        $this->makeApplicant(['last_name' => 'Santos Jr.', 'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'Santos', 'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', ['matched_phonetic' => true, 'match_score' => 2]);
    }

    /**
     * A short name with one letter different ("Cruz" vs "Crux") is not a name match.
     *
     * WHY: Names of four letters or fewer allow no typos. Allowing one would
     * flag different people with similar short surnames.
     */
    public function test_one_letter_difference_in_a_short_name_is_not_a_match(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'Crux', 'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 0);
    }

    /**
     * Names with no Latin letters never match on name, but can still match
     * on birthdate and contact.
     *
     * WHY: Blank or symbol-only names must not count as a name match, because
     * two empty names would look identical. Matching on the other criteria
     * should still work.
     */
    public function test_names_without_latin_letters_never_count_as_a_name_match(): void
    {
        $this->makeApplicant(['last_name' => '---', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => '***', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', [
            'match_score'      => 2,
            'matched_phonetic' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // CONTACT NORMALIZATION
    // ─────────────────────────────────────────────────────────────────

    public static function contactFormats(): array
    {
        return [
            'leading zero, local'       => ['09171234567'],
            'plus 63 with spaces'       => ['+63 917 123 4567'],
            'dashes, no leading zero'   => ['917-123-4567'],
            '63 without plus'           => ['63 917 123 4567'],
        ];
    }

    /**
     * Every common format of the same number is a contact match.
     *
     * WHY: People write numbers in many ways. If the formats do not match,
     * the same person is not flagged.
     */
    #[DataProvider('contactFormats')]
    public function test_contact_formats_of_the_same_number_match(string $newerFormat): void
    {
        $this->makeApplicant(['last_name' => 'Garcia', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Villanueva', 'birthdate' => '1990-05-14', 'contact_number' => $newerFormat]);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', [
            'matched_contact'   => true,
            'matched_birthdate' => true,
            'match_score'       => 2,
        ]);
    }

    /**
     * A number that differs only in its last digit is not a contact match.
     *
     * WHY: Adjacent numbers are common. A near miss must not count as a match.
     */
    public function test_contact_differing_by_one_digit_is_not_a_match(): void
    {
        $this->makeApplicant(['last_name' => 'Garcia', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Villanueva', 'birthdate' => '1990-05-14', 'contact_number' => '09171234568']);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
    }

    /**
     * Numbers that are only a country code ("+63" or "63") normalize to nothing,
     * so they never match each other.
     *
     * WHY: Without this guard, two incomplete numbers would look equal.
     */
    public function test_numbers_that_normalize_to_nothing_never_match(): void
    {
        $this->makeApplicant(['last_name' => 'Garcia', 'birthdate' => '1990-05-14', 'contact_number' => '+63']);
        $this->makeApplicant(['last_name' => 'Villanueva', 'birthdate' => '1990-05-14', 'contact_number' => '63']);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
    }

    /**
     * A matching contact alone, with different birthdates and names, is not enough.
     *
     * WHY: Shared phones happen (family members, a shared household number).
     * One criterion is never enough.
     */
    public function test_contact_match_alone_never_creates_a_flag(): void
    {
        $this->makeApplicant(['last_name' => 'Garcia', 'birthdate' => '1990-01-01', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Villanueva', 'birthdate' => '1992-02-02', 'contact_number' => '09171234567']);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
    }

    // ─────────────────────────────────────────────────────────────────
    // BIRTHDATE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Birthdates one day apart are not a match.
     *
     * WHY: Date entry errors are common, but a one-day difference is still a
     * different recorded date. The rule is an exact day match.
     */
    public function test_birthdate_one_day_apart_is_not_a_match(): void
    {
        $this->makeApplicant(['last_name' => 'Garcia', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Villanueva', 'birthdate' => '1990-05-15', 'contact_number' => '09171234567']);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
    }

    // ─────────────────────────────────────────────────────────────────
    // SCORING AND ONE FLAG PER PAIR
    // ─────────────────────────────────────────────────────────────────

    /**
     * Three records that all match create three flags, one per pair, not six.
     *
     * WHY: A record can be a candidate through both the birthdate group and
     * the contact group. Each pair must still be flagged only once.
     */
    public function test_three_identical_records_create_one_flag_per_pair(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);

        $result = $this->service()->scanPending();

        $this->assertSame(3, $result['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 3);
    }

    /**
     * A later scan flags only the new pairs, and does not repeat earlier ones.
     *
     * WHY: Staff add records over time. Each scan should report only what is new.
     */
    public function test_later_scan_flags_only_pairs_involving_new_records(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->assertSame(1, $this->service()->scanPending()['flagged']);

        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $second = $this->service()->scanPending();

        $this->assertSame(2, $second['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 3);
    }

    /**
     * A verified record can be the newer side of a flag. The flag is created,
     * and the record keeps its Verified status.
     *
     * WHY: Verified records must still be reviewable, but a scan must never
     * downgrade them.
     */
    public function test_verified_newer_record_is_flagged_but_keeps_its_status(): void
    {
        $older = $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $newer = $this->makeApplicant([
            'last_name'      => 'Cruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '09171234567',
            'status'         => 'Verified',
        ]);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'status' => 'Verified']);
        $this->assertDatabaseHas('applicants', ['id' => $older->id, 'status' => 'Pending']);
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIOR DECISIONS BLOCK THE PAIR
    // ─────────────────────────────────────────────────────────────────

    public static function resolvedStatuses(): array
    {
        return [
            'Retained Both' => ['Retained Both'],
            'Deleted'       => ['Deleted'],
            'Merged'        => ['Merged'],
        ];
    }

    /**
     * A resolved flag blocks the pair whatever its outcome.
     *
     * WHY: Any past decision (kept, deleted, or merged) is final. A pair must
     * not reappear just because the outcome was not "Retained Both".
     */
    #[DataProvider('resolvedStatuses')]
    public function test_resolved_flag_blocks_pair_for_any_outcome(string $status): void
    {
        $a = $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $b = $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);

        DuplicateFlag::factory()->resolved($status)->create([
            'applicant_id_new'      => $b->id,
            'applicant_id_existing' => $a->id,
        ]);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    // ─────────────────────────────────────────────────────────────────
    // EMPTY DATA
    // ─────────────────────────────────────────────────────────────────

    /**
     * Scanning an empty database returns zeros and does not fail.
     *
     * WHY: A new install or a fully cleaned table must not produce an error.
     */
    public function test_scan_on_empty_database_returns_zero_counts(): void
    {
        $result = $this->service()->scanPending();

        $this->assertSame(['scanned' => 0, 'flagged' => 0], $result);
        $this->assertDatabaseCount('duplicate_flags', 0);
    }
        // ─────────────────────────────────────────────────────────────────
    // MORE NAME EDGE CASES
    // ─────────────────────────────────────────────────────────────────

    /** A five-letter name allows one letter of typo, so Reyes and Reyas match. */
    public function test_one_letter_typo_in_a_five_letter_name_is_a_match(): void
    {
        $this->makeApplicant(['last_name' => 'Reyes', 'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'Reyas', 'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', ['matched_phonetic' => true, 'match_score' => 2]);
    }

    /** Spaces are kept during normalization, but a two-space gap in a long name is within typo tolerance. */
    public function test_multi_word_surname_with_different_spacing_is_a_match(): void
    {
        $this->makeApplicant(['last_name' => 'De la Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'Delacruz',   'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', ['matched_phonetic' => true]);
    }

    /** Apostrophes are removed, so O'Brien and Obrien normalize to the same value. */
    public function test_apostrophe_is_ignored_in_last_name(): void
    {
        $this->makeApplicant(['last_name' => "O'Brien", 'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'Obrien',  'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', ['matched_phonetic' => true]);
    }

    /** Digits typed into a name are removed, so "Cruz2" matches "Cruz". */
    public function test_digits_in_last_name_are_ignored(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz2', 'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'Cruz',  'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', ['matched_phonetic' => true]);
    }

    /** Roman-numeral suffixes are dropped the same way as Jr. and Sr. */
    public function test_roman_numeral_suffix_iii_is_ignored(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz III', 'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'Cruz',     'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', ['matched_phonetic' => true]);
    }

    /**
     * A last name that is only a suffix ("Jr.") normalizes to nothing, so it
     * never matches on name. Birthdate and contact can still flag the pair.
     */
    public function test_suffix_only_last_name_never_matches_on_name(): void
    {
        $this->makeApplicant(['last_name' => 'Jr.', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Jr.', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', [
            'matched_phonetic'  => false,
            'matched_birthdate' => true,
            'matched_contact'   => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // MORE CONTACT EDGE CASES
    // ─────────────────────────────────────────────────────────────────

    /** Brackets, dashes and spaces are stripped, so "(0917) 123-4567" matches "09171234567". */
    public function test_contact_with_brackets_and_dashes_matches_plain_number(): void
    {
        $this->makeApplicant(['last_name' => 'Garcia',     'birthdate' => '1990-05-14', 'contact_number' => '(0917) 123-4567']);
        $this->makeApplicant(['last_name' => 'Villanueva', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', ['matched_contact' => true, 'matched_birthdate' => true]);
    }

    /**
     * KNOWN GAP (DUP-2): a contact with only a few digits is compared as-is.
     * "123" and "0123" both normalize to "123", so two unrelated records
     * with the same birthdate are flagged. Registration rejects short numbers,
     * so this comes from older or imported data.
     *
     * HOW TO REPRODUCE: two records, different names, same birthdate, contacts
     * "123" and "0123". BUG: one flag is created. Expected: no flag.
     *
     * FIX: in nationalNumber(), return '' when fewer than 10 digits remain.
     */
    public function test_short_contact_numbers_are_not_treated_as_a_match(): void
    {
        $this->makeApplicant(['last_name' => 'Garcia',     'birthdate' => '1990-05-14', 'contact_number' => '123']);
        $this->makeApplicant(['last_name' => 'Villanueva', 'birthdate' => '1990-05-14', 'contact_number' => '0123']);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
    }

    // ─────────────────────────────────────────────────────────────────
    // MORE BIRTHDATE AND SCORING EDGE CASES
    // ─────────────────────────────────────────────────────────────────

    /** A matching birthdate alone (one point) never creates a flag. */
    public function test_birthdate_match_alone_never_creates_a_flag(): void
    {
        $this->makeApplicant(['last_name' => 'Garcia',     'birthdate' => '1990-05-14', 'contact_number' => '09170000001']);
        $this->makeApplicant(['last_name' => 'Villanueva', 'birthdate' => '1990-05-14', 'contact_number' => '09170000002']);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 0);
    }

    /** Name and contact match, birthdates differ by one day: two points, so a flag. Birthdate is recorded as not matched. */
    public function test_name_and_contact_match_flags_even_when_birthdate_is_one_day_off(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-15', 'contact_number' => '09171234567']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', [
            'match_score'       => 2,
            'matched_phonetic'  => true,
            'matched_birthdate' => false,
            'matched_contact'   => true,
        ]);
    }

    /** Name and contact match with different birthdates: two points, so a flag, with birthdate not matched. */
    public function test_name_and_contact_match_without_birthdate_match_is_flagged(): void
    {
        $this->makeApplicant(['last_name' => 'Garcia', 'birthdate' => '1990-01-01', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Garcia', 'birthdate' => '1992-02-02', 'contact_number' => '09171234567']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('duplicate_flags', [
            'matched_phonetic'  => true,
            'matched_birthdate' => false,
            'matched_contact'   => true,
        ]);
    }

    /**
     * A and B match on all three. C shares only the name with them (one point).
     * Only the A-B pair is flagged. C does not get a flag from a single point.
     */
    public function test_a_single_point_match_does_not_block_or_create_a_flag_for_another_record(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1991-01-01', 'contact_number' => '09179999999']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    // ─────────────────────────────────────────────────────────────────
    // MORE PRIOR-DECISION AND STATUS EDGE CASES
    // ─────────────────────────────────────────────────────────────────

    /** A flag still in Pending (not resolved) also blocks the pair from being flagged again. */
    public function test_existing_pending_flag_blocks_the_pair(): void
    {
        $a = $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $b = $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);

        DuplicateFlag::factory()->create([
            'applicant_id_new'      => $b->id,
            'applicant_id_existing' => $a->id,
            'resolution_status'     => 'Pending',
        ]);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    /**
     * After a flag exists, deactivating one side means that applicant is no
     * longer scanned. The next scan adds nothing, and the old flag stays.
     */
    public function test_deactivating_one_side_stops_new_flags_but_keeps_the_old_one(): void
    {
        $a = $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);

        $a->update(['is_active' => false]);

        $this->assertSame(0, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    /** An older Verified record is never changed by a scan, even when it is the existing side of a flag. */
    public function test_verified_older_record_keeps_its_status(): void
    {
        $older = $this->makeApplicant([
            'last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567', 'status' => 'Verified',
        ]);
        $newer = $this->makeApplicant([
            'last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567', 'status' => 'Pending',
        ]);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('applicants', ['id' => $older->id, 'status' => 'Verified']);
        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'status' => 'Flagged']);
    }

    /** A newer record that is already Flagged gets a flag, and its status stays Flagged. */
    public function test_newer_record_already_flagged_keeps_flagged_status(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567']);
        $newer = $this->makeApplicant([
            'last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567', 'status' => 'Flagged',
        ]);

        $this->assertSame(1, $this->service()->scanPending()['flagged']);
        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'status' => 'Flagged']);
    }

    /** When every applicant is inactive, nothing is scanned and nothing is flagged. */
    public function test_scan_with_only_inactive_applicants_scans_nothing(): void
    {
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567', 'is_active' => false]);
        $this->makeApplicant(['last_name' => 'Cruz', 'birthdate' => '1990-05-14', 'contact_number' => '09171234567', 'is_active' => false]);

        $this->assertSame(['scanned' => 0, 'flagged' => 0], $this->service()->scanPending());
    }

    // ─────────────────────────────────────────────────────────────────
    // LARGE DATA
    // ─────────────────────────────────────────────────────────────────

    /**
     * The scan reads applicants in batches of 500. Going past one batch
     * must still count every applicant. Records here do not match, so no flags.
     */
  public function test_scan_counts_every_applicant_across_the_batch_boundary(): void
{
    for ($i = 1; $i <= 501; $i++) {
        $this->makeApplicant([
            'last_name'      => 'Garcia',
            'birthdate'      => now()->subYears(20)->subDays($i)->toDateString(),
            'contact_number' => '09' . str_pad((string) $i, 9, '0', STR_PAD_LEFT),
        ]);
    }

    $result = $this->service()->scanPending();

    $this->assertSame(501, $result['scanned']);
    $this->assertSame(0, $result['flagged']);
}

}

// Manual test plan: edge cases
// Before each case

// Every case needs an empty applicant table. In tinker (php artisan tinker), run:

// php
// App\Models\DuplicateFlag::query()->delete();
// App\Models\Applicant::query()->delete();
// $b = App\Models\Barangay::where('name', 'San Jose')->firstOrFail();

// If the delete fails on a foreign key, run php artisan migrate:fresh, then recreate the San Jose barangay under a municipality.

// Then, for each case, log in as staff, open Duplicate Review, click Scan Active Applicants, and confirm the dialog. Record the toast text and the rows in the queue. Check the database with:

// php
// App\Models\DuplicateFlag::all(['applicant_id_new', 'applicant_id_existing', 'match_score', 'matched_phonetic', 'matched_birthdate', 'matched_contact', 'resolution_status']);
// EC-01: Accents and letter case are ignored
// Problem: “Peña” and “PENA” are treated as different names.
// Why this test is needed: staff type names with and without accents. A missed match leaves a real duplicate unflagged.
// Scenario: two applicants with the same birthdate and different contacts. Only the name matches.
// Steps:
// Run the cleanup from “Before each case.”
// In tinker:
// php
//      App\Models\Applicant::factory()->create(['barangay_id' => $b->id, 'last_name' => 'Peña', 'birthdate' => '1990-05-14', 'contact_number' => '09170000001', 'is_active' => true, 'status' => 'Pending']);
//      App\Models\Applicant::factory()->create(['barangay_id' => $b->id, 'last_name' => 'PENA', 'birthdate' => '1990-05-14', 'contact_number' => '09170000002', 'is_active' => true, 'status' => 'Pending']);
// Run the scan in the browser.
// Expected result: toast reports 1 new flag. The row shows 2/3 with Name and Birthdate badges and no Contact badge.
// Actual result: ______________________
// Status: Pass / Fail
// EC-02: Name suffix is ignored
// Problem: “Santos Jr.” and “Santos” are treated as different people.
// Why this test is needed: relatives often share a name and differ only by a suffix. The suffix should not hide the match.
// Scenario: same birthdate, different contacts, last names “Santos Jr.” and “Santos”.
// Steps:
// Run the cleanup.
// In tinker, create two applicants with last_name set to Santos Jr. and Santos, the same birthdate 1990-05-14, and contacts 09170000001 and 09170000002.
// Run the scan.
// Expected result: 1 new flag, 2/3, with a Name badge.
// Actual result: ______________________
// Status: Pass / Fail
// EC-03: One-letter difference in a short name is not a match
// Problem: “Cruz” and “Crux” are flagged as the same name.
// Why this test is needed: short surnames differ by one letter across many unrelated families. Allowing a typo here floods the queue with false alarms.
// Scenario: same birthdate, different contacts, names “Cruz” and “Crux”.
// Steps:
// Run the cleanup.
// In tinker, create two applicants with last names Cruz and Crux, birthdate 1990-05-14, and contacts 09170000001 and 09170000002.
// Run the scan.
// Expected result: toast reports 0 new flags. The queue is empty.
// Actual result: ______________________
// Status: Pass / Fail
// EC-04: Contact that differs by one digit is not a match
// Problem: near-miss numbers are treated as the same number.
// Why this test is needed: adjacent numbers are common. Treating them as equal links unrelated people.
// Scenario: same birthdate, different names, contacts 09171234567 and 09171234568.
// Steps:
// Run the cleanup.
// In tinker, create “Garcia” with contact 09171234567 and “Villanueva” with contact 09171234568, both with birthdate 1990-05-14.
// Run the scan.
// Expected result: 0 new flags. Only the birthdate matches, which is 1 point.
// Actual result: ______________________
// Status: Pass / Fail
// EC-05: A matching contact alone never creates a flag
// Problem: two people sharing one phone number are flagged.
// Why this test is needed: households and families share phones. One matching criterion is not enough to call two records duplicates.
// Scenario: same contact, different birthdates, different names.
// Steps:
// Run the cleanup.
// In tinker, create “Garcia” with birthdate 1990-01-01 and “Villanueva” with birthdate 1992-02-02, both with contact 09171234567.
// Run the scan.
// Expected result: 0 new flags.
// Actual result: ______________________
// Status: Pass / Fail
// EC-06: Birthdate one day apart is not a match
// Problem: birthdates off by one day count as the same.
// Why this test is needed: the birthdate rule is an exact match. A loose rule would flag twins, cousins, and mistyped dates for unrelated people.
// Scenario: birthdates 1990-05-14 and 1990-05-15, different names, same contact.
// Steps:
// Run the cleanup.
// In tinker, create “Garcia” with birthdate 1990-05-14 and “Villanueva” with birthdate 1990-05-15, both with contact 09171234567.
// Run the scan.
// Expected result: 0 new flags.
// Actual result: ______________________
// Status: Pass / Fail
// EC-07: Every common format of the same number matches
// Problem: the same phone number written in another format is not recognized.
// Why this test is needed: staff enter numbers in whatever format the resident uses. A format mismatch hides real duplicates.
// Scenario: the older record has 09171234567. Run the case four times, changing only the newer record’s contact to each format below. Use different names (“Garcia” and “Villanueva”) and the same birthdate 1990-05-14.
// Formats to test, one run each:
// +63 917-123-4567
// 917-123-4567
// 63 917 123 4567
// Steps (repeat for each format):
// Run the cleanup.
// In tinker, create the older and newer applicants as described.
// Run the scan.
// Expected result: each run creates 1 flag at 2/3 with Birthdate and Contact badges.
// Actual result: Format 1: ________ Format 2: ________ Format 3: ________
// Status: Pass / Fail
// EC-08: A number that normalizes to nothing does not match
// Problem: “+63” and “63” are treated as equal.
// Why this test is needed: a partly typed number becomes empty after cleaning. If empty values match, unrelated people get flagged.
// Scenario: same birthdate, different names, contacts +63 and 63.
// Steps:
// Run the cleanup.
// In tinker, create “Garcia” and “Villanueva” with birthdate 1990-05-14 and contacts +63 and 63 respectively.
// Run the scan.
// Expected result: 0 new flags.
// Actual result: ______________________
// Status: Pass / Fail
// EC-09: Names with no Latin letters do not count as a name match
// Problem: symbol-only names (“---” and “***”) are treated as an identical name.
// Why this test is needed: blank or junk name entries exist. Equal junk must not add a name point. The pair can still match on birthdate and contact.
// Scenario: same birthdate and same contact, last names “---” and “***”.
// Steps:
// Run the cleanup.
// In tinker, create two applicants with last names --- and ***, birthdate 1990-05-14, and contact 09171234567.
// Run the scan.
// Expected result: 1 new flag at 2/3 with Birthdate and Contact badges and no Name badge.
// Actual result: ______________________
// Status: Pass / Fail
// EC-10: Three identical records create three flags, one per pair
// Problem: a record that matches through both birthdate and contact is flagged twice, or the count is wrong.
// Why this test is needed: staff would see duplicate rows and an inflated count, and would review the same pair twice.
// Scenario: three applicants with the same name, birthdate, and contact.
// Steps:
// Run the cleanup.
// In tinker, create three applicants with last name Cruz, birthdate 1990-05-14, and contact 09171234567.
// Run the scan.
// Expected result: toast reports 3 new flags. The queue has exactly 3 rows. In tinker, the database has 3 flag rows, and no two rows share the same pair of IDs.
// Actual result: ______________________
// Status: Pass / Fail
// EC-11: A later scan flags only pairs with the new record
// Problem: every scan repeats old flags, or the new record is not matched.
// Why this test is needed: staff run the scan many times. Repeats clutter the queue, and missing new pairs means duplicates slip through.
// Scenario: create a matching pair, scan, then add a third matching record and scan again.
// Steps:
// Run the cleanup.
// In tinker, create two applicants with last name Cruz, birthdate 1990-05-14, and contact 09171234567.
// Run the scan. Note the toast (expected: 1 new flag).
// In tinker, create a third applicant with the same name, birthdate, and contact.
// Run the scan again.
// Expected result: the second toast reports 2 new flags. The queue has 3 rows in total. The first pair appears only once.
// Actual result: ______________________
// Status: Pass / Fail
// EC-12: A resolved pair stays blocked for any outcome
// Problem: a pair marked Deleted or Merged is flagged again on the next scan.
// Why this test is needed: any past decision must be final. Otherwise staff must re-review the same pair.
// Scenario: a pair already has a flag resolved as “Deleted,” then as “Merged.”
// Steps:
// Run the cleanup.
// In tinker, create two matching applicants (last name Cruz, birthdate 1990-05-14, contact 09171234567). Note their IDs as $x and $y.
// In tinker, create the resolved flag:
// php
//      App\Models\DuplicateFlag::create(['applicant_id_new' => $y->id, 'applicant_id_existing' => $x->id, 'matched_phonetic' => true, 'matched_birthdate' => true, 'matched_contact' => true, 'match_score' => 3, 'resolution_status' => 'Deleted']);
// Run the scan. Expected: 0 new flags.
// Change 'Deleted' to 'Merged' in the flag row, or run the cleanup and repeat steps 2 to 4 with 'Merged'. Run the scan again.
// Expected result: both runs report 0 new flags. The queue does not show the pair.
// Actual result: Deleted: ________ Merged: ________
// Status: Pass / Fail
// EC-13: A verified newer record is flagged and keeps Verified
// Problem: a scan changes a verified record to Flagged, or the verified record is not flagged at all.
// Why this test is needed: verified residents must still be checked for duplicates, but their verified status is a staff decision that a scan must not overwrite.
// Scenario: an older Pending applicant and a newer Verified applicant match on all three criteria.
// Steps:
// Run the cleanup.
// In tinker, create “Cruz” (birthdate 1990-05-14, contact 09171234567, status Pending), then create a second “Cruz” with the same birthdate and contact, status Verified.
// Run the scan.
// In tinker, check both statuses.
// Expected result: 1 new flag, with the verified record as the newer one. The newer record’s status is still Verified. The older record is still Pending.
// Actual result: ______________________
// Status: Pass / Fail
// Summary
// Area	Unit test	Manual case
// Accents and case	test_accents_and_letter_case_are_ignored_in_last_name	EC-01
// Name suffix	test_name_suffix_is_ignored_when_comparing	EC-02
// Short-name typo boundary	test_one_letter_difference_in_a_short_name_is_not_a_match	EC-03
// Non-Latin names	test_names_without_latin_letters_never_count_as_a_name_match	EC-09
// Contact near-miss	test_contact_differing_by_one_digit_is_not_a_match	EC-04
// Contact formats	test_contact_formats_of_the_same_number_match	EC-07
// Empty after normalizing	test_numbers_that_normalize_to_nothing_never_match	EC-08
// Contact alone	test_contact_match_alone_never_creates_a_flag	EC-05
// Birthdate one day off	test_birthdate_one_day_apart_is_not_a_match	EC-06
// One flag per pair	test_three_identical_records_create_one_flag_per_pair	EC-10
// Later scans	test_later_scan_flags_only_pairs_involving_new_records	EC-11
// Verified newer record	test_verified_newer_record_is_flagged_but_keeps_its_status	EC-13
// Resolved statuses	test_resolved_flag_blocks_pair_for_any_outcome	EC-12
// Empty database	test_scan_on_empty_database_returns_zero_counts	Covered by DD-19

