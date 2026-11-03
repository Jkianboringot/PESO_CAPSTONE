<?php
// database/factories/BarangayFactory.php
// WHY: Barangays are the lowest geographic unit used in analytics.
// Tests that verify barangay-level filtering need real barangay records
// linked to a municipality. Using ->for($municipality) in tests
// keeps the relationship clean without manual ID assignment.

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

class BarangayFactory extends Factory
{
    public function definition(): array
    {
        static $barangays = [
            'San Jose', 'San Pedro', 'San Isidro', 'Santa Cruz',
            'Salvacion', 'Mabini', 'Del Rosario', 'Buenavista',
            'Lubas', 'Libod', 'Poblacion', 'Agban', 'Gogon',
            'Tagas', 'Rawis', 'Antipolo', 'Bigaa', 'Jupi',
        ];

        return [
            // municipality_id is required — must be provided via ->for()
            // or by passing it directly: BarangayFactory::new(['municipality_id' => $id])
            'municipality_id' => Municipality::factory(),
            'name'            => fake()->unique()->randomElement($barangays),
        ];
    }
}
