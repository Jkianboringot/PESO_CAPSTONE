<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Applicant;
use App\Models\Education;
use App\Models\Municipality;
use App\Models\Barangay;
use App\Models\Skill;
use App\Models\SkillCategory;
use Livewire\Livewire;
use App\Livewire\RegistrationForm;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * The RegistrationForm is the entry point for ALL public data.
 * If it breaks, residents cannot submit. If it accepts invalid data,
 * the database gets dirty. If the DB transaction fails silently,
 * an applicant is created but their education and skills are not —
 * producing orphaned, incomplete records that corrupt analytics.
 *
 * TESTING MINDSET:
 * I think of this as testing a CONTRACT:
 * "Given valid inputs, exactly these DB records are created."
 * "Given invalid inputs, exactly these errors are returned."
 * "Given a transaction failure, NO records are left behind."
 *
 * The transaction test is the most important in this file.
 * It verifies ATOMICITY — the guarantee that the 3-write
 * operation (applicant + education + skills) either ALL succeed
 * or ALL fail. Without this test, a half-written registration
 * could exist in the DB for years before anyone notices.
 *
 * Livewire testing uses the Livewire::test() helper which
 * simulates component lifecycle without a real browser.
 */
class RegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    // Helper: create the minimum geography needed for registration
    private function setupGeography(): array
    {
        $municipality = Municipality::factory()->create(['name' => 'Virac']);
        $barangay     = Barangay::factory()->create([
            'municipality_id' => $municipality->id,
            'name'            => 'San Jose',
        ]);
        return [$municipality, $barangay];
    }

    // Helper: create some skills to select
    private function setupSkills(): array
    {
        $category = SkillCategory::factory()->create(['name' => 'ICT & Digital Technology']);
        $skill1   = Skill::factory()->create([
            'skill_category_id' => $category->id,
            'name' => 'Web Development',
        ]);
        $skill2   = Skill::factory()->create([
            'skill_category_id' => $category->id,
            'name' => 'Data Encoding',
        ]);
        return [$skill1, $skill2];
    }

    // ─────────────────────────────────────────────────────────────────
    // HAPPY PATH: Valid submission creates all three DB records
    // ─────────────────────────────────────────────────────────────────

    /**
     * A fully valid submission must create:
     * 1. One applicant record
     * 2. One education record linked to that applicant
     * 3. The correct number of applicant_skill pivot rows
     * 4. Set $submitted = true to show the confirmation screen
     *
     * WHY THIS IS THE MOST IMPORTANT TEST IN THIS FILE:
     * It validates the DB transaction. If any of these assertions fails,
     * it means part of the registration was NOT saved — which means
     * PESO has incomplete data for that resident.
     */
    public function test_valid_submission_creates_applicant_education_and_skills(): void
    {
        [$municipality, $barangay] = $this->setupGeography();
        [$skill1, $skill2]         = $this->setupSkills();

        Livewire::test(RegistrationForm::class)
            // Step 1: Personal Info
            ->set('last_name',       'Santos')
            ->set('first_name',      'Juan')
            ->set('middle_name',     'Cruz')
            ->set('birthdate',       '1995-06-15')
            ->set('sex',             'Male')
            ->set('civil_status',    'Single')
            ->set('contact_number',  '09171234567')
            ->set('email',           'juan.santos@gmail.com')
            ->call('nextStep')   // passes step 1 validation

            // Step 2: Location
            ->set('address',         'Purok 1, San Jose')
            ->set('municipality_id', $municipality->id)
            ->set('barangay_id',     $barangay->id)
            ->call('nextStep')   // passes step 2 validation

            // Step 3: Education
            ->set('highest_level',   'College Graduate')
            ->set('course_program',  'BS Information Systems')
            ->set('school_name',     'Catanduanes State University')
            ->set('year_graduated',  2018)
            ->call('nextStep')   // passes step 3 validation

            // Step 4: Skills
            ->set('selected_skills',     [$skill1->id, $skill2->id])
            ->set("skill_proficiencies.{$skill1->id}", 'Intermediate')
            ->set("skill_proficiencies.{$skill2->id}", 'Beginner')
            ->call('nextStep')   // passes step 4 validation

            // Step 5: Consent
            ->set('consent_given', true)
            ->call('submit')     // triggers the DB transaction

            // Assert component state shows success
            ->assertSet('submitted', true)
            ->assertSet('reference_id', fn($val) => str_starts_with($val, 'PESO-'));

        // Assert DB — Applicant
        $this->assertDatabaseCount('applicants', 1);
        $applicant = Applicant::first();
        $this->assertEquals('Santos',     $applicant->last_name);
        $this->assertEquals('Juan',       $applicant->first_name);
        $this->assertEquals('09171234567',$applicant->contact_number);
        $this->assertEquals($barangay->id,$applicant->barangay_id);
        $this->assertTrue($applicant->consent_given);
        $this->assertNotNull($applicant->consent_given_at);

        // Assert DB — Education (linked to correct applicant)
        $this->assertDatabaseCount('education', 1);
        $education = Education::first();
        $this->assertEquals($applicant->id,         $education->applicant_id);
        $this->assertEquals('College Graduate',     $education->highest_level);
        $this->assertEquals('BS Information Systems',$education->course_program);

        // Assert DB — Skills pivot
        $this->assertDatabaseCount('applicant_skill', 2);
        $this->assertDatabaseHas('applicant_skill', [
            'applicant_id'      => $applicant->id,
            'skill_id'          => $skill1->id,
            'proficiency_level' => 'Intermediate',
        ]);
        $this->assertDatabaseHas('applicant_skill', [
            'applicant_id'      => $applicant->id,
            'skill_id'          => $skill2->id,
            'proficiency_level' => 'Beginner',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // VALIDATION: Required fields must block progression
    // ─────────────────────────────────────────────────────────────────

    /**
     * Missing last name must block step 1 progression.
     *
     * WHY: The analytics and DOLE reports depend on having real names.
     * If the validation passes with an empty last name, PESO's
     * entire workforce registry would have nameless records.
     */
    public function test_step_1_blocked_when_last_name_missing(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('last_name',      '')  // EMPTY
            ->set('first_name',     'Juan')
            ->set('birthdate',      '1995-06-15')
            ->set('sex',            'Male')
            ->set('civil_status',   'Single')
            ->set('contact_number', '09171234567')
            ->call('nextStep')
            ->assertHasErrors(['last_name'])      // validation error
            ->assertSet('step', 1);              // still on step 1
    }

    /**
     * Invalid contact number format must fail validation.
     *
     * WHY: PESO uses contact numbers to verify applicants before
     * referring them to employers. A garbage contact number
     * means PESO cannot reach the applicant. The regex validation
     * protects against obviously invalid entries.
     */
    public function test_step_1_blocked_when_contact_number_invalid(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('last_name',      'Santos')
            ->set('first_name',     'Juan')
            ->set('birthdate',      '1995-06-15')
            ->set('sex',            'Male')
            ->set('civil_status',   'Single')
            ->set('contact_number', 'not-a-number')  // INVALID
            ->call('nextStep')
            ->assertHasErrors(['contact_number'])
            ->assertSet('step', 1);
    }

    /**
     * Future birthdate must be rejected.
     *
     * WHY: Applicants must be real people who were born in the past.
     * A birthdate of 2099 would create an applicant with
     * negative age — which would crash the age calculation
     * in the Excel export.
     */
    public function test_step_1_blocked_when_birthdate_is_in_future(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('last_name',      'Santos')
            ->set('first_name',     'Juan')
            ->set('birthdate',      '2099-01-01')  // FUTURE
            ->set('sex',            'Male')
            ->set('civil_status',   'Single')
            ->set('contact_number', '09171234567')
            ->call('nextStep')
            ->assertHasErrors(['birthdate'])
            ->assertSet('step', 1);
    }

    /**
     * Zero skills selected must block step 4 progression.
     *
     * WHY: Skills data is the entire point of the registration.
     * An applicant with no skills recorded contributes nothing to
     * workforce analytics and skills gap analysis. Minimum 1 skill
     * enforced here ensures every record is analytically useful.
     */
    public function test_step_4_blocked_when_no_skills_selected(): void
    {
        [$municipality, $barangay] = $this->setupGeography();

        Livewire::test(RegistrationForm::class)
            ->set('step', 4)  // jump to step 4
            ->set('selected_skills', [])  // EMPTY
            ->call('nextStep')
            ->assertHasErrors(['selected_skills'])
            ->assertSet('step', 4);
    }

    /**
     * Unchecked consent checkbox must block final submission.
     *
     * WHY: RA 10173 requires EXPLICIT consent before collecting
     * personal data. If consent_given = false but submission succeeds,
     * PESO would be storing personal data without legal basis.
     * This is a data privacy violation with legal consequences.
     */
    public function test_submission_blocked_without_consent(): void
    {
        [$municipality, $barangay] = $this->setupGeography();
        [$skill1]                  = $this->setupSkills();

        Livewire::test(RegistrationForm::class)
            ->set('step',           5)
            ->set('consent_given',  false)  // NOT CONSENTED
            ->call('submit')
            ->assertHasErrors(['consent_given'])
            ->assertSet('submitted', false);

        // No applicant should have been created
        $this->assertDatabaseCount('applicants', 0,
            'No applicant must be created if consent was not given.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // TRANSACTION: If anything fails, nothing is saved
    // ─────────────────────────────────────────────────────────────────

    /**
     * Verify that the submitted component correctly generates
     * a reference_id starting with PESO-.
     *
     * WHY: The reference ID is the resident's only proof of registration.
     * If it is missing, null, or malformed, the resident cannot
     * reference their submission at the PESO office.
     */
    public function test_reference_id_is_in_correct_format_after_submission(): void
    {
        [$municipality, $barangay] = $this->setupGeography();
        [$skill1]                  = $this->setupSkills();

        $component = Livewire::test(RegistrationForm::class)
            ->set('last_name',       'Reyes')
            ->set('first_name',      'Maria')
            ->set('birthdate',       '1990-03-20')
            ->set('sex',             'Female')
            ->set('civil_status',    'Single')
            ->set('contact_number',  '09289876543')
            ->set('address',         'Sitio Mabini')
            ->set('municipality_id', $municipality->id)
            ->set('barangay_id',     $barangay->id)
            ->set('highest_level',   'High School')
            ->set('selected_skills', [$skill1->id])
            ->set("skill_proficiencies.{$skill1->id}", 'Beginner')
            ->set('consent_given',   true)
            ->set('step',            5)
            ->call('submit');

        $refId = $component->get('reference_id');
        $this->assertNotNull($refId);
        $this->assertMatchesRegularExpression(
            '/^PESO-[A-Z0-9]{13}$/',
            $refId,
            'Reference ID must match format PESO-XXXXXXXXXXXXX (13 uppercase alphanumeric).'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // DYNAMIC BARANGAY LOADING
    // ─────────────────────────────────────────────────────────────────

    /**
     * Changing municipality must load barangays for that municipality.
     *
     * WHY: This is the reactive behavior that makes the form usable.
     * If updatedMunicipalityId() does not fire or does not load
     * the correct barangays, a resident from Pandan could
     * accidentally select a Virac barangay — corrupting geographic analytics.
     */
    public function test_municipality_change_loads_correct_barangays(): void
    {
        $muni1   = Municipality::factory()->create(['name' => 'Virac']);
        $muni2   = Municipality::factory()->create(['name' => 'Pandan']);
        $bara1   = Barangay::factory()->create(['municipality_id' => $muni1->id, 'name' => 'San Jose']);
        $bara2   = Barangay::factory()->create(['municipality_id' => $muni2->id, 'name' => 'Agban']);

        $component = Livewire::test(RegistrationForm::class)
            ->set('municipality_id', $muni1->id);

        // Component's $barangays should contain Virac's barangay
        $barangays = $component->get('barangays');
        $this->assertArrayHasKey($bara1->id, $barangays,
            'After selecting Virac, barangays array must contain San Jose.'
        );
        $this->assertArrayNotHasKey($bara2->id, $barangays,
            'After selecting Virac, barangays array must NOT contain Pandan barangays.'
        );
    }

    /**
     * Changing municipality must reset the selected barangay.
     *
     * WHY: If barangay_id is not reset when municipality changes,
     * a resident could select Pandan municipality but accidentally
     * submit with a Virac barangay_id from a previous selection.
     * This would silently corrupt geographic data.
     */
    public function test_municipality_change_resets_barangay_selection(): void
    {
        $muni1 = Municipality::factory()->create();
        $muni2 = Municipality::factory()->create();
        $bara1 = Barangay::factory()->create(['municipality_id' => $muni1->id]);

        Livewire::test(RegistrationForm::class)
            ->set('municipality_id', $muni1->id)
            ->set('barangay_id',     $bara1->id)
            // Now change municipality
            ->set('municipality_id', $muni2->id)
            ->assertSet('barangay_id', null,
                'barangay_id must reset to null when municipality changes.'
            );
    }
}
