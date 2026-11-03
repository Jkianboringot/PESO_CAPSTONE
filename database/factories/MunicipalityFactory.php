<?php
// database/factories/MunicipalityFactory.php
// WHY: Almost every feature test needs geographic data because
// applicants require a barangay FK which requires a municipality FK.
// This factory makes geography setup a one-liner in tests.

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MunicipalityFactory extends Factory
{
    public function definition(): array
    {
        // Use real Catanduanes municipality names so test data looks realistic
        static $municipalities = [
            'Virac', 'Pandan', 'Caramoran', 'San Andres', 'Bato',
            'Viga', 'Bagamanoc', 'Baras', 'Gigmoto', 'Panganiban', 'San Miguel',
        ];

        return [
            'name'     => fake()->unique()->randomElement($municipalities),
            'province' => 'Catanduanes',
        ];
    }
}
