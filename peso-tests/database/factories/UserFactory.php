<?php
// database/factories/UserFactory.php
// WHY: Tests need users with specific roles and states.
// The factory provides sensible defaults that any test can override.
// Without this, every test would need 10+ lines just to create a user.

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'password'          => Hash::make('password'), // default test password
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
            'role_id'           => null,   // override in test with specific role
            'is_active'         => true,
            'last_login_at'     => null,
        ];
    }

    /**
     * Convenience state: create an unverified user.
     * Used in tests that check email verification flows.
     */
    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /**
     * Convenience state: create an inactive (deactivated) user.
     * Used in tests that check the deactivated account block.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
