<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\RegistrationForm;
use App\Models\Municipality;
use App\Models\Barangay;
use App\Models\Skill;

class RegistrationDuplicateTest extends TestCase
{
    // use RefreshDatabase;

    protected $seed = true;

    public function test_duplicate_registration_is_detected()
    {
        $municipality = Municipality::first();

        $barangay = Barangay::where(
            'municipality_id',
            $municipality->id
        )->first();

        $skill = Skill::first();

        $data = [
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'birthdate' => '2000-01-01',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'contact_number' => '09123456789',
            'address' => 'Test Address',
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'highest_level' => 'College Graduate',
            'selected_skills' => [$skill->id],
            'skill_proficiencies' => [
                $skill->id => 'Advanced',
            ],
            'consent_given' => true,
            'step' => 5,
        ];

        // First submit
        Livewire::test(RegistrationForm::class)
            ->set($data)
            ->call('submit')
            ->assertSet('submitted', true);

        // Second identical submit
        Livewire::test(RegistrationForm::class)
            ->set($data)
            ->call('submit');

        $this->assertDatabaseCount('applicants', 2);
    }
}