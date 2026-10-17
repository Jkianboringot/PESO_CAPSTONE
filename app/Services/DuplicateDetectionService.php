<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\DuplicateFlag;
use Illuminate\Support\Str;

class DuplicateDetectionService {

    /**
     * Run composite duplicate detection against all active applicants.
     * Returns array of DuplicateFlag records created (empty if none).
     */
    public function detect(Applicant $newApplicant): array {
        $flags = [];

        // Compare against every active applicant except the new one (can't compare it to itself)
        $candidates = Applicant::active()
            ->where('id', '!=', $newApplicant->id)
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

            // ── Criterion 3: Contact number match ──────────────────
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

        // Nothing usable to compare (blank, or no Latin letters): never a match.
        // Without this, two blanks would be "equal" and count as a match.
        if ($n1 === '' || $n2 === '') return false;

        // Exact match after normalization
        if ($n1 === $n2) return true;

        // Metaphone: same "sound" (handles sound-alikes like Reyes/Reyez)
        $m1 = metaphone($n1);
        $m2 = metaphone($n2);
        if ($m1 !== '' && $m1 === $m2) return true;

        // Typo tolerance: number of single-letter edits allowed depends on name length.
        // Short names must be (almost) exact; long names may have a slip or two.
        $len     = max(strlen($n1), strlen($n2));
        $allowed = $len >= 9 ? 2 : ($len >= 5 ? 1 : 0);

        return levenshtein($n1, $n2) <= $allowed;
    }

    private function normalizeName(string $name): string {
        // Convert accents instead of deleting them: Peña → pena, Muñoz → munoz
        $name = Str::ascii(mb_strtolower(trim($name)));

        // Drop suffixes whether or not they have a period: "Santos Jr", "Santos Jr.", "Santos Sr."
        $name = preg_replace('/\b(jr|sr|ii|iii)\b\.?/', '', $name);

        // Remove anything that is not a letter or space (hyphens, apostrophes, digits)
        $name = preg_replace('/[^a-z\s]/', '', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    // ── Criterion 3: Contact number matching ────────────────────────
    private function contactMatch(?string $c1, ?string $c2): bool {
        $d1 = $this->nationalNumber($c1);
        $d2 = $this->nationalNumber($c2);

        // Blank / non-numeric numbers never match each other
        if ($d1 === '' || $d2 === '') return false;

        return $d1 === $d2;
    }

    // Reduce any format to the bare national number so these all compare equal:
    // "+63 917 123 4567", "63917-123-4567", "0917 123 4567"  →  "9171234567"
    private function nationalNumber(?string $contact): string {
        $digits = preg_replace('/\D/', '', (string) $contact);

        if (str_starts_with($digits, '63')) {
            $digits = substr($digits, 2);   // country code
        }

        return ltrim($digits, '0');          // local leading zero
    }
}