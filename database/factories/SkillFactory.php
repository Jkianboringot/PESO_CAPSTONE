<?php
// database/factories/SkillFactory.php
// WHY: Skills gap tests need individual skills. The factory creates
// a skill linked to a category, which can be overridden per-test
// when testing specific category-level analytics.

namespace Database\Factories;

use App\Models\SkillCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class SkillFactory extends Factory
{
    public function definition(): array
    {
        static $skills = [
            'Web Development', 'Data Encoding', 'Graphic Design',
            'Rice Farming', 'Coconut Processing', 'Fishery Operations',
            'Masonry', 'Carpentry', 'Electrical Wiring',
            'Caregiving', 'Community Health Work',
            'Food & Beverage Service', 'Housekeeping', 'Tour Guiding',
            'Bookkeeping', 'Customer Service', 'Office Administration',
            'Driving (Professional)', 'Vessel Operations',
            'Pottery & Ceramics', 'Weaving', 'Painting',
            'Elementary Teaching', 'Secondary Teaching',
            'Automotive Servicing', 'Tailoring & Dressmaking',
            'Beauty Care & Nail Care',
        ];

        return [
            'name'              => fake()->unique()->randomElement($skills),
            'skill_category_id' => SkillCategory::factory(),
        ];
    }
}
