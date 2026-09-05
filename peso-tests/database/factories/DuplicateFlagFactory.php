<?php
// database/factories/DuplicateFlagFactory.php
// WHY: DuplicateReview tests need pre-existing flag records.
// Creating them manually requires creating two applicants and
// a flag with the right FK relationships — 15+ lines.
// The factory collapses that to one line.
//
// IMPORTANT: applicant_id_new and applicant_id_existing must
// be DIFFERENT applicants. The factory creates two separate
// applicants by default. Override in tests when you need
// specific applicants.

namespace Database\Factories;

use App\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DuplicateFlagFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Two separate applicants — do NOT use the same factory instance
            'applicant_id_new'      => Applicant::factory(),
            'applicant_id_existing' => Applicant::factory(),

            // Default: phonetic + birthdate match (score 2)
            'matched_phonetic'      => true,
            'matched_birthdate'     => true,
            'matched_contact'       => false,
            'match_score'           => 2,

            'resolution_status'     => 'Pending',
            'resolved_by'           => null,
            'resolution_notes'      => null,
            'resolved_at'           => null,
        ];
    }

    // ── Convenience states ──────────────────────────────────────────────

    /** Score 3: all three criteria matched */
    public function allMatch(): static
    {
        return $this->state(fn () => [
            'matched_phonetic'  => true,
            'matched_birthdate' => true,
            'matched_contact'   => true,
            'match_score'       => 3,
        ]);
    }

    /** Already resolved as Merged */
    public function resolved(string $status = 'Merged'): static
    {
        return $this->state(fn () => [
            'resolution_status' => $status,
            'resolved_at'       => now(),
        ]);
    }
}
