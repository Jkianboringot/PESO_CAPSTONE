<?php

namespace Tests\Feature\Concerns;

use App\Livewire\RegistrationForm;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Skill;
use App\Models\SkillCategory;
use Livewire\Livewire;

/**
 * Shared setup for the registration tests.
 *
 * Skill and SkillCategory have no factory in this project (that was the
 * "Call to undefined method SkillCategory::factory()" failure), so they are
 * created with forceCreate(). If a migration complains that some column
 * "doesn't have a default value", add that column to the arrays below.
 */
trait RegistersApplicants
{
    protected string $testPassword = 'Secret123!';

    protected function makeGeography(): array
    {
        $municipality = Municipality::factory()->create(['name' => 'Virac']);
        $barangay     = Barangay::factory()->create([
            'municipality_id' => $municipality->id,
            'name'            => 'San Jose',
        ]);

        return [$municipality, $barangay];
    }

    protected function makeSkills(): array
    {
        $category = SkillCategory::forceCreate(['name' => 'ICT & Digital Technology']);

        $skill1 = Skill::forceCreate([
            'skill_category_id' => $category->id,
            'name'              => 'Web Development',
        ]);
        $skill2 = Skill::forceCreate([
            'skill_category_id' => $category->id,
            'name'              => 'Data Encoding',
        ]);

        return [$skill1, $skill2];
    }

    /**
     * Walks the real form through steps 1-5 with nextStep() and returns the
     * component sitting on step 6 (review + oath + consent), ready for submit().
     */
    protected function fillFormUpToReview($municipality, $barangay, $skill1, $skill2)
    {
        return Livewire::test(RegistrationForm::class)
            // Step 1: personal + password
            ->set('last_name', 'Santos')
            ->set('first_name', 'Juan')
            ->set('middle_name', 'Cruz')
            ->set('birthdate', '1995-06-15')
            ->set('sex', 'Male')
            ->set('civil_status', 'Single')
            ->set('contact_number', '09171234567')
            ->set('email', 'juan.santos@gmail.com')
            ->set('password', $this->testPassword)
            ->set('password_confirmation', $this->testPassword)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 2)

            // Step 2: address
            ->set('address', 'Purok 1, San Jose')
            ->set('municipality_id', $municipality->id)
            ->set('barangay_id', $barangay->id)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 3)

            // Step 3: education
            ->set('highest_level', 'College Graduate')
            ->set('course_program', 'BS Information Systems')
            ->set('school_name', 'Catanduanes State University')
            ->set('year_graduated', '2018')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 4)

            // Step 4: skills
            ->set('selected_skills', [$skill1->id, $skill2->id])
            ->set("skill_proficiencies.{$skill1->id}", 'Intermediate')
            ->set("skill_proficiencies.{$skill2->id}", 'Beginner')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 5)

            // Step 5: experience (optional, one job added to exercise the rows)
            ->call('addRow', 'work_experiences')
            ->set('work_experiences.0.company_name', 'Virac Public Market')
            ->set('work_experiences.0.position', 'Cashier')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 6);
    }
}