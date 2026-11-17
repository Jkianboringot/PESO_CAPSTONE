<?php

namespace Tests\Feature;

use App\Livewire\RegistrationForm;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\Education;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\RegistersApplicants;
use Tests\Feature\Concerns\RegistersApplicants as ConcernsRegistersApplicants;
use Tests\TestCase;

/**
 * Tests the Livewire RegistrationForm (the 6-step public form).
 *
 * What was wrong before:
 *  - SkillCategory::factory() does not exist -> helpers now use forceCreate().
 *  - The tests still described the OLD 5-step form. The form now has a password
 *    on step 1, optional experience on step 5, and oath + consent on step 6.
 */
class RegistrationFormTest extends TestCase
{
    use RefreshDatabase, ConcernsRegistersApplicants;

    // ─────────────────────────────────────────────────────────────────
    // HAPPY PATH
    // ─────────────────────────────────────────────────────────────────

    public function test_valid_submission_creates_applicant_education_skills_and_experience(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSet('reference_id', fn ($val) => str_starts_with($val, 'PESO-'));

        // Applicant
        $this->assertDatabaseCount('applicants', 1);
        $applicant = Applicant::first();
        $this->assertEquals('Santos', $applicant->last_name);
        $this->assertEquals('Juan', $applicant->first_name);
        $this->assertEquals('09171234567', $applicant->contact_number);
        $this->assertEquals($barangay->id, $applicant->barangay_id);
        $this->assertTrue((bool) $applicant->oath_given);
        $this->assertNotNull($applicant->oath_given_at);
        $this->assertTrue((bool) $applicant->consent_given);
        $this->assertNotNull($applicant->consent_given_at);
        $this->assertEquals('Pending', $applicant->status);
        $this->assertTrue(Hash::check($this->testPassword, $applicant->password), 'Password must be stored hashed.');

        // Education
        $this->assertDatabaseCount('education', 1);
        $education = Education::first();
        $this->assertEquals($applicant->id, $education->applicant_id);
        $this->assertEquals('College Graduate', $education->highest_level);

        // Skills pivot
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

        // Experience row from step 5
        $this->assertEquals(1, $applicant->workExperiences()->count());
    }

    public function test_reference_id_is_in_correct_format_after_submission(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $component = $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit');

        $refId = $component->get('reference_id');
        $this->assertNotNull($refId);
        $this->assertMatchesRegularExpression(
            '/^PESO-[A-Z0-9]{13}$/',
            $refId,
            'Reference ID must match PESO-XXXXXXXXXXXXX. If only this fails, the model generates a different format.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // STEP VALIDATION
    // ─────────────────────────────────────────────────────────────────

    public function test_step_1_blocked_when_last_name_missing(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('last_name', '')
            ->set('first_name', 'Juan')
            ->set('birthdate', '1995-06-15')
            ->set('sex', 'Male')
            ->set('civil_status', 'Single')
            ->set('contact_number', '09171234567')
            ->set('password', $this->testPassword)
            ->set('password_confirmation', $this->testPassword)
            ->call('nextStep')
            ->assertHasErrors(['last_name'])
            ->assertSet('step', 1);
    }

    public function test_step_1_blocked_when_contact_number_invalid(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('last_name', 'Santos')
            ->set('first_name', 'Juan')
            ->set('birthdate', '1995-06-15')
            ->set('sex', 'Male')
            ->set('civil_status', 'Single')
            ->set('contact_number', 'not-a-number')
            ->set('password', $this->testPassword)
            ->set('password_confirmation', $this->testPassword)
            ->call('nextStep')
            ->assertHasErrors(['contact_number'])
            ->assertSet('step', 1);
    }

    public function test_step_1_blocked_when_birthdate_is_in_future(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('last_name', 'Santos')
            ->set('first_name', 'Juan')
            ->set('birthdate', '2099-01-01')
            ->set('sex', 'Male')
            ->set('civil_status', 'Single')
            ->set('contact_number', '09171234567')
            ->set('password', $this->testPassword)
            ->set('password_confirmation', $this->testPassword)
            ->call('nextStep')
            ->assertHasErrors(['birthdate'])
            ->assertSet('step', 1);
    }

    public function test_step_1_blocked_when_password_confirmation_does_not_match(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('last_name', 'Santos')
            ->set('first_name', 'Juan')
            ->set('birthdate', '1995-06-15')
            ->set('sex', 'Male')
            ->set('civil_status', 'Single')
            ->set('contact_number', '09171234567')
            ->set('password', $this->testPassword)
            ->set('password_confirmation', 'something-else')
            ->call('nextStep')
            ->assertHasErrors(['password'])
            ->assertSet('step', 1);
    }

    public function test_step_4_blocked_when_no_skills_selected(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 4)
            ->set('selected_skills', [])
            ->call('nextStep')
            ->assertHasErrors(['selected_skills'])
            ->assertSet('step', 4);
    }

    public function test_step_5_blocked_when_an_added_job_row_is_left_empty(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 5)
            ->call('addRow', 'work_experiences')   // row added but not filled in
            ->call('nextStep')
            ->assertHasErrors(['work_experiences.0.company_name', 'work_experiences.0.position'])
            ->assertSet('step', 5);
    }

