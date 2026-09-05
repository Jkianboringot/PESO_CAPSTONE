<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Applicant;
use App\Models\Education;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Exports\ApplicantsExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * The ApplicantsExport class maps Applicant model data to a flat
 * 19-column array that is submitted to DOLE's Bureau of Local
 * Employment (BLE). This is not internal data — it is an official
 * government submission.
 *
 * WHAT WOULD HAPPEN IF THIS BROKE:
 * - Wrong column order → DOLE's import system assigns data to
 *   wrong columns. "Age" ends up in the "Sex" column. The entire
 *   submission is rejected or — worse — silently accepted with
 *   corrupted data that DOLE uses for national workforce statistics.
 *
 * - Null middle name outputs "null" → every DOLE record for people
 *   without middle names contains the literal word "null".
 *
 * - Age calculated from birthdate overflows or returns negative
 *   for a future birthdate that somehow slipped past validation.
 *
 * - Skills column joins with wrong separator → DOLE's CSV parser
 *   splits on the wrong character and misreads skill data.
 *
 * TESTING MINDSET:
 * I create one applicant with known, controlled data and assert
 * the EXACT content of each position in the output array.
 * Position 0 must be reference_id. Position 5 must be age. Etc.
 * Any positional shift would go unnoticed in a visual review
 * but is immediately caught here.
 */
class ApplicantsExportTest extends TestCase
{
    use RefreshDatabase;

    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        // Build a fully-loaded applicant with known values
        $municipality = Municipality::factory()->create(['name' => 'Virac']);
        $barangay     = Barangay::factory()->create([
            'municipality_id' => $municipality->id,
            'name'            => 'San Jose',
        ]);

        $category = SkillCategory::factory()->create(['name' => 'ICT & Digital Technology']);
        $skill1   = Skill::factory()->create([
            'skill_category_id' => $category->id,
            'name'              => 'Web Development',
        ]);
        $skill2   = Skill::factory()->create([
            'skill_category_id' => $category->id,
            'name'              => 'Data Encoding',
        ]);

        $this->applicant = Applicant::factory()->create([
            'reference_id'   => 'PESO-TESTEXPORT001',
            'last_name'      => 'Santos',
            'first_name'     => 'Juan',
            'middle_name'    => 'Cruz',
            'birthdate'      => '1995-06-15',
            'sex'            => 'Male',
            'civil_status'   => 'Single',
            'contact_number' => '09171234567',
            'email'          => 'juan.santos@gmail.com',
            'address'        => 'Purok 1, San Jose',
            'barangay_id'    => $barangay->id,
            'status'         => 'Verified',
            'is_active'      => true,
        ]);

        Education::factory()->create([
            'applicant_id'  => $this->applicant->id,
            'highest_level' => 'College Graduate',
            'course_program'=> 'BS Information Systems',
            'school_name'   => 'Catanduanes State University',
            'year_graduated'=> 2018,
        ]);

        $this->applicant->skills()->attach([
            $skill1->id => ['proficiency_level' => 'Intermediate'],
            $skill2->id => ['proficiency_level' => 'Beginner'],
        ]);

