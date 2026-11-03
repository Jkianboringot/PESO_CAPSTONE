<?php
// database/factories/EducationFactory.php
// WHY: Analytics tests and export tests need applicants with education
// records. The factory creates one linked to an applicant automatically.
// The UNIQUE constraint on applicant_id means only one per applicant —
// the factory respects this by accepting an applicant_id override.

namespace Database\Factories;

use App\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EducationFactory extends Factory
{
    public function definition(): array
    {
        $levels = [
            'Elementary', 'High School', 'Senior High School',
            'Vocational/Technical', 'College Undergraduate',
            'College Graduate', 'Post-Graduate',
        ];

        $courses = [
            'BS Information Systems', 'BS Nursing', 'BS Education',
            'BS Agriculture', 'BS Business Administration', 'BS Engineering',
            'NC II Caregiving', 'NC II Computer Hardware Servicing',
        ];

        return [
            'applicant_id'  => Applicant::factory(),
            'highest_level' => fake()->randomElement($levels),
            'course_program'=> fake()->optional(0.75)->randomElement($courses),
            'school_name'   => fake()->optional(0.80)->company() . ' University',
            'year_graduated'=> fake()->optional(0.70)->numberBetween(1995, 2024),
        ];
    }
}
