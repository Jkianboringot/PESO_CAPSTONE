<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Services\DuplicateDetectionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds 20 applicants:
 *   - 8 plain, unrelated applicants
 *   - 6 scenario pairs (original + duplicate = 12) built to hit different
 *     combinations of the 3 duplicate criteria
 *
 * `status` is NOT set anywhere here (the column default applies).
 * Flags are created by running the REAL DuplicateDetectionService on each
 * duplicate, so the service itself decides who gets flagged.
 *
 * Reset first so old rows don't add extra matches:
 *   php artisan migrate:fresh --seed
 */
class ApplicantSeeder extends Seeder
{
    // ── Name pools (trimmed; none sound like the scenario surnames) ──────
    private array $lastNames = [
        'Reyes','Cruz','Ocampo','Flores','Ramos','Lopez','Hernandez','Gonzales',
        'Perez','Castillo','Morales','Soriano','Tolentino','Magno','Abad','Aguilar',
        'Arellano','Balmaceda','Baltazar','Basilio','Buenaventura','Cabrales',
        'Capili','Castañeda','Catacutan','Cervantes','Concepcion','Corpuz',
        'Dimaano','Dizon','Estrada','Fajardo','Galvez','Guerrero','Guevara',
        'Ignacio','Lazaro','Luna','Manansala','Navarro','Pangilinan',
        'Resurrecion','Salazar','Tagala','Valdez','Zamora',
    ];

    private array $firstNamesMale = [
        'Juan','Jose','Antonio','Manuel','Francisco','Miguel','Luis','Pedro',
        'Ricardo','Roberto','Eduardo','Fernando','Alfredo','Rodrigo','Noel',
        'Christian','Mark','John','Michael','Ryan','Jerome','Jayson','Julius',
        'Kenneth','Kevin','Lester','Marlon','Nathan','Patrick','Rafael',
    ];

    private array $firstNamesFemale = [
        'Ana','Rosa','Carmen','Luz','Gloria','Pilar','Lourdes','Angelica',
        'Beatriz','Bernadette','Carla','Cecilia','Cherry','Christine','Corazon',
        'Cristina','Daisy','Diana','Eden','Grace','Hazel','Irene','Jasmine',
        'Joyce','Karen','Leah','Marites','Nina','Rhea','Sheila',
    ];

    private array $middleNames = [
        'A.','B.','C.','D.','E.','G.','L.','M.','P.','R.','S.','T.',
        'Santos','Reyes','Cruz','Garcia','Ramos','Flores','Lopez','Morales',
    ];

    private array $streets = [
        'Purok 1','Purok 2','Purok 3','Purok 4','Sitio Bagong Buhay',
        'Sitio Mabini','Sitio Rizal','Sitio Pag-asa','Blk 1 Lot 5','Blk 2 Lot 12',
        'National Highway','Barangay Road','Coastal Road','Near the Church',
        'Near the School','Near the Market',
    ];

    private array $contactPrefixes = [
        '0917','0918','0919','0920','0921','0926','0927','0928','0929','0930',
        '0935','0936','0939','0946','0947','0955','0956','0961','0975','0995',
    ];

    private array $educationLevels = [
        'Elementary','High School','Senior High School',
        'Vocational/Technical','College Undergraduate',
        'College Graduate','Post-Graduate',
    ];

    private array $eduWeights = [15, 25, 15, 20, 10, 12, 3];

    private array $courses = [
        'BS Information Systems','BS Information Technology','BS Computer Science',
        'BS Nursing','BS Education','BS Agriculture','BS Criminology',
        'BS Business Administration','BS Accountancy','BS Tourism',
        'Bachelor of Secondary Education','Bachelor of Elementary Education',
        'NC II Computer Hardware Servicing','NC II Electrical Installation',
        'NC II Welding','NC II Caregiving','NC II Bookkeeping','NC II Beauty Care',
    ];