    // ─────────────────────────────────────────────────────────────────
    // FINAL SUBMIT: oath + consent, and "cannot skip steps"
    // ─────────────────────────────────────────────────────────────────

    public function test_submission_blocked_without_consent(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('oath_given', true)
            ->set('consent_given', false)   // NOT CONSENTED
            ->call('submit')
            ->assertHasErrors(['consent_given'])
            ->assertSet('submitted', false);

        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_submission_blocked_without_oath(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('oath_given', false)      // NO OATH
            ->set('consent_given', true)
            ->call('submit')
            ->assertHasErrors(['oath_given'])
            ->assertSet('submitted', false);

        $this->assertDatabaseCount('applicants', 0);
    }

    /**
     * The old FIXME: jumping straight to step 6 and calling submit() with an
     * empty form must NOT save anything, and must send the user back to the
     * first step that has an error.
     */
    public function test_cannot_skip_steps_and_submit_an_empty_form(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 6)
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->assertHasErrors(['last_name', 'password', 'barangay_id', 'selected_skills'])
            ->assertSet('submitted', false)
            ->assertSet('step', 1);

        $this->assertDatabaseCount('applicants', 0);
    }

    // ─────────────────────────────────────────────────────────────────
    // DYNAMIC BARANGAY LOADING
    // ─────────────────────────────────────────────────────────────────

    public function test_municipality_change_loads_correct_barangays(): void
    {
        $muni1 = Municipality::factory()->create(['name' => 'Virac']);
        $muni2 = Municipality::factory()->create(['name' => 'Pandan']);
        $bara1 = Barangay::factory()->create(['municipality_id' => $muni1->id, 'name' => 'San Jose']);
        $bara2 = Barangay::factory()->create(['municipality_id' => $muni2->id, 'name' => 'Agban']);

        $component = Livewire::test(RegistrationForm::class)
            ->set('municipality_id', $muni1->id);

        $barangays = $component->get('barangays');
        $this->assertArrayHasKey($bara1->id, $barangays);
        $this->assertArrayNotHasKey($bara2->id, $barangays);
    }

    public function test_municipality_change_resets_barangay_selection(): void
    {
        $muni1 = Municipality::factory()->create();
        $muni2 = Municipality::factory()->create();
        $bara1 = Barangay::factory()->create(['municipality_id' => $muni1->id]);

        Livewire::test(RegistrationForm::class)
            ->set('municipality_id', $muni1->id)
            ->set('barangay_id', $bara1->id)
            ->set('municipality_id', $muni2->id)
            ->assertSet('barangay_id', null);
    }

        // ─────────────────────────────────────────────────────────────────
    // HELPERS FOR EDGE CASES
    // ─────────────────────────────────────────────────────────────────

    /** Valid step 1 data. Override any key to test one rule at a time. */
    private function stepOneData(array $override = []): array
    {
        return array_merge([
            'last_name'            => 'Santos',
            'first_name'           => 'Juan',
            'birthdate'            => '1995-06-15',
            'sex'                  => 'Male',
            'civil_status'         => 'Single',
            'contact_number'       => '09171234567',
            'password'             => $this->testPassword,
            'password_confirmation' => $this->testPassword,
        ], $override);
    }

    /** Sets each field on a fresh form, then presses Continue. */
    private function continueFromStepOne(array $data)
    {
        $component = Livewire::test(RegistrationForm::class);

        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        return $component->call('nextStep');
    }

    // ─────────────────────────────────────────────────────────────────
    // STEP 1: BOUNDARY VALUES
    // ─────────────────────────────────────────────────────────────────

