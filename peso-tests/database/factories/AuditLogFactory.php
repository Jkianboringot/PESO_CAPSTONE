<?php
// database/factories/AuditLogFactory.php
// WHY: Some tests need to assert that audit logs were NOT created,
// or need pre-existing audit logs to test pagination/display.
// The factory provides a baseline — individual tests override fields.

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'action'     => fake()->randomElement([
                'APPLICANT_CREATED', 'APPLICANT_UPDATED',
                'APPLICANT_DEACTIVATED', 'USER_LOGIN', 'USER_LOGOUT',
                'DUPLICATE_RESOLVED_MERGED', 'REPORT_DOWNLOADED',
            ]),
            'model_type' => 'Applicant',
            'model_id'   => rand(1, 100),
            'changes'    => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /** Guest action (no logged-in user — resident registration) */
    public function guest(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }
}