    private array $schools = [
        'Catanduanes State University','Catanduanes College','Divine Word College',
        'STI College','Bicol University','Partido College',
        'Catanduanes National High School','Virac National High School',
        'Pandan National High School','Bato National High School',
        'TESDA Training Center - Catanduanes',
    ];

    private array $categoryNames = [
        'ICT & Digital Technology','Agricultural & Fisheries',
        'Construction & Engineering','Health & Social Services',
        'Tourism & Hospitality','Business & Administration',
        'Maritime & Transport','Arts, Crafts & Design',
        'Teaching & Education','Trade & Technical Services',
    ];

    // ── Duplicate scenarios ──────────────────────────────────────────────
    // Criteria (flag needs 2+): phonetic last name, same birthdate, contact (last 7 digits)
    private function scenarios(): array
    {
        return [
            'A' => [
                'label'  => 'Phonetic + Birthdate',
                'flag'   => true,
                'original'  => ['last_name' => 'Santos', 'first_name' => 'Maria', 'sex' => 'Female',
                                'birthdate' => '1995-03-14', 'contact_number' => '09171110001'],
                'duplicate' => ['last_name' => 'Santoz', 'first_name' => 'Maria', 'sex' => 'Female',
                                'birthdate' => '1995-03-14', 'contact_number' => '09282220001'],
            ],
            'B' => [
                'label'  => 'Phonetic (typo) + Contact (+63 format)',
                'flag'   => true,
                'original'  => ['last_name' => 'Villanueva', 'first_name' => 'Jose', 'sex' => 'Male',
                                'birthdate' => '1991-06-30', 'contact_number' => '09173334444'],
                'duplicate' => ['last_name' => 'Villanueba', 'first_name' => 'Jose', 'sex' => 'Male',
                                'birthdate' => '1999-12-01', 'contact_number' => '+63 917 333 4444'],
            ],
            'C' => [
                'label'  => 'Birthdate + Contact (different surname)',
                'flag'   => true,
                'original'  => ['last_name' => 'Torres', 'first_name' => 'Carlos', 'sex' => 'Male',
                                'birthdate' => '1988-07-22', 'contact_number' => '09285550123'],
                'duplicate' => ['last_name' => 'Mendoza', 'first_name' => 'Carlos', 'sex' => 'Male',
                                'birthdate' => '1988-07-22', 'contact_number' => '0928-555-0123'],
            ],
            'D' => [
                'label'  => 'All 3 criteria (Jr. suffix)',
                'flag'   => true,
                'original'  => ['last_name' => 'Dela Cruz', 'first_name' => 'Elena', 'sex' => 'Female',
                                'birthdate' => '1985-02-18', 'contact_number' => '09396667777'],
                'duplicate' => ['last_name' => 'Dela Cruz Jr.', 'first_name' => 'Elena', 'sex' => 'Female',
                                'birthdate' => '1985-02-18', 'contact_number' => '+63 939 666 7777'],
            ],
            'E' => [
                'label'  => 'Phonetic ONLY (control, 1 criterion)',
                'flag'   => false,
                'original'  => ['last_name' => 'Bautista', 'first_name' => 'Ramon', 'sex' => 'Male',
                                'birthdate' => '1990-01-10', 'contact_number' => '09451118888'],
                'duplicate' => ['last_name' => 'Bautista', 'first_name' => 'Ramon', 'sex' => 'Male',
                                'birthdate' => '2001-08-09', 'contact_number' => '09561119999'],
            ],
            'F' => [
                'label'  => 'Birthdate ONLY (control, 1 criterion)',
                'flag'   => false,
                'original'  => ['last_name' => 'Aquino', 'first_name' => 'Angela', 'sex' => 'Female',
                                'birthdate' => '1992-11-05', 'contact_number' => '09771230000'],
                'duplicate' => ['last_name' => 'Lim', 'first_name' => 'Angela', 'sex' => 'Female',
                                'birthdate' => '1992-11-05', 'contact_number' => '09882340000'],
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        $this->command->info('Seeding 20 applicants (8 unique + 6 duplicate scenario pairs)...');

        $barangays = DB::table('barangays')
            ->join('municipalities', 'municipalities.id', '=', 'barangays.municipality_id')
            ->select('barangays.id as barangay_id', 'barangays.name as barangay_name')
            ->get();

        $skillMap = DB::table('skills')
            ->join('skill_categories', 'skill_categories.id', '=', 'skills.skill_category_id')
            ->select('skills.id as skill_id', 'skill_categories.name as category_name')
            ->get()
            ->groupBy('category_name');

        $proficiencies = ['Beginner', 'Beginner', 'Beginner', 'Intermediate', 'Intermediate', 'Advanced', 'Expert'];
        $now = Carbon::now();

        $rows = [];
        $duplicateRefByScenario = [];

        // 8 plain, unrelated applicants
        for ($i = 0; $i < 8; $i++) {
            $rows[] = $this->makeRow([], $barangays, $now);
        }

        // 6 scenario pairs: original is older, duplicate is a recent re-registration
        foreach ($this->scenarios() as $key => $s) {
            $original = $this->makeRow(
                $s['original'] + ['created_at' => $now->copy()->subDays(rand(90, 400))->format('Y-m-d H:i:s')],
                $barangays,
                $now
            );
            $duplicate = $this->makeRow(
                $s['duplicate'] + ['created_at' => $now->copy()->subDays(rand(0, 2))->format('Y-m-d H:i:s')],
                $barangays,
                $now
            );
            $rows[] = $original;
            $rows[] = $duplicate;
            $duplicateRefByScenario[$key] = $duplicate['reference_id'];
        }

        // ── Insert applicants ────────────────────────────────────────────
        DB::table('applicants')->insert($rows);

        $ids = DB::table('applicants')
            ->whereIn('reference_id', array_column($rows, 'reference_id'))
            ->pluck('id', 'reference_id');

        // ── Education + skills for every applicant ───────────────────────
        $educationRows = [];
        $skillRows = [];

        foreach ($rows as $row) {
            $applicantId = $ids[$row['reference_id']] ?? null;
            if (!$applicantId) continue;

            $eduLevel = $this->weightedPick($this->educationLevels, $this->eduWeights);
            $course = $school = $yearGrad = null;

            if (!in_array($eduLevel, ['Elementary', 'High School'])) {
                $age = Carbon::parse($row['birthdate'])->age;
                $course = $this->rand() < 0.80 ? $this->pick($this->courses) : null;
                $school = $this->rand() < 0.85 ? $this->pick($this->schools) : null;
                $maxGrad = (int) $now->format('Y');
                $minGrad = max(1990, $maxGrad - $age + 16);
                $yearGrad = $minGrad < $maxGrad ? rand($minGrad, $maxGrad) : $maxGrad;
            }

            $educationRows[] = [
                'applicant_id'   => $applicantId,
                'highest_level'  => $eduLevel,
                'course_program' => $course,
                'school_name'    => $school,
                'year_graduated' => $yearGrad,
                'created_at'     => $row['created_at'],
                'updated_at'     => $row['updated_at'],
            ];

            // 1–3 categories, up to 3 skills each
            $cats = $this->categoryNames;
            shuffle($cats);
            $used = [];
            foreach (array_slice($cats, 0, rand(1, 3)) as $catName) {
                $catSkills = $skillMap->get($catName);
                if (!$catSkills || $catSkills->isEmpty()) continue;

                foreach ($catSkills->shuffle()->take(rand(1, min(3, $catSkills->count()))) as $skill) {
                    if (isset($used[$skill->skill_id])) continue;
                    $used[$skill->skill_id] = true;
                    $skillRows[] = [
                        'applicant_id'      => $applicantId,
                        'skill_id'          => $skill->skill_id,
                        'proficiency_level' => $this->pick($proficiencies),
                    ];
                }
            }
        }

        DB::table('education')->insert($educationRows);
        if ($skillRows) {
            DB::table('applicant_skill')->insert($skillRows);
        }

        // ── Run the REAL duplicate detection on each scenario duplicate ──
        // (the service creates the DuplicateFlag rows and sets status itself)
        $service = app(DuplicateDetectionService::class);
        $report = [];

        foreach ($this->scenarios() as $key => $s) {
            $applicant = Applicant::find($ids[$duplicateRefByScenario[$key]]);
            $flags = $applicant ? $service->detect($applicant) : [];

            $flagged = count($flags) > 0;
            $score = $flagged ? max(array_map(fn($f) => $f->match_score, $flags)) : 0;

            $report[] = [
                $key,
                $s['label'],
                $s['flag'] ? 'Flag' : 'No flag',
                $flagged ? "Flagged (score {$score})" : 'Not flagged',
                $flagged === $s['flag'] ? 'OK' : 'MISMATCH',
            ];
        }

        $this->command->newLine();
        $this->command->table(['#', 'Scenario', 'Expected', 'Result', 'Check'], $report);
        $this->command->info('Done! ' . count($rows) . ' applicants seeded.');
    }

    // ── Row builder ──────────────────────────────────────────────────────
    private function makeRow(array $overrides, $barangays, Carbon $now): array
    {
        $sex = $this->rand() < 0.52 ? 'Female' : 'Male';
        $age = $this->weightedAge();
        $createdAt = $now->copy()->subDays(rand(0, 540))->subHours(rand(0, 23))->format('Y-m-d H:i:s');

        $firstName = $sex === 'Female'
            ? $this->pick($this->firstNamesFemale)
            : $this->pick($this->firstNamesMale);
        $lastName = $this->pick($this->lastNames);
        $barangay = $barangays->random();

        $row = [
            'reference_id'        => 'PESO-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 13)),
            'last_name'           => $lastName,
            'first_name'          => $firstName,
            'middle_name'         => $this->rand() < 0.85 ? $this->pick($this->middleNames) : null,
            'birthdate'           => $now->copy()->subYears($age)->subDays(rand(0, 364))->format('Y-m-d'),
            'sex'                 => $sex,
            'civil_status'        => $this->weightedPick(['Single', 'Married', 'Widowed', 'Separated'], [55, 35, 5, 5]),
            'contact_number'      => $this->pick($this->contactPrefixes) . rand(1000000, 9999999),
            'email'               => $this->rand() < 0.45
                ? strtolower($firstName) . '.' . strtolower(str_replace(' ', '', $lastName)) . rand(1, 999) . '@' . $this->pick(['gmail.com', 'yahoo.com', 'outlook.com'])
                : null,
            'address'             => $this->pick($this->streets) . ', ' . $barangay->barangay_name,
            'barangay_id'         => $barangay->barangay_id,
            'is_active'           => true,
            'consent_given'       => true,
            'consent_given_at'    => $createdAt,
            'last_name_metaphone' => '',
            'created_at'          => $createdAt,
            'updated_at'          => $createdAt,
        ];

        $row = array_merge($row, $overrides);

        // keep derived fields in sync with any overrides
        $row['last_name_metaphone'] = metaphone($row['last_name']);
        $row['consent_given_at']    = $row['created_at'];
        $row['updated_at']          = $row['created_at'];

        return $row;
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    private function rand(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function pick(array $arr): mixed
    {
        return $arr[array_rand($arr)];
    }

    private function weightedPick(array $items, array $weights): mixed
    {
        $total = array_sum($weights);
        $roll = mt_rand(1, $total);
        $cumulative = 0;
        foreach ($items as $i => $item) {
            $cumulative += $weights[$i];
            if ($roll <= $cumulative) return $item;
        }
        return $items[array_key_last($items)];
    }

    private function weightedAge(): int
    {
        $r = $this->rand() * 100;
        if ($r < 20) return rand(18, 24);
        if ($r < 55) return rand(25, 35);
        if ($r < 80) return rand(36, 45);
        if ($r < 95) return rand(46, 55);
        return rand(56, 65);
    }
}