    /** Born yesterday is the youngest a registrant can be (before:today). */
    public function test_birthdate_yesterday_is_accepted(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'birthdate' => now()->subDay()->toDateString(),
        ]))->assertHasNoErrors()->assertSet('step', 2);
    }

    /** A birthdate of today is not "before today", so it is refused. */
    public function test_birthdate_today_is_rejected(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'birthdate' => now()->toDateString(),
        ]))->assertHasErrors(['birthdate'])->assertSet('step', 1);
    }

    public function test_last_name_of_100_characters_is_accepted(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'last_name' => str_repeat('a', 100),
        ]))->assertHasNoErrors();
    }

    public function test_last_name_of_101_characters_is_rejected(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'last_name' => str_repeat('a', 101),
        ]))->assertHasErrors(['last_name']);
    }

    /** Seven characters is one below the minimum of eight. */
    public function test_password_of_seven_characters_is_rejected(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'password'              => 'Abcdef1',
            'password_confirmation' => 'Abcdef1',
        ]))->assertHasErrors(['password']);
    }

    public function test_password_of_exactly_eight_characters_is_accepted(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'password'              => 'Abcdef12',
            'password_confirmation' => 'Abcdef12',
        ]))->assertHasNoErrors();
    }

    /** The contact rule needs exactly 11 digits. Ten digits passes the regex but fails min:11. */
    public function test_contact_number_of_ten_digits_is_rejected(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'contact_number' => '0917123456',
        ]))->assertHasErrors(['contact_number']);
    }

    public function test_contact_number_with_letters_is_rejected(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'contact_number' => '0917abc4567',
        ]))->assertHasErrors(['contact_number']);
    }

    public function test_sex_value_outside_the_list_is_rejected(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'sex' => 'Unknown',
        ]))->assertHasErrors(['sex']);
    }

    public function test_civil_status_outside_the_list_is_rejected(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'civil_status' => 'Complicated',
        ]))->assertHasErrors(['civil_status']);
    }

    public function test_disability_value_outside_the_list_is_rejected(): void
    {
        $this->continueFromStepOne($this->stepOneData([
            'disabilities' => ['Not a real disability'],
        ]))->assertHasErrors(['disabilities.0']);
    }

    /** Email and middle name are optional. A blank value is stored as NULL. */
    public function test_blank_email_and_middle_name_are_stored_as_null(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('email', '')
            ->set('middle_name', '')
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit');

        $applicant = Applicant::first();
        $this->assertNull($applicant->email);
        $this->assertNull($applicant->middle_name);
    }

    /** Disabilities are optional, and a valid choice is saved. Assumes the model casts disabilities to an array. */
    public function test_selected_disability_is_saved(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('disabilities', [\App\Livewire\Applicant\Profile::DISABILITIES[0]])
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertEquals(
            [\App\Livewire\Applicant\Profile::DISABILITIES[0]],
            Applicant::first()->disabilities
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // STEP 2: ADDRESS
    // ─────────────────────────────────────────────────────────────────

    public function test_missing_barangay_is_rejected(): void
    {
        [$municipality] = $this->makeGeography();

        Livewire::test(RegistrationForm::class)
            ->set('step', 2)
            ->set('address', 'Purok 1')
            ->set('municipality_id', $municipality->id)
            ->call('nextStep')
            ->assertHasErrors(['barangay_id'])
            ->assertSet('step', 2);
    }

    public function test_address_over_255_characters_is_rejected(): void
    {
        [$municipality, $barangay] = $this->makeGeography();

        Livewire::test(RegistrationForm::class)
            ->set('step', 2)
            ->set('address', str_repeat('a', 256))
            ->set('municipality_id', $municipality->id)
            ->set('barangay_id', $barangay->id)
            ->call('nextStep')
            ->assertHasErrors(['address']);
    }

    /**
     * KNOWN GAP (REG-2): the rule only checks that the barangay exists. It does
     * not check that the barangay belongs to the chosen municipality. A tampered
     * request can pair municipality A with a barangay from municipality B.
     *
     * HOW TO REPRODUCE: pick municipality 1 and a barangay that belongs to
     * municipality 2. BUG: the step passes. Expected: validation error on barangay_id.
     *
     * FIX: use Rule::exists('barangays', 'id')->where('municipality_id', $this->municipality_id)
     * for barangay_id in rulesForStep(2).
     */
    public function test_barangay_must_belong_to_the_selected_municipality(): void
    {
        [$municipality] = $this->makeGeography();
        $otherMunicipality = Municipality::factory()->create();
        $otherBarangay     = Barangay::factory()->create(['municipality_id' => $otherMunicipality->id]);

        Livewire::test(RegistrationForm::class)
            ->set('step', 2)
            ->set('address', 'Purok 1')
            ->set('municipality_id', $municipality->id)
            ->set('barangay_id', $otherBarangay->id)
            ->call('nextStep')
            ->assertHasErrors(['barangay_id']);
    }

    // ─────────────────────────────────────────────────────────────────
    // STEP 3: EDUCATION
    // ─────────────────────────────────────────────────────────────────

    public function test_highest_level_is_required(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 3)
            ->call('nextStep')
            ->assertHasErrors(['highest_level']);
    }

    public function test_year_graduated_before_1950_is_rejected(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 3)
            ->set('highest_level', 'College Graduate')
            ->set('year_graduated', '1949')
            ->call('nextStep')
            ->assertHasErrors(['year_graduated']);
    }

    /** The maximum is the current year plus one, so next year is accepted. */
    public function test_year_graduated_next_year_is_accepted(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 3)
            ->set('highest_level', 'College Graduate')
            ->set('year_graduated', (string) (date('Y') + 1))
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 4);
    }

    public function test_year_graduated_two_years_ahead_is_rejected(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 3)
            ->set('highest_level', 'College Graduate')
            ->set('year_graduated', (string) (date('Y') + 2))
            ->call('nextStep')
            ->assertHasErrors(['year_graduated']);
    }

    // ─────────────────────────────────────────────────────────────────
    // STEP 4: SKILLS
    // ─────────────────────────────────────────────────────────────────

    public function test_proficiency_outside_the_list_is_rejected(): void
    {
        [$skill1] = $this->makeSkills();

        Livewire::test(RegistrationForm::class)
            ->set('step', 4)
            ->set('selected_skills', [$skill1->id])
            ->set('skill_proficiencies', [$skill1->id => 'Master'])
            ->call('nextStep')
            ->assertHasErrors(['skill_proficiencies.' . $skill1->id]);
    }

    /** A skill ID that does not exist in the database is refused. */
    public function test_nonexistent_skill_id_is_rejected(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 4)
            ->set('selected_skills', [99999])
            ->call('nextStep')
            ->assertHasErrors(['selected_skills.0']);
    }

    /** A proficiency set for a skill that was not selected is ignored when saving. */
    public function test_proficiency_for_an_unselected_skill_is_not_saved(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('skill_proficiencies', [$skill1->id => 'Advanced', 99999 => 'Expert'])
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('applicant_skill', 2);
        $this->assertDatabaseMissing('applicant_skill', ['skill_id' => 99999]);
    }

    /** A selected skill with no proficiency chosen is saved as Beginner. */
    public function test_skill_with_no_proficiency_is_saved_as_beginner(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('skill_proficiencies', [])
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit');

        $applicant = Applicant::first();
        $this->assertDatabaseHas('applicant_skill', [
            'applicant_id' => $applicant->id, 'skill_id' => $skill1->id, 'proficiency_level' => 'Beginner',
        ]);
        $this->assertDatabaseHas('applicant_skill', [
            'applicant_id' => $applicant->id, 'skill_id' => $skill2->id, 'proficiency_level' => 'Beginner',
        ]);
    }

    public function test_other_skills_over_1000_characters_is_rejected(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 4)
            ->set('selected_skills', [])
            ->set('other_skills', str_repeat('a', 1001))
            ->call('nextStep')
            ->assertHasErrors(['selected_skills', 'other_skills']);
    }

    /** Spaces-only "other skills" is saved as NULL, not an empty string. */
    public function test_blank_other_skills_is_stored_as_null(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('other_skills', '   ')
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit');

        $this->assertNull(Applicant::first()->other_skills);
    }

    // ─────────────────────────────────────────────────────────────────
    // STEP 5: EXPERIENCE AND TRAINING (OPTIONAL SECTIONS)
    // ─────────────────────────────────────────────────────────────────

    /** Step 5 is optional, so with every row removed the user can continue. */
    public function test_removing_an_incomplete_row_lets_the_user_continue(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 5)
            ->call('addRow', 'work_experiences')
            ->call('removeRow', 'work_experiences', 0)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 6);
    }

    /** After a removal the remaining rows are re-numbered from 0, so wire:model keys stay valid. */
    public function test_removing_a_row_renumbers_the_rest(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 5)
            ->set('trainings', [
                ['title' => 'Alpha', 'provider' => '', 'year' => ''],
                ['title' => 'Beta',  'provider' => '', 'year' => ''],
                ['title' => 'Gamma', 'provider' => '', 'year' => ''],
            ])
            ->call('removeRow', 'trainings', 0)
            ->assertSet('trainings', fn ($rows) => array_column($rows, 'title') === ['Beta', 'Gamma']
                && array_keys($rows) === [0, 1]);
    }

    public function test_training_year_outside_the_range_is_rejected(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 5)
            ->set('trainings', [['title' => 'Welding', 'provider' => 'TESDA', 'year' => '1900']])
            ->call('nextStep')
            ->assertHasErrors(['trainings.0.year']);
    }

    /** Submitting with no optional rows saves the applicant with zero experience rows. */
    public function test_empty_optional_sections_save_no_rows(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('work_experiences', [])
            ->set('trainings', [])
            ->set('seminars', [])
            ->set('certifications', [])
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->assertHasNoErrors();

        $applicant = Applicant::first();
        $this->assertSame(0, $applicant->workExperiences()->count());
        $this->assertSame(0, $applicant->trainings()->count());
        $this->assertSame(0, $applicant->seminars()->count());
        $this->assertSame(0, $applicant->certifications()->count());
    }

    /** Unknown section names are ignored instead of throwing an error. */
    public function test_unknown_section_name_is_ignored(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('step', 5)
            ->call('addRow', 'bogus')
            ->call('removeRow', 'bogus', 0)
            ->assertHasNoErrors();
    }

    // ─────────────────────────────────────────────────────────────────
    // STEP NAVIGATION
    // ─────────────────────────────────────────────────────────────────

    public function test_going_back_keeps_values_already_entered(): void
    {
        Livewire::test(RegistrationForm::class)
            ->set('last_name', 'Santos')
            ->set('step', 2)
            ->call('prevStep')
            ->assertSet('step', 1)
            ->assertSet('last_name', 'Santos');
    }

    public function test_previous_on_the_first_step_stays_on_step_one(): void
    {
        Livewire::test(RegistrationForm::class)
            ->call('prevStep')
            ->assertSet('step', 1);
    }

    /** Edit from review: Save goes straight back to the review screen. */
    public function test_edit_from_review_returns_to_review_after_save(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->call('goToStep', 1)
            ->assertSet('returnToReview', true)
            ->call('nextStep')
            ->assertSet('step', 6)
            ->assertSet('returnToReview', false);
    }

    /** Edit from review with an invalid value keeps the user on that step and in edit mode. */
    public function test_edit_from_review_with_invalid_value_stays_on_that_step(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->call('goToStep', 1)
            ->set('last_name', '')
            ->call('nextStep')
            ->assertHasErrors(['last_name'])
            ->assertSet('step', 1)
            ->assertSet('returnToReview', true);
    }

    // ─────────────────────────────────────────────────────────────────
    // SUBMISSION EDGE CASES
    // ─────────────────────────────────────────────────────────────────

    /** After a successful submit the password is cleared, so a second submit cannot reuse it. */
    public function test_password_is_cleared_after_successful_submit(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertSet('password', '');
    }

    /**
     * A second submit after success is refused, because the cleared password
     * fails the required rule. No second applicant is created.
     */
    public function test_second_submit_after_success_creates_no_second_applicant(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $component = $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->call('submit');

        $component->assertHasErrors(['password']);
        $this->assertDatabaseCount('applicants', 1);
    }

    /**
     * If anything fails after the applicant row is written, the whole save is
     * rolled back. Nothing is left half-saved.
     */
    public function test_failure_during_save_rolls_back_every_table(): void
    {
        $this->mock(\App\Services\DuplicateDetectionService::class, function ($mock) {
            $mock->shouldReceive('detect')->andThrow(new \RuntimeException('detector down'));
        });

        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
            ->set('oath_given', true)
            ->set('consent_given', true)
            ->call('submit')
            ->assertSet('submitted', false)
            ->assertDispatched('done', error: 'Something went wrong. Please try again.');

        $this->assertDatabaseCount('applicants', 0);
        $this->assertDatabaseCount('education', 0);
        $this->assertDatabaseCount('applicant_skill', 0);
    }

    /**
     * The same contact number is accepted twice. Duplicates are caught in the
     * duplicate review, not blocked at registration. This documents current behaviour.
     */
    public function test_same_contact_number_is_accepted_and_left_for_duplicate_review(): void
    {
        [$municipality, $barangay] = $this->makeGeography();
        [$skill1, $skill2]         = $this->makeSkills();

        foreach (range(1, 2) as $_) {
            $this->fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
                ->set('oath_given', true)
                ->set('consent_given', true)
                ->call('submit')
                ->assertHasNoErrors();
        }

        $this->assertDatabaseCount('applicants', 2);
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC ROUTES
    // ─────────────────────────────────────────────────────────────────

    public function test_guest_can_open_the_registration_form(): void
    {
        $this->get('/')->assertOk();
    }

    /** The old link redirects to the form, so printed QR codes still work. */
    public function test_old_job_portal_link_redirects_to_the_form(): void
    {
        $this->get('/job-portal')->assertRedirect('/');
    }

    public function test_qr_code_endpoint_responds_for_guests(): void
    {
        $this->get('/qr/job-portal')->assertOk();
    }
    
}


