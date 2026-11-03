<?php
// database/factories/RoleFactory.php
// WHY: Tests need roles to assign to users. Rather than hardcoding
// role IDs (which change per test run), we use factories so each
// test creates its own role with known slugs.

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    public function definition(): array
    {
        // Random slug in case multiple roles are created in one test
        $name = fake()->unique()->randomElement(['Staff', 'Administrator', 'Supervisor']);
        return [
            'name' => $name,
            'slug' => strtolower($name),
        ];
    }

    /** Quickly create a staff role */
    public function staff(): static
    {
        return $this->state(fn () => ['name' => 'Staff', 'slug' => 'staff']);
    }

    /** Quickly create an admin role */
    public function admin(): static
    {
        return $this->state(fn () => ['name' => 'Administrator', 'slug' => 'admin']);
    }
}
