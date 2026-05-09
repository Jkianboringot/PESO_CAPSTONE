<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\DuplicateFlag;

class DuplicateDetectionService {

    /**
     * Run composite duplicate detection against all active applicants.
     * Returns array of DuplicateFlag records created (empty if none).
     */
    public function detect(Applicant $newApplicant): array {
        $flags = [];

        // Only compare against active, non-flagged applicants excluding self
        $candidates = Applicant::active()
            ->where('id', '!=', $newApplicant->id)// REMOVE-LATER I DONT SEE THE USE OF THIS
            ->select(['id', 'last_name', 'last_name_metaphone', 'birthdate', 'contact_number'])
            ->get();

        foreach ($candidates as $existing) {
            $score   = 0;
            $phonetic = false;
            $bdate   = false;
            $contact = false;

            // ── Criterion 1: Phonetic last name match ──────────────
            if ($this->phoneticMatch($newApplicant->last_name, $existing->last_name)) {
                $score++; $phonetic = true;
            }

            // ── Criterion 2: Exact birthdate match ─────────────────
            if ($newApplicant->birthdate->isSameDay($existing->birthdate)) {
                $score++; $bdate = true;
            }

            // ── Criterion 3: Contact number partial match ──────────
            if ($this->contactMatch($newApplicant->contact_number, $existing->contact_number)) {
                $score++; $contact = true;
            }

            // Flag if 2 or more criteria satisfied
            if ($score >= 2) {
                $flag = DuplicateFlag::create([
                    'applicant_id_new'      => $newApplicant->id,
                    'applicant_id_existing' => $existing->id,
                    'matched_phonetic'      => $phonetic,
                    'matched_birthdate'     => $bdate,
                    'matched_contact'       => $contact,
                    'match_score'           => $score,
                    'resolution_status'     => 'Pending',
                ]);

                // Update applicant status to Flagged
                $newApplicant->update(['status' => 'Flagged']);
                $flags[] = $flag;
            }
        }

        return $flags;
    }

    // ── Criterion 1: Phonetic matching ──────────────────────────────
    private function phoneticMatch(string $name1, string $name2): bool {
        $n1 = $this->normalizeName($name1);
        $n2 = $this->normalizeName($name2);

        // Exact match after normalization
        if ($n1 === $n2) return true;

        // Metaphone phonetic match (handles common sound-alikes)
        $m1 = metaphone($n1);
        $m2 = metaphone($n2);
        if ($m1 !== '' && $m1 === $m2) return true;

        // Fallback: string similarity >= 85% (handles minor typos)
        similar_text($n1, $n2, $pct); // REVIEW
        return $pct >= 85.0;
    }

    private function normalizeName(string $name): string {  // REVIEW
        $name = mb_strtolower(trim($name));
        // Expand Filipino name abbreviations
        $name = preg_replace('/\bma\.\s*/u', 'maria ', $name);
        $name = preg_replace('/\bj\.\s*/u', 'jose ', $name);
        $name = preg_replace('/\bj[rl]\.\s*/u', '', $name); // Jr/Sr
        // Remove non-alpha characters
        $name = preg_replace('/[^a-z\s]/', '', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    // ── Criterion 3: Contact number matching ────────────────────────
    private function contactMatch(string $c1, string $c2): bool {
        // Extract digits only
        $d1 = preg_replace('/\D/', '', $c1);
        $d2 = preg_replace('/\D/', '', $c2);

        if (strlen($d1) < 7 || strlen($d2) < 7) return $d1 === $d2; // REVIEW

        // Compare last 7 digits (handles +63, 0, and 63 prefixes)
        return substr($d1, -7) === substr($d2, -7);
    }
}
