<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Municipality;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * The Applicant model contains query scopes (active(), byBarangay(),
 * byDateRange(), byEducation(), bySkillCategory()) that are used in
 * EVERY analytics query and in the ApplicantManagement search.
 *
 * TESTING MINDSET:
 * Query scopes are invisible bugs. If the active() scope silently
 * returns ALL records (including inactive), the analytics dashboard
 * would include deactivated applicants in workforce counts — inflating
 * PESO's reported numbers to DOLE. Nobody would notice immediately.
 * These tests make that failure VISIBLE the moment the bug is introduced.
 *
 * I also test the full_name accessor because it is used in the UI
 * and in the Excel export. If it returns "Lastname, Firstname null"
 * instead of "Lastname, Firstname" when middle name is absent,
 * every export row would have "null" in the name column.
 */
class ApplicantModelTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────
    // SCOPE: active()
    // ─────────────────────────────────────────────────────────────────

    /**
     * active() scope must return ONLY applicants where is_active = true.
     * Deactivated records must be excluded.
     *
     * WHY: Every analytics query uses this scope. If it leaks inactive
     * records, ALL dashboard numbers are wrong.
     */
    public function test_active_scope_excludes_inactive_applicants(): void
    {
        // Create 3 active and 2 inactive
        Applicant::factory()->count(3)->create(['is_active' => true]);
        Applicant::factory()->count(2)->create(['is_active' => false]);

        $result = Applicant::active()->get();

        $this->assertCount(3, $result,
            'active() scope should return only the 3 active applicants.'
        );

        // Extra check: none of the returned records is inactive
        $result->each(function ($a) {
            $this->assertTrue($a->is_active,
                'active() scope must never return a record where is_active = false.'
            );
        });
    }

    /**
     * active() with no active applicants must return empty collection,
     * not throw an exception.
     *
     * WHY: On a fresh installation before any registrations,
     * the dashboard would crash if the scope throws on empty.
     */
    public function test_active_scope_returns_empty_when_none_active(): void
    {
        Applicant::factory()->count(3)->create(['is_active' => false]);

        $result = Applicant::active()->get();

        $this->assertCount(0, $result,
            'active() scope should return empty collection, not throw, when all are inactive.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SCOPE: byBarangay()
    // ─────────────────────────────────────────────────────────────────

    /**
     * byBarangay($id) must filter to only that barangay's applicants.
     */
    public function test_by_barangay_scope_filters_correctly(): void
    {
        $municipality = Municipality::factory()->create();
        $barangay1    = Barangay::factory()->for($municipality)->create();
        $barangay2    = Barangay::factory()->for($municipality)->create();

        Applicant::factory()->count(4)->create(['barangay_id' => $barangay1->id]);
        Applicant::factory()->count(2)->create(['barangay_id' => $barangay2->id]);

        $result = Applicant::byBarangay($barangay1->id)->get();

        $this->assertCount(4, $result,
            'byBarangay() should return only applicants from the specified barangay.'
        );
    }

    /**
     * byBarangay(null) must return ALL applicants — not filter at all.
     *
     * WHY: In the analytics dashboard, when no barangay filter is selected,
     * the filter value is null. The scope must pass through in that case.
     * If null causes a WHERE barangay_id = null query, the dashboard
     * returns zero results when no filter is selected — breaking analytics.
     */
    public function test_by_barangay_scope_with_null_returns_all(): void
    {
        $municipality = Municipality::factory()->create();
        $barangay     = Barangay::factory()->for($municipality)->create();
        Applicant::factory()->count(5)->create(['barangay_id' => $barangay->id]);

        $result = Applicant::byBarangay(null)->get();

        $this->assertCount(5, $result,
            'byBarangay(null) should return ALL applicants, not filter.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SCOPE: byDateRange()
    // ─────────────────────────────────────────────────────────────────

    /**
     * byDateRange() must include records on the boundary dates (inclusive).
     *
     * WHY: Off-by-one date bugs are very common. "From January 1 to
     * January 31" — does it include January 1? Does it include January 31?
     * PESO staff would set date ranges for monthly reports. If the
     * boundary is exclusive, they would miss records from the first
     * or last day of the month.
     */
    public function test_date_range_scope_is_inclusive_on_both_ends(): void
    {
        // Create applicants on the boundary dates and outside
        Applicant::factory()->create(['created_at' => '2025-01-01 00:00:01']); // ON start
        Applicant::factory()->create(['created_at' => '2025-01-15 12:00:00']); // INSIDE
        Applicant::factory()->create(['created_at' => '2025-01-31 23:59:59']); // ON end
        Applicant::factory()->create(['created_at' => '2024-12-31 23:59:59']); // BEFORE start
        Applicant::factory()->create(['created_at' => '2025-02-01 00:00:01']); // AFTER end

        $result = Applicant::byDateRange('2025-01-01', '2025-01-31')->get();

        $this->assertCount(3, $result,
            'byDateRange() must be inclusive of both the start date and end date.'
        );
    }

    /**
     * byDateRange(null, null) must return ALL records.
     *
     * WHY: Same null-passthrough logic as byBarangay(null).
     */
    public function test_date_range_scope_with_nulls_returns_all(): void
    {
        Applicant::factory()->count(5)->create();

        $result = Applicant::byDateRange(null, null)->get();

        $this->assertCount(5, $result,
            'byDateRange(null, null) must not filter anything.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // ACCESSOR: getFullNameAttribute()
    // ─────────────────────────────────────────────────────────────────

    /**
     * Full name must be formatted "LASTNAME, FIRSTNAME MIDDLENAME".
     */
    public function test_full_name_accessor_formats_correctly(): void
    {
        $applicant = Applicant::factory()->make([
            'last_name'   => 'Santos',
            'first_name'  => 'Juan',
            'middle_name' => 'Cruz',
        ]);

        $this->assertEquals('Santos, Juan Cruz', $applicant->full_name);
    }

    /**
     * Full name with NULL middle name must NOT include "null" in the output.
     *
     * WHY: PHP string concatenation: "Santos, Juan " . null = "Santos, Juan "
     * which after trim() = "Santos, Juan" — correct.
     * But if the accessor is written carelessly as
     * "{$last}, {$first} {$middle}" without null coalescing,
     * it could produce "Santos, Juan null" — which would appear in
     * every export row where the applicant has no middle name.
     */
    public function test_full_name_accessor_handles_null_middle_name(): void
    {
        $applicant = Applicant::factory()->make([
            'last_name'   => 'Reyes',
            'first_name'  => 'Maria',
            'middle_name' => null,
        ]);

        $name = $applicant->full_name;

        $this->assertEquals('Reyes, Maria', $name,
            'Full name must not include the word "null" when middle name is absent.'
        );
        $this->assertStringNotContainsString('null', strtolower($name),
            'The word "null" must never appear in a full_name output.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // BOOT: reference_id and metaphone auto-generated on create
    // ─────────────────────────────────────────────────────────────────

    /**
     * reference_id must be auto-generated on create, never null.
     *
     * WHY: The reference_id is the resident's proof of registration.
     * If it is null, the confirmation screen shows nothing and the
     * resident has no way to reference their submission.
     */
    public function test_reference_id_auto_generated_on_create(): void
    {
        $applicant = Applicant::factory()->create([
            'reference_id' => null, // force null to test boot override
        ]);

        $this->assertNotNull($applicant->reference_id,
            'reference_id must be auto-generated and never null.'
        );
        $this->assertStringStartsWith('PESO-', $applicant->reference_id,
            'reference_id must start with the PESO- prefix.'
        );
    }

    /**
     * last_name_metaphone must be auto-generated on create.
     *
     * WHY: The duplicate detection service uses this indexed field
     * for fast pre-filtering. If it is null, the metaphone comparison
     * in DuplicateDetectionService would fail silently.
     */
    public function test_metaphone_auto_generated_on_create(): void
    {
        $applicant = Applicant::factory()->create([
            'last_name' => 'Santos',
        ]);

        $this->assertNotNull($applicant->last_name_metaphone,
            'last_name_metaphone must be auto-set from last_name on create.'
        );
        $this->assertEquals(
            metaphone('Santos'),
            $applicant->last_name_metaphone,
            'Metaphone must match PHP metaphone() output for the last_name.'
        );
    }
}
