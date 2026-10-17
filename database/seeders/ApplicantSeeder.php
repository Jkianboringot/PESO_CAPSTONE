<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Services\DuplicateDetectionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ApplicantSeeder extends Seeder
{
    private string $passwordHash = '';

    private array $lastNames = [
        'Reyes', 'Cruz', 'Ocampo', 'Flores', 'Ramos', 'Lopez',
        'Hernandez', 'Gonzales', 'Perez', 'Castillo', 'Morales',
        'Soriano', 'Tolentino', 'Magno', 'Abad', 'Aguilar',
        'Arellano', 'Balmaceda', 'Baltazar', 'Basilio',
        'Buenaventura', 'Cabrales', 'Capili', 'Castañeda',
        'Catacutan', 'Cervantes', 'Concepcion', 'Corpuz',
        'Dimaano', 'Dizon', 'Estrada', 'Fajardo', 'Galvez',
        'Guerrero', 'Guevara', 'Ignacio', 'Lazaro', 'Luna',
        'Manansala', 'Navarro', 'Pangilinan', 'Resurrecion',
        'Salazar', 'Tagala', 'Valdez', 'Zamora',
    ];

    private array $firstNamesMale = [
        'Juan', 'Jose', 'Antonio', 'Manuel', 'Francisco',
        'Miguel', 'Luis', 'Pedro', 'Ricardo', 'Roberto',
        'Eduardo', 'Fernando', 'Alfredo', 'Rodrigo', 'Noel',
        'Christian', 'Mark', 'John', 'Michael', 'Ryan',
        'Jerome', 'Jayson', 'Julius', 'Kenneth', 'Kevin',
        'Lester', 'Marlon', 'Nathan', 'Patrick', 'Rafael',
    ];

    private array $firstNamesFemale = [
        'Ana', 'Rosa', 'Carmen', 'Luz', 'Gloria', 'Pilar',
        'Lourdes', 'Angelica', 'Beatriz', 'Bernadette', 'Carla',
        'Cecilia', 'Cherry', 'Christine', 'Corazon', 'Cristina',
        'Daisy', 'Diana', 'Eden', 'Grace', 'Hazel', 'Irene',
        'Jasmine', 'Joyce', 'Karen', 'Leah', 'Marites', 'Nina',
        'Rhea', 'Sheila',
    ];

    private array $middleNames = [
        'A.', 'B.', 'C.', 'D.', 'E.', 'G.', 'L.', 'M.', 'P.',
        'R.', 'S.', 'T.', 'Santos', 'Reyes', 'Cruz', 'Garcia',
        'Ramos', 'Flores', 'Lopez', 'Morales',
    ];

    private array $streets = [
        'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4',
        'Sitio Bagong Buhay', 'Sitio Mabini', 'Sitio Rizal',
        'Sitio Pag-asa', 'Blk 1 Lot 5', 'Blk 2 Lot 12',
        'National Highway', 'Barangay Road', 'Coastal Road',
        'Near the Church', 'Near the School', 'Near the Market',
    ];

    private array $contactPrefixes = [
        '0917', '0918', '0919', '0920', '0921', '0926',
        '0927', '0928', '0929', '0930', '0935', '0936',
        '0939', '0946', '0947', '0955', '0956', '0961',
        '0975', '0995',
    ];

    private array $educationLevels = [
        'Elementary',
        'High School',
        'Senior High School',
        'Vocational/Technical',
        'College Undergraduate',
        'College Graduate',
        'Post-Graduate',
    ];

    private array $eduWeights = [15, 25, 15, 20, 10, 12, 3];

    private array $courses = [
        'BS Information Systems',
        'BS Information Technology',
        'BS Computer Science',
        'BS Nursing',
        'BS Education',
        'BS Agriculture',
        'BS Criminology',
        'BS Business Administration',
        'BS Accountancy',
        'BS Tourism',
        'Bachelor of Secondary Education',
        'Bachelor of Elementary Education',
        'NC II Computer Hardware Servicing',
        'NC II Electrical Installation',
        'NC II Welding',
        'NC II Caregiving',
        'NC II Bookkeeping',
        'NC II Beauty Care',
    ];

    private array $schools = [
        'Catanduanes State University',
        'Catanduanes College',
        'Divine Word College',
        'STI College',
        'Bicol University',
        'Partido College',
        'Catanduanes National High School',
        'Virac National High School',
        'Pandan National High School',
        'Bato National High School',
        'TESDA Training Center - Catanduanes',
    ];

    private array $categoryNames = [
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

    /*
    |--------------------------------------------------------------------------
    | Sample data for new applicant-related tables
    |--------------------------------------------------------------------------
    */

    private array $companies = [
        'ABC Trading',
        'Virac General Hospital',
        'Bicol Construction Services',
        'Catanduanes Agricultural Supply',
        'Local Government Office',
        'Community Cooperative',
        'Tech Solutions Philippines',
        'JMR Retail Store',
        'Bicol Food Services',
        'Independent Contractor',
    ];

    private array $positions = [
        'Office Staff',
        'Sales Associate',
        'Computer Technician',
        'Administrative Assistant',
        'Farm Worker',
        'Customer Service Representative',
        'Bookkeeper',
        'Construction Worker',
        'Teacher Assistant',
        'IT Support Staff',
        'Cashier',
        'Warehouse Assistant',
    ];

    private array $workDescriptions = [
        'Handled daily operations and maintained records.',
        'Assisted customers and provided general support.',
        'Prepared reports and organized company documents.',
        'Maintained equipment and ensured workplace safety.',
        'Coordinated with team members to complete assigned tasks.',
        'Managed inventory and monitored supplies.',
        'Assisted with administrative and clerical duties.',
    ];

    private array $trainingTitles = [
        'Basic Computer Literacy',
        'Occupational Safety and Health',
        'Basic First Aid Training',
        'Customer Service Training',
        'Bookkeeping NC III Training',
        'Computer Systems Servicing NC II',
        'Food Safety and Sanitation',
        'Entrepreneurship Development',
        'Basic Welding Training',
        'Digital Marketing Fundamentals',
    ];

    private array $trainingProviders = [
        'TESDA',
        'DOLE',
        'Catanduanes State University',
        'Bicol University',
        'Local Government Unit',
        'Private Training Center',
    ];

    private array $seminarTitles = [
        'Career Development Seminar',
        'Job Readiness and Interview Skills',
        'Workplace Communication',
        'Financial Literacy Seminar',
        'Entrepreneurship and Small Business',
        'Digital Skills for Employment',
        'Workplace Safety Awareness',
        'Leadership and Teamwork',
    ];

    private array $seminarOrganizers = [
        'PESO',
        'DOLE',
        'TESDA',
        'Catanduanes State University',
        'Local Government Unit',
        'Youth Development Office',
    ];

    private array $certifications = [
        [
            'name' => 'Computer Systems Servicing NC II',
            'body' => 'TESDA',
        ],
        [
            'name' => 'Electrical Installation and Maintenance NC II',
            'body' => 'TESDA',
        ],
        [
            'name' => 'Welding NC II',
            'body' => 'TESDA',
        ],
        [
            'name' => 'Bookkeeping NC III',
            'body' => 'TESDA',
        ],
        [
            'name' => 'Caregiving NC II',
            'body' => 'TESDA',
        ],
        [
            'name' => 'First Aid Certification',
            'body' => 'Red Cross',
        ],
        [
            'name' => 'Food and Beverage Services NC II',
            'body' => 'TESDA',
        ],
        [
            'name' => 'Computer Programming NC III',
            'body' => 'TESDA',
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Duplicate-detection scenarios
    |--------------------------------------------------------------------------
    */

    private function scenarios(): array
    {
        return [
            'A' => [
                'label' => 'Phonetic + Birthdate',
                'flag' => true,
                'original' => [
                    'last_name' => 'Santos',
                    'first_name' => 'Maria',
                    'sex' => 'Female',
                    'birthdate' => '1995-03-14',
                    'contact_number' => '09171110001',
                ],
                'duplicate' => [
                    'last_name' => 'Santoz',
                    'first_name' => 'Maria',
                    'sex' => 'Female',
                    'birthdate' => '1995-03-14',
                    'contact_number' => '09282220001',
                ],
            ],
            'B' => [
                'label' => 'Phonetic typo + Contact',
                'flag' => true,
                'original' => [
                    'last_name' => 'Villanueva',
                    'first_name' => 'Jose',
                    'sex' => 'Male',
                    'birthdate' => '1991-06-30',
                    'contact_number' => '09173334444',
                ],
                'duplicate' => [
                    'last_name' => 'Villanueba',
                    'first_name' => 'Jose',
                    'sex' => 'Male',
                    'birthdate' => '1999-12-01',
                    'contact_number' => '+63 917 333 4444',
                ],
            ],
            'C' => [
                'label' => 'Birthdate + Contact',
                'flag' => true,
                'original' => [
                    'last_name' => 'Torres',
                    'first_name' => 'Carlos',
                    'sex' => 'Male',
                    'birthdate' => '1988-07-22',
                    'contact_number' => '09285550123',
                ],
                'duplicate' => [
                    'last_name' => 'Mendoza',
                    'first_name' => 'Carlos',
                    'sex' => 'Male',
                    'birthdate' => '1988-07-22',
                    'contact_number' => '0928-555-0123',
                ],
            ],
            'D' => [
                'label' => 'All 3 criteria',
                'flag' => true,
                'original' => [
                    'last_name' => 'Dela Cruz',
                    'first_name' => 'Elena',
                    'sex' => 'Female',
                    'birthdate' => '1985-02-18',
                    'contact_number' => '09396667777',
                ],
                'duplicate' => [
                    'last_name' => 'Dela Cruz Jr.',
                    'first_name' => 'Elena',
                    'sex' => 'Female',
                    'birthdate' => '1985-02-18',
                    'contact_number' => '+63 939 666 7777',
                ],
            ],
            'E' => [
                'label' => 'Phonetic only',
                'flag' => false,
                'original' => [
                    'last_name' => 'Bautista',
                    'first_name' => 'Ramon',
                    'sex' => 'Male',
                    'birthdate' => '1990-01-10',
                    'contact_number' => '09451118888',
                ],
                'duplicate' => [
                    'last_name' => 'Bautista',
                    'first_name' => 'Ramon',
                    'sex' => 'Male',
                    'birthdate' => '2001-08-09',
                    'contact_number' => '09561119999',
                ],
            ],
            'F' => [
                'label' => 'Birthdate only',
                'flag' => false,
                'original' => [
                    'last_name' => 'Aquino',
                    'first_name' => 'Angela',
                    'sex' => 'Female',
                    'birthdate' => '1992-11-05',
                    'contact_number' => '09771230000',
                ],
                'duplicate' => [
                    'last_name' => 'Lim',
                    'first_name' => 'Angela',
                    'sex' => 'Female',
                    'birthdate' => '1992-11-05',
                    'contact_number' => '09882340000',
                ],
            ],
        ];
    }

    public function run(): void
    {
        $this->passwordHash = Hash::make('123');

        $this->command->info(
            'Seeding 20 applicants (8 unique + 6 duplicate scenario pairs)...'
        );

        $barangays = DB::table('barangays')
            ->join(
                'municipalities',
                'municipalities.id',
                '=',
                'barangays.municipality_id'
            )
            ->select(
                'barangays.id as barangay_id',
                'barangays.name as barangay_name'
            )
            ->get();

        if ($barangays->isEmpty()) {
            throw new \RuntimeException(
                'No barangays found. Seed municipalities and barangays first.'
            );
        }

        $skillMap = DB::table('skills')
            ->join(
                'skill_categories',
                'skill_categories.id',
                '=',
                'skills.skill_category_id'
            )
            ->select(
                'skills.id as skill_id',
                'skill_categories.name as category_name'
            )
            ->get()
            ->groupBy('category_name');

        $proficiencies = [
            'Beginner',
            'Beginner',
            'Beginner',
            'Intermediate',
            'Intermediate',
            'Advanced',
            'Expert',
        ];

        $now = Carbon::now();
        $rows = [];
        $duplicateRefByScenario = [];

        // Eight unrelated applicants.
        for ($i = 0; $i < 8; $i++) {
            $rows[] = $this->makeRow([], $barangays, $now);
        }

        // Six original/duplicate pairs.
        foreach ($this->scenarios() as $key => $scenario) {
            $original = $this->makeRow(
                $scenario['original'] + [
                    'created_at' => $now->copy()
                        ->subDays(rand(90, 400))
                        ->format('Y-m-d H:i:s'),
                ],
                $barangays,
                $now
            );

            $duplicate = $this->makeRow(
                $scenario['duplicate'] + [
                    'created_at' => $now->copy()
                        ->subDays(rand(0, 2))
                        ->format('Y-m-d H:i:s'),
                ],
                $barangays,
                $now
            );

            $rows[] = $original;
            $rows[] = $duplicate;

            $duplicateRefByScenario[$key] = $duplicate['reference_id'];
        }

        // Insert applicants.
        DB::table('applicants')->insert($rows);

        $ids = DB::table('applicants')
            ->whereIn('reference_id', array_column($rows, 'reference_id'))
            ->pluck('id', 'reference_id');

        // Assign the applicant role.
        $role = Role::findOrCreate('applicant', 'applicant');

        $roleRows = [];

        foreach ($ids as $applicantId) {
            $roleRows[] = [
                'role_id' => $role->id,
                'model_type' => Applicant::class,
                'model_id' => $applicantId,
            ];
        }

        if ($roleRows) {
            DB::table('model_has_roles')->insert($roleRows);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Education and skills.
        $educationRows = [];
        $skillRows = [];

        foreach ($rows as $row) {
            $applicantId = $ids[$row['reference_id']] ?? null;

            if (!$applicantId) {
                continue;
            }

            $eduLevel = $this->weightedPick(
                $this->educationLevels,
                $this->eduWeights
            );

            $course = null;
            $school = null;
            $yearGrad = null;

            if (!in_array($eduLevel, ['Elementary', 'High School'])) {
                $age = Carbon::parse($row['birthdate'])->age;

                $course = $this->rand() < 0.80
                    ? $this->pick($this->courses)
                    : null;

                $school = $this->rand() < 0.85
                    ? $this->pick($this->schools)
                    : null;

                $maxGrad = (int) $now->format('Y');
                $minGrad = max(1990, $maxGrad - $age + 16);

                $yearGrad = $minGrad < $maxGrad
                    ? rand($minGrad, $maxGrad)
                    : $maxGrad;
            }

            $educationRows[] = [
                'applicant_id' => $applicantId,
                'highest_level' => $eduLevel,
                'course_program' => $course,
                'school_name' => $school,
                'year_graduated' => $yearGrad,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];

            $categories = $this->categoryNames;
            shuffle($categories);
            $used = [];

            foreach (array_slice($categories, 0, rand(1, 3)) as $category) {
                $categorySkills = $skillMap->get($category);

                if (!$categorySkills || $categorySkills->isEmpty()) {
                    continue;
                }

                $maxSkills = min(3, $categorySkills->count());

                foreach (
                    $categorySkills->shuffle()->take(rand(1, $maxSkills))
                    as $skill
                ) {
                    if (isset($used[$skill->skill_id])) {
                        continue;
                    }

                    $used[$skill->skill_id] = true;

                    $skillRows[] = [
                        'applicant_id' => $applicantId,
                        'skill_id' => $skill->skill_id,
                        'proficiency_level' => $this->pick($proficiencies),
                    ];
                }
            }
        }

        if ($educationRows) {
            DB::table('education')->insert($educationRows);
        }

        if ($skillRows) {
            DB::table('applicant_skill')->insert($skillRows);
        }

        // Work experience, training, seminar, and certification records.
        $workExperienceRows = [];
        $trainingRows = [];
        $seminarRows = [];
        $certificationRows = [];

        $currentYear = (int) $now->format('Y');

        foreach ($rows as $row) {
            $applicantId = $ids[$row['reference_id']] ?? null;

            if (!$applicantId) {
                continue;
            }

            $createdAt = $row['created_at'];
            $updatedAt = $row['updated_at'];

            // One to three work experiences per applicant.
            $workCount = rand(1, 3);

            for ($i = 0; $i < $workCount; $i++) {
                $workExperienceRows[] = [
                    'applicant_id' => $applicantId,
                    'company_name' => $this->pick($this->companies),
                    'position' => $this->pick($this->positions),
                    'duration' => $this->pick([
                        '6 months',
                        '1 year',
                        '1 year 6 months',
                        '2 years',
                        '3 years',
                        '4 years',
                    ]),
                    'description' => $this->pick($this->workDescriptions),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            }

            // One to three trainings per applicant.
            $trainingCount = rand(1, 3);

            for ($i = 0; $i < $trainingCount; $i++) {
                $trainingRows[] = [
                    'applicant_id' => $applicantId,
                    'title' => $this->pick($this->trainingTitles),
                    'provider' => $this->pick($this->trainingProviders),
                    'year' => rand(2018, $currentYear),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            }

            // Zero to two seminars per applicant.
            $seminarCount = rand(0, 2);

            for ($i = 0; $i < $seminarCount; $i++) {
                $seminarRows[] = [
                    'applicant_id' => $applicantId,
                    'title' => $this->pick($this->seminarTitles),
                    'organizer' => $this->pick($this->seminarOrganizers),
                    'year' => rand(2018, $currentYear),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            }

            // Zero to two certifications per applicant.
            $certificationCount = rand(0, 2);

            for ($i = 0; $i < $certificationCount; $i++) {
                $certification = $this->pick($this->certifications);
                $yearIssued = rand(2018, $currentYear);

                $expiryYear = $this->rand() < 0.5
                    ? $yearIssued + rand(2, 5)
                    : null;

                $certificationRows[] = [
                    'applicant_id' => $applicantId,
                    'name' => $certification['name'],
                    'issuing_body' => $certification['body'],
                    'year_issued' => $yearIssued,
                    'expiry_year' => $expiryYear,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            }
        }

        // Insert only if there are records.
        if ($workExperienceRows) {
            DB::table('work_experiences')->insert($workExperienceRows);
        }

        if ($trainingRows) {
            DB::table('trainings')->insert($trainingRows);
        }

        if ($seminarRows) {
            DB::table('seminars')->insert($seminarRows);
        }

        if ($certificationRows) {
            DB::table('certifications')->insert($certificationRows);
        }

        $this->command->info(
            'Work experiences: ' . count($workExperienceRows)
            . ' | Trainings: ' . count($trainingRows)
            . ' | Seminars: ' . count($seminarRows)
            . ' | Certifications: ' . count($certificationRows)
        );

        // Run the real duplicate-detection service.
        $service = app(DuplicateDetectionService::class);
        $report = [];

        foreach ($this->scenarios() as $key => $scenario) {
            $applicant = Applicant::find(
                $ids[$duplicateRefByScenario[$key]]
            );

            $flags = $applicant
                ? $service->detect($applicant)
                : [];

            $flagged = count($flags) > 0;

            $score = $flagged
                ? max(array_map(
                    fn ($flag) => $flag->match_score,
                    $flags
                ))
                : 0;

            $report[] = [
                $key,
                $scenario['label'],
                $scenario['flag'] ? 'Flag' : 'No flag',
                $flagged ? "Flagged (score {$score})" : 'Not flagged',
                $flagged === $scenario['flag'] ? 'OK' : 'MISMATCH',
            ];
        }

        $this->command->newLine();

        $this->command->table(
            ['#', 'Scenario', 'Expected', 'Result', 'Check'],
            $report
        );

        $this->command->info(
            'Done! ' . count($rows)
            . ' applicants seeded. Applicant password: 123'
        );
    }

    private function makeRow(
        array $overrides,
        $barangays,
        Carbon $now
    ): array {
        $sex = $this->rand() < 0.52 ? 'Female' : 'Male';
        $age = $this->weightedAge();

        $createdAt = $now->copy()
            ->subDays(rand(0, 540))
            ->subHours(rand(0, 23))
            ->format('Y-m-d H:i:s');

        $firstName = $sex === 'Female'
            ? $this->pick($this->firstNamesFemale)
            : $this->pick($this->firstNamesMale);

        $lastName = $this->pick($this->lastNames);
        $barangay = $barangays->random();

        $row = [
            'reference_id' => 'PESO-' . strtoupper(
                substr(md5(uniqid((string) mt_rand(), true)), 0, 13)
            ),
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_name' => $this->rand() < 0.85
                ? $this->pick($this->middleNames)
                : null,
            'birthdate' => $now->copy()
                ->subYears($age)
                ->subDays(rand(0, 364))
                ->format('Y-m-d'),
            'sex' => $sex,
            'civil_status' => $this->weightedPick(
                ['Single', 'Married', 'Widowed', 'Separated'],
                [55, 35, 5, 5]
            ),
            'contact_number' => $this->pick($this->contactPrefixes)
                . rand(1, 100),
            'email' => $this->rand() < 0.45
                ? strtolower($firstName) . '.'
                    . strtolower(str_replace(' ', '', $lastName))
                    . rand(1, 999) . '@'
                    . $this->pick([
                        'gmail.com',
                        'yahoo.com',
                        'outlook.com',
                    ])
                : null,
            'password' => $this->passwordHash,
            'address' => $this->pick($this->streets)
                . ', ' . $barangay->barangay_name,
            'barangay_id' => $barangay->barangay_id,
            'is_active' => true,
            'consent_given' => true,
            'consent_given_at' => $createdAt,
            'last_name_metaphone' => '',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        $row = array_merge($row, $overrides);

        // Keep derived fields synchronized with overrides.
        $row['last_name_metaphone'] = metaphone($row['last_name']);
        $row['consent_given_at'] = $row['created_at'];
        $row['updated_at'] = $row['created_at'];

        return $row;
    }

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

            if ($roll <= $cumulative) {
                return $item;
            }
        }

        return $items[array_key_last($items)];
    }

    private function weightedAge(): int
    {
        $r = $this->rand() * 100;

        if ($r < 20) {
            return rand(18, 24);
        }

        if ($r < 55) {
            return rand(25, 35);
        }

        if ($r < 80) {
            return rand(36, 45);
        }

        if ($r < 95) {
            return rand(46, 55);
        }

        return rand(56, 65);
    }
}
