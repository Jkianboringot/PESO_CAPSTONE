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
}