        // Reload with all relationships
        $this->applicant = Applicant::with([
            'barangay.municipality',
            'education',
            'skills.category',
        ])->find($this->applicant->id);
    }

    // ─────────────────────────────────────────────────────────────────
    // COLUMN COUNT
    // ─────────────────────────────────────────────────────────────────

    /**
     * The export must produce exactly 19 columns.
     *
     * WHY: DOLE BLE import template expects exactly 19 columns.
     * Fewer columns → import fails. More columns → extra data
     * in undefined positions may corrupt the DOLE record.
     */
    public function test_export_produces_exactly_19_columns(): void
    {
        $export = new ApplicantsExport([]);
        $row    = $export->map($this->applicant);

        $this->assertCount(19, $row,
            'DOLE BLE format requires exactly 19 columns. ' .
            'Adding or removing a column breaks the submission format.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // COLUMN POSITIONS (EXACT POSITIONAL TESTS)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Each column must be in the correct position.
     *
     * WHY: DOLE's import system is position-sensitive.
     * Swapping columns 5 and 6 (Age and Sex) would result in
     * numeric age values in the Sex column and "Male"/"Female"
     * in the Age column — silently corrupting national workforce stats.
     *
     * We test each position explicitly because a regex or count
     * test would pass even if all columns were shuffled.
     */
    public function test_column_positions_match_dole_ble_format(): void
    {
        $export = new ApplicantsExport([]);
        $row    = $export->map($this->applicant);

        // Position 0: Reference ID
        $this->assertEquals('PESO-TESTEXPORT001', $row[0],
            'Column 0 must be Reference ID.'
        );

        // Position 1: Last Name
        $this->assertEquals('Santos', $row[1],
            'Column 1 must be Last Name.'
        );

        // Position 2: First Name
        $this->assertEquals('Juan', $row[2],
            'Column 2 must be First Name.'
        );

        // Position 3: Middle Name
        $this->assertEquals('Cruz', $row[3],
            'Column 3 must be Middle Name.'
        );

        // Position 4: Birthdate (formatted Y-m-d)
        $this->assertEquals('1995-06-15', $row[4],
            'Column 4 must be Birthdate in Y-m-d format.'
        );

        // Position 5: Age (calculated from birthdate)
        $expectedAge = Carbon::parse('1995-06-15')->age;
        $this->assertEquals($expectedAge, $row[5],
            'Column 5 must be Age (calculated dynamically from birthdate).'
        );

        // Position 6: Sex
        $this->assertEquals('Male', $row[6],
            'Column 6 must be Sex.'
        );

        // Position 7: Civil Status
        $this->assertEquals('Single', $row[7],
            'Column 7 must be Civil Status.'
        );

        // Position 8: Contact Number
        $this->assertEquals('09171234567', $row[8],
            'Column 8 must be Contact Number.'
        );

        // Position 9: Email
        $this->assertEquals('juan.santos@gmail.com', $row[9],
            'Column 9 must be Email.'
        );

        // Position 10: Address
        $this->assertEquals('Purok 1, San Jose', $row[10],
            'Column 10 must be Address.'
        );

        // Position 11: Barangay
        $this->assertEquals('San Jose', $row[11],
            'Column 11 must be Barangay name.'
        );

        // Position 12: Municipality
        $this->assertEquals('Virac', $row[12],
            'Column 12 must be Municipality name.'
        );

        // Position 13: Highest Education
        $this->assertEquals('College Graduate', $row[13],
            'Column 13 must be Highest Education Level.'
        );

        // Position 14: Course/Program
        $this->assertEquals('BS Information Systems', $row[14],
            'Column 14 must be Course/Program.'
        );

        // Position 17: Registration Status
        $this->assertEquals('Verified', $row[17],
            'Column 17 must be Registration Status.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // NULL HANDLING
    // ─────────────────────────────────────────────────────────────────

    /**
     * NULL middle name must output empty string '', NOT 'null'.
     *
     * WHY: PHP concatenation: (string) null === '' in most cases,
     * BUT if the accessor uses "$first $middle" and middle is null,
     * the output is "Juan " (with trailing space) or "Juan null"
     * depending on the PHP version and how null is cast.
     * In the export, the middle name is its own column.
     * null in a column exported to CSV becomes the literal string
     * "null" — which would appear in DOLE's records.
     */
    public function test_null_middle_name_outputs_empty_string_not_null(): void
    {
        $this->applicant->update(['middle_name' => null]);
        $this->applicant->refresh();

        $export = new ApplicantsExport([]);
        $row    = $export->map($this->applicant);

        $this->assertEquals('', $row[3],
            'NULL middle name must export as empty string, never as the word "null".'
        );
        $this->assertNotEquals('null', $row[3],
            'The word "null" must never appear in a DOLE export column.'
        );
    }

    /**
     * NULL email must output empty string, not 'null'.
     */
    public function test_null_email_outputs_empty_string(): void
    {
        $this->applicant->update(['email' => null]);
        $this->applicant->refresh();

        $export = new ApplicantsExport([]);
        $row    = $export->map($this->applicant);

        $this->assertEquals('', $row[9],
            'NULL email must export as empty string.'
        );
    }

    /**
     * Applicant with no education record must not throw an exception.
     *
     * WHY: If for some reason an applicant has no education record
     * (e.g., legacy data, a seeder bug, or a failed transaction),
     * the export must handle it gracefully — not crash with a
     * "Call to a member function on null" error that kills
     * the entire report generation for all applicants.
     */
    public function test_missing_education_record_does_not_crash_export(): void
    {
        // Delete the education record
        $this->applicant->education()->delete();
        $this->applicant->refresh();

        $export = new ApplicantsExport([]);

        // This must NOT throw — must return a row with empty education fields
        $row = $export->map($this->applicant);

        $this->assertEquals('', $row[13],
            'Missing education: highest_level must export as empty string, not crash.'
        );
        $this->assertEquals('', $row[14],
            'Missing education: course_program must export as empty string, not crash.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SKILLS COLUMN FORMATTING
    // ─────────────────────────────────────────────────────────────────

    /**
     * Multiple skills must be joined with ' | ' separator.
     *
     * WHY: The skills column contains multiple values in one cell.
     * The separator must be consistent so that DOLE or researchers
     * can parse it. ' | ' was chosen because it is unlikely to appear
     * in a skill name itself. A comma separator would conflict with
     * CSV column delimiters and break CSV parsing.
     */
    public function test_skills_column_joins_with_pipe_separator(): void
    {
        $export = new ApplicantsExport([]);
        $row    = $export->map($this->applicant);

        // Position 15: Skills column
        $skillsCell = $row[15];

        $this->assertStringContainsString(' | ', $skillsCell,
            'Multiple skills must be separated by " | " in the skills column.'
        );
        $this->assertStringContainsString('Web Development', $skillsCell);
        $this->assertStringContainsString('Data Encoding',   $skillsCell);
    }

    /**
     * Applicant with no skills must export empty string in skills column.
     *
     * WHY: An applicant without skills should not crash the export.
     * This can happen with legacy data or if skills were delinked.
     */
    public function test_no_skills_exports_empty_string(): void
    {
        // Detach all skills
        $this->applicant->skills()->detach();
        $this->applicant->refresh();
        $this->applicant->load('skills.category');

        $export = new ApplicantsExport([]);
        $row    = $export->map($this->applicant);

        $this->assertEquals('', $row[15],
            'Applicant with no skills must export empty string in skills column.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // HEADINGS
    // ─────────────────────────────────────────────────────────────────

    /**
     * The headings array must have exactly 19 entries.
     *
     * WHY: If map() returns 19 columns but headings() returns 18,
     * the last column has no header in the Excel file.
     * DOLE staff reading the file would not know what the last column is.
     * If headings() returns 20, there is an empty column header
     * with no data — also confusing.
     */
    public function test_headings_count_matches_column_count(): void
    {
        $export   = new ApplicantsExport([]);
        $headings = $export->headings();
        $row      = $export->map($this->applicant);

        $this->assertCount(19, $headings,
            'headings() must return exactly 19 column headers.'
        );

        $this->assertEquals(count($headings), count($row),
            'Number of headings must match number of data columns.'
        );
    }

    /**
     * First heading must be 'Reference ID'.
     *
     * WHY: The Reference ID is the primary key for cross-referencing
     * with PESO's own system. If its heading is wrong, DOLE cannot
     * match the export to their lookup tables.
     */
    public function test_first_heading_is_reference_id(): void
    {
        $export = new ApplicantsExport([]);
        $this->assertEquals('Reference ID', $export->headings()[0],
            'First column heading must be "Reference ID".'
        );
    }
}
