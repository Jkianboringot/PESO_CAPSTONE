<?php
// database/factories/SkillCategoryFactory.php
// WHY: Skills gap and analytics tests need skill categories to exist.
// Tests use firstOrCreate() in most cases, but when a factory is
// needed for ->for() relationships, this provides a clean default.

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SkillCategoryFactory extends Factory
{
    public function definition(): array
    {
        static $categories = [
            'ICT & Digital Technology',
            'Agricultural & Fisheries',
            'Construction & Engineering',
            'Health & Social Services',
            'Tourism & Hospitality',
            'Business & Administration',
            'Maritime & Transport',
            'Arts, Crafts & Design',
            'Teaching & Education',
            'Trade & Technical Services',
        ];

        return [
            'name'      => fake()->unique()->randomElement($categories),
            'pqf_level' => fake()->randomElement(['NC I', 'NC II', 'NC III', 'NC IV']),
        ];
    }
}
