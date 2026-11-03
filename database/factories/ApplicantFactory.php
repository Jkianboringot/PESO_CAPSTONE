<?php
// database/factories/ApplicantFactory.php
// WHY: The Applicant model is the most tested model in the system.
// Nearly every feature test creates applicants. Without a factory,
// tests would need 20+ lines of setup per applicant — burying the
// actual assertion logic under noise.
//
// DESIGN DECISIONS IN THIS FACTORY:
// - reference_id is set to null so the model boot() auto-generates it
//   (tests the actual boot behavior, not a hardcoded value)
// - last_name_metaphone is null for the same reason (boot generates it)
// - birthdate is set to a realistic past date so age calculation works
// - consent_given is true because all valid applicants must have consented
// - is_active defaults to true because active() scope is used everywhere

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicantFactory extends Factory
{
    public function definition(): array
    {
        $sex = fake()->randomElement(['Male', 'Female']);

        // Filipino-realistic names
        $lastNames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia',
                      'Mendoza', 'Torres', 'Ramos', 'Flores', 'Lopez'];
        $maleFirst  = ['Juan', 'Jose', 'Mark', 'Carlo', 'Ryan', 'Joel', 'Rodel'];
        $femaleFirst = ['Maria', 'Ana', 'Rose', 'Karen', 'Christine', 'Lea', 'Nina'];

        return [
            // null so Applicant::boot() auto-generates via uniqid()
            'reference_id'        => null,

            'last_name'           => fake()->randomElement($lastNames),
            'first_name'          => $sex === 'Male'
                                        ? fake()->randomElement($maleFirst)
                                        : fake()->randomElement($femaleFirst),
            'middle_name'         => fake()->optional(0.8)->randomElement(
                                        ['A.', 'B.', 'Santos', 'Reyes', 'Cruz']
                                    ),
            'birthdate'           => fake()->dateTimeBetween('-60 years', '-18 years')
                                           ->format('Y-m-d'),
            'sex'                 => $sex,
            'civil_status'        => fake()->randomElement(
                                        ['Single', 'Married', 'Widowed', 'Separated']
                                    ),
            'contact_number'      => '0917' . fake()->numerify('#######'),
            'email'               => fake()->optional(0.5)->safeEmail(),
            'address'             => 'Purok ' . rand(1, 5),

            // Creates a municipality + barangay automatically unless overridden
            // Override in tests: ->create(['barangay_id' => $knownBarangay->id])
            'barangay_id'         => Barangay::factory(),

            'status'              => 'Pending',
            'is_active'           => true,
            'consent_given'       => true,
            'consent_given_at'    => now(),

            // null so boot() auto-generates from last_name
            'last_name_metaphone' => null,
        ];
    }

    // ── Convenience states ──────────────────────────────────────────────

    /** State: create an inactive (deactivated) applicant */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'status'    => 'Inactive',
        ]);
    }

    /** State: create a flagged applicant (duplicate detected) */
    public function flagged(): static
    {
        return $this->state(fn () => ['status' => 'Flagged']);
    }

    /** State: create a verified applicant */
    public function verified(): static
    {
        return $this->state(fn () => ['status' => 'Verified']);
    }
}
