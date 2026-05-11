<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApplicantSeeder extends Seeder
{
    // ── Philippine-realistic name pools ──────────────────────────────────
    private array $lastNames = [
        'Santos','Reyes','Cruz','Bautista','Ocampo','Garcia','Mendoza','Torres',
        'Ramos','Flores','Dela Cruz','Lopez','Hernandez','Gonzales','Perez',
        'Aquino','Villanueva','Castillo','Morales','Soriano','Tolentino','Magno',
        'Abad','Aguilar','Aragon','Arellano','Arguelles','Arias','Arriola',
        'Bagamasbad','Balboa','Balmaceda','Baltazar','Bañez','Banzon','Barba',
        'Barnachea','Basilio','Batungbakal','Buenaventura','Bueno','Burgos',
        'Cabanag','Cabanting','Cabrales','Cacal','Cadiente','Caluag','Camiling',
        'Capili','Capino','Carbonell','Cariaga','Castañeda','Catacutan','Cepeda',
        'Cervantes','Cinco','Ciriaco','Coloma','Compuesto','Concepcion','Conde',
        'Corpuz','Cortez','Cubelo','Cuenca','Dacumos','Dagalea','Dancel',
        'Dantes','David','De Guzman','De Jesus','De Leon','De Mesa','De Torres',
        'Del Rosario','Delgado','Diaz','Didal','Dimaano','Dimacali','Dizon',
        'Dollente','Domingo','Doria','Dueñas','Dulay','Dumaual','Dy',
        'Ebora','Echevarria','Edralin','Ejercito','Elizondo','Enriquez','Ermino',
        'Escalante','Escolar','Esperanza','Espino','Espiritu','Estacio','Estrada',
        'Evangelista','Fabian','Fabro','Fajardo','Fallorina','Famularcano',
        'Farinas','Faustino','Feria','Fernandez','Figueroa','Fontanilla','Fonacier',
        'Francia','Francisco','Fruto','Fuentes','Fuentebella','Gabieta','Gabriel',
        'Galang','Galapon','Gallardo','Galvez','Gamboa','Gan','Garibay',
        'Gatmaitan','Geronimo','Godoy','Gomez','Guerrero','Guevara','Guiao',
        'Guinto','Gutierrez','Hermosura','Herrera','Hidalgo','Hilario','Hufana',
        'Ignacio','Imperial','Infante','Isidro','Javier','Jimenez','Joaquin',
        'Juico','Jurado','Lacap','Lagman','Lainez','Lanuza','Lapid',
        'Lara','Laserna','Latasa','Laurel','Lava','Laviña','Lazaro',
        'Ledesma','Legarda','Legazpi','Lim','Llamas','Lontoc','Lorenzana',
        'Lucero','Luna','Luzon','Macapagal','Maceda','Madrigal','Magsaysay',
        'Malabanan','Malapit','Malabed','Malacad','Malicdem','Mallari','Manaloto',
        'Manansala','Maniago','Manipon','Maniquis','Manlapaz','Mantalaba','Manuel',
        'Maquiling','Marasigan','Marcelino','Marcelo','Marcos','Margallo','Mariano',
        'Maronilla','Marquez','Martinez','Mateo','Mazo','Medina','Mejia',
        'Melencion','Mercado','Miranda','Molina','Molo','Montes','Montoya',
        'Moran','Morato','Muñoz','Narciso','Navarro','Neri','Nicolas',
        'Nisperos','Nieto','Noble','Nolasco','Noriega','Nuqui','Obispo',
        'Obusan','Olaco','Olaguera','Olano','Oliva','Olivares','Oliveros',
        'Ong','Opiña','Orense','Orillo','Orozco','Ortega','Ortiz',
        'Otico','Pacana','Padilla','Pagulayan','Palacio','Palacios','Palad',
        'Pama','Pangilinan','Paras','Parazo','Paredes','Pardo','Patron',
        'Pecaña','Penalosa','Peralta','Pimentel','Pineda','Placido','Planas',
        'Poblete','Ponce','Porras','Posadas','Prado','Punzal','Quezon',
        'Quijano','Quimpo','Quimson','Quintos','Quirino','Rabena','Ragasa',
        'Ramos','Razon','Recio','Regala','Remulla','Resurrecion','Ricafort',
        'Rivera','Robles','Roco','Rodriguez','Rojas','Roldan','Roman',
        'Romero','Ronquillo','Roque','Rosales','Rosario','Rubio','Rueda',
        'Ruiz','Saavedra','Sabado','Sabio','Sacay','Sacdalan','Saet',
        'Salazar','Saldaña','Salcedo','Samaniego','Samonte','Samson','Sanchez',
        'Sandoval','San Juan','San Pedro','Santa Ana','Santayana','Santiago',
        'Sarmienta','Sarmiento','Sato','Seva','Sierra','Sigua','Silva',
        'Simbulan','Sison','So','Soller','Sotto','Suarez','Sulit',
        'Tadeo','Tagala','Tancinco','Tañada','Tan','Tañedo','Tayag',
        'Tengco','Tiangco','Tibayan','Ticman','Tiglao','Timbol','Tirona',
        'Tobias','Tolosa','Tomboc','Toralba','Torno','Trinidad','Tuazon',
        'Ty','Ureta','Uy','Valdez','Valencia','Valenzuela','Varela',
        'Vargas','Vasquez','Velarde','Velasco','Veloso','Ventura','Verzosa',
        'Vicente','Victorino','Vidar','Vidad','Viernes','Viray','Vitug',
        'Yap','Ylagan','Zamora','Zara','Zobel','Zulueta','Zuno',
    ];

    private array $firstNamesMale = [
        'Juan','Jose','Antonio','Manuel','Francisco','Carlos','Miguel','Luis',
        'Pedro','Ricardo','Roberto','Eduardo','Fernando','Alfredo','Ernesto',
        'Ramon','Rodrigo','Eugenio','Gregorio','Teodoro','Angelo','Dante',
        'Renato','Rolando','Noel','Alvin','Arnel','Rodel','Reymart','Jonel',
        'Christian','Mark','John','Michael','Ryan','Jerome','Jayson','Julius',
        'Jeffrey','Jonathan','James','Joseph','Jason','Joshua','Joel','Jomar',
        'Kenneth','Kevin','Karl','Kristopher','Kyle','Klarenz','Karlo','Kenrick',
        'Lester','Leonardo','Lorenzo','Louie','Luigi','Luke','Lance','Lawrence',
        'Marlon','Mario','Mauro','Manolo','Marco','Martin','Maynard','Marlo',
        'Nathan','Neil','Nelson','Nestor','Nicholas','Norman','Noel','Nonoy',
        'Oliver','Orlando','Oscar','Oswaldo','Othmar','Omarr','Omer','Orville',
        'Patrick','Paul','Peter','Philip','Paolo','Percival','Pio','Primo',
        'Rafael','Ramir','Randy','Raphael','Renzel','Rex','Ricky','Roger',
        'Romeo','Ronie','Ronnie','Rosendo','Roy','Rudy','Russell','Russel',
        'Salvador','Samuel','Santiago','Sean','Sherwin','Siegfredo','Simon',
        'Sonny','Stephen','Steven','Teodoro','Thomas','Timothy','Tomas',
        'Victor','Vicente','Vincent','Vladimir','Warren','Wendell','Wilfredo',
        'William','Winston','Xavier','Ynigo','Yvan','Zandro','Zaldy','Zosimo',
    ];

    private array $firstNamesFemale = [
        'Maria','Ana','Rosa','Carmen','Luz','Elena','Gloria','Pilar',
        'Consuelo','Remedios','Milagros','Natividad','Concepcion','Rosario','Lourdes',
        'Angela','Andrea','Angelica','Anita','Anna','Annaliza','Annabelle',
        'Beatriz','Belinda','Bernadette','Beverly','Bianca','Blessie','Brenda',
        'Carla','Carina','Catherine','Cecilia','Celeste','Cherry','Christine',
        'Clarissa','Claudia','Corazon','Cristina','Czarina','Czarinah','Daisy',
        'Dana','Danielle','Darlene','Dawn','Diana','Dolores','Donna','Dorothy',
        'Eden','Edna','Elaine','Eleanor','Elizabeth','Elvira','Emily','Emma',
        'Esmeralda','Esperanza','Estrella','Eva','Evangeline','Fe','Felicitas',
        'Filipina','Flordeliza','Florence','Florita','Frances','Francisca','Gemma',
        'Genoveva','Georgia','Geraldine','Gertrudes','Gina','Gloria','Grace',
        'Hannah','Hazel','Helen','Helena','Hilda','Ines','Irene','Isabel',
        'Isadora','Jacqueline','Janet','Jasmine','Jean','Jennifer','Jenny',
        'Jessica','Joanna','Josephine','Joyce','Judith','Julie','Karen',
        'Katrina','Kristina','Laura','Leah','Lena','Leonora','Leslie',
        'Leticia','Lilia','Liliana','Linda','Lisa','Lorena','Lorraine',
        'Lucila','Luisa','Lydia','Mabel','Madeleine','Magdalena','Maribel',
        'Maricel','Maricor','Marilyn','Marina','Marites','Marlene','Martha',
        'Mary','Mayumi','Mercedes','Michelle','Monica','Nena','Nicole',
        'Nina','Nora','Norma','Olivia','Patricia','Paula','Rachel','Rebecca',
        'Regina','Rhea','Rica','Richelle','Rita','Rowena','Ruby','Ruth',
        'Sandra','Sarah','Sharon','Sheila','Shirley','Sofia','Stella',
        'Susan','Teresa','Theresa','Tina','Vanessa','Veronica','Victoria',
        'Virginia','Vivian','Wendy','Yvette','Yvonne','Zenaida','Zoila',
    ];

    private array $middleNames = [
        'A.','B.','C.','D.','E.','F.','G.','H.','I.','J.','K.','L.','M.',
        'N.','O.','P.','Q.','R.','S.','T.','U.','V.','W.','Y.','Z.',
        'Santos','Reyes','Cruz','Garcia','Mendoza','Torres','Ramos','Flores',
        'Lopez','Hernandez','Gonzales','Villanueva','Castillo','Morales','Soriano',
    ];

    private array $streets = [
        'Purok 1','Purok 2','Purok 3','Purok 4','Sitio Bagong Buhay',
        'Sitio Mabini','Sitio Rizal','Sitio Magsaysay','Sitio del Monte',
        'Sitio Pag-asa','Sitio Masagana','Sitio Maliwanag','Sitio Bagumbayan',
        'Blk 1 Lot 5','Blk 2 Lot 12','Blk 3 Lot 7','Blk 4 Lot 3',
        'National Highway','Barangay Road','Coastal Road','Mountain Road',
        'Near the Church','Near the School','Near the Market','Near the Port',
    ];

    private array $contactPrefixes = ['0917','0918','0919','0920','0921','0922',
        '0923','0926','0927','0928','0929','0930','0932','0933','0935',
        '0936','0939','0942','0946','0947','0948','0949','0950','0955',
        '0956','0961','0963','0966','0967','0973','0975','0977','0979',
        '0995','0996','0997','0998','0999',
    ];

    // ── Catanduanes geography ────────────────────────────────────────────
    private array $barangaysByMunicipality = [
        'Virac'      => ['Antipolo','Balite','Bigaa','Buenavista','Buyo','Gogon Caatoan',
                         'Gogon Sirangan','Hawan Ilaya','Hawan Ibaba','Igang','Jupi',
                         'Lubas','Lupi','Magnesia del Norte','Magnesia del Sur',
                         'Marinawa','Ogbong','Pajo','Rawis','Salvacion','San Isidro',
                         'San Jose','San Juan Bag-ong Lungsod','San Pablo','San Pedro',
                         'San Roque','San Vicente','Santa Cruz','Santo Domingo',
                         'Tagas','Trinidad','Virac Poblacion','Washington'],
        'Pandan'     => ['Agban','Buenavista','Cagdarao','Cawayan','Coliat','Del Rosario',
                         'Gata','Gubat','Lawaan','Lubas','Mabini','Osiao',
                         'Pandan Poblacion','Salvacion','San Isidro','San Jose',
                         'San Lorenzo','San Nicolas','San Roque','Santa Cruz','Timbaan'],
        'Caramoran'  => ['Alibijaban','Buenavista','Del Rosario','Guialo','Hamorawon',
                         'Lubas','Mabini','Malinao','Panay','Salogon',
                         'Caramoran Poblacion','San Andres','San Isidro','San Jose'],
        'San Andres' => ['Bagong Silang','Buenavista','Buyo','Cagbalogo','Calolbon',
                         'Del Rosario','Mabini','Malinao','Poblacion','Salvacion',
                         'San Isidro','San Jose','San Juan','San Ramon','Santa Cruz'],
        'Bato'       => ['Bato Poblacion','Buyo','Cabihian','Cagsawa','Lalud',
                         'Libod','Napo','Obo','Oga','San Isidro','San Juan',
                         'San Pablo','San Pedro','Santa Cruz','Tinago','Vinadac'],
        'Viga'       => ['Almojuela','Banao','Buyo','Cabugao','Del Rosario',
                         'Hamorawon','Igang','Japitan','Laboy','Libod','Lubas',
                         'Mabini','Malinao','Panay','Salvacion','San Isidro',
                         'San Jose','San Juan','Santa Cruz','Viga Poblacion'],
        'Bagamanoc'  => ['Bagamanoc Poblacion','Biong','Buenavista','Cabcab',
                         'Carangag','Codon','Comag','Lubas','Otap','Pambuhan',
                         'San Vicente','Santa Cruz','Tabugon'],
        'Baras'      => ['Baras Poblacion','Batolinao','Buenavista','Cabugao',
                         'Hilawan','Magapo','Ogbong','Panique','Salvacion',
                         'San Isidro','San Miguel','San Roque','Santa Cruz'],
        'Gigmoto'    => ['Bagong Sikat','Batohonas','Biong','Buyo',
                         'Gigmoto Poblacion','Pandan','Salvacion','San Vicente'],
        'Panganiban' => ['Bacak','Buenavista','Bulalacao','Cabcab','Cagbalogo',
                         'Del Rosario','Mabini','Nagsiya','Panganiban Poblacion',
                         'San Isidro','San Juan','Santa Cruz'],
        'San Miguel' => ['Burabod','Calabnigan','Cawayan','Cobo','Igang',
                         'Naga','Pandan','San Miguel Poblacion','Santa Cruz'],
    ];

    private array $educationLevels = [
        'Elementary','High School','Senior High School',
        'Vocational/Technical','College Undergraduate',
        'College Graduate','Post-Graduate',
    ];

    // Education level weights — realistic distribution for Catanduanes
    private array $eduWeights = [15, 25, 15, 20, 10, 12, 3];

    private array $courses = [
        'BS Information Systems','BS Information Technology','BS Computer Science',
        'BS Nursing','BS Education','BS Agriculture','BS Fisheries','BS Criminology',
        'BS Business Administration','BS Accountancy','BS Engineering','BS Architecture',
        'BS Tourism','BS Hotel and Restaurant Management','BS Social Work',
        'Bachelor of Secondary Education','Bachelor of Elementary Education',
        'Diploma in Computer Technology','NC II Computer Hardware Servicing',
        'NC II Electrical Installation','NC II Plumbing','NC II Welding',
        'NC II Caregiving','NC II Food and Beverage','NC II Bookkeeping',
        'NC III Automotive Servicing','NC II Dressmaking','NC II Beauty Care',
    ];

    private array $schools = [
        'Catanduanes State University','Catanduanes College','Divine Word College',
        'STI College','AMA Computer University','Palompon Institute of Technology',
        'University of the Philippines','De La Salle University','Ateneo de Manila',
        'University of Santo Tomas','Far Eastern University','Mapua University',
        'Bicol University','University of Nueva Caceres','Partido College',
        'Catanduanes National High School','Virac National High School',
        'Pandan National High School','Caramoran National High School',
        'Bato National High School','Catanduanes National School of Arts and Trades',
        'TESDA Training Center - Catanduanes',
    ];

    private array $statuses = ['Pending','Pending','Pending','Verified','Verified','Flagged'];

    // Skills mapped to category names
    private array $skillsByCategory = [
        'ICT & Digital Technology'   => ['Computer Hardware Servicing','Web Development','Network Administration','Data Encoding','Graphic Design','Social Media Management','Software Development'],
        'Agricultural & Fisheries'   => ['Rice Farming','Coconut Processing','Fishery Operations','Vegetable Production','Animal Husbandry'],
        'Construction & Engineering' => ['Masonry','Carpentry','Plumbing','Electrical Wiring','Welding & Fabrication'],
        'Health & Social Services'   => ['Caregiving','Health Care Support','Community Health Work','Medical Transcription'],
        'Tourism & Hospitality'      => ['Food & Beverage Service','Housekeeping','Front Office Services','Tour Guiding','Bartending'],
        'Business & Administration'  => ['Bookkeeping','Customer Service','Office Administration','Business Process Outsourcing'],
        'Maritime & Transport'       => ['Vessel Operations','Port Operations','Driving (Professional)'],
        'Arts, Crafts & Design'      => ['Pottery & Ceramics','Weaving','Jewelry Making','Painting'],
        'Teaching & Education'       => ['Elementary Teaching','Secondary Teaching','Vocational Training'],
        'Trade & Technical Services' => ['Automotive Servicing','Refrigeration & AC Servicing','Tailoring & Dressmaking','Beauty Care & Nail Care'],
    ];

    // ────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        $this->command->info('Seeding 1,000 fake applicants for PESO Connect...');

        // Pre-load lookup tables
        $barangayMap  = DB::table('barangays')
            ->join('municipalities','municipalities.id','=','barangays.municipality_id')
            ->select('barangays.id as barangay_id','barangays.name as barangay_name',
                     'municipalities.name as municipality_name')
            ->get()
            ->groupBy('municipality_name');

        $skillMap = DB::table('skills')
            ->join('skill_categories','skill_categories.id','=','skills.skill_category_id')
            ->select('skills.id as skill_id','skills.name as skill_name',
                     'skill_categories.name as category_name')
            ->get()
            ->groupBy('category_name');

        $proficiencies = ['Beginner','Beginner','Beginner','Intermediate','Intermediate','Advanced','Expert'];

        $now = Carbon::now();
        $bar = $this->command->getOutput()->createProgressBar(1000);
        $bar->start();

        $applicantRows = [];
        $educationRows = [];
        $skillRows     = [];

        $municipalityNames = array_keys($this->barangaysByMunicipality);

        // Weight municipalities — Virac gets ~35%, others distributed
        $muniWeights = [35,12,8,8,8,7,5,5,4,4,4]; // Virac first

        for ($i = 0; $i < 1000; $i++) {
            $sex = $this->rand() < 0.52 ? 'Female' : 'Male';

            $lastName   = $this->pick($this->lastNames);
            $firstName  = $sex === 'Female'
                ? $this->pick($this->firstNamesFemale)
                : $this->pick($this->firstNamesMale);
            $middleName = $this->rand() < 0.85 ? $this->pick($this->middleNames) : null;

            // Birthdate: ages 18–65, weighted toward 20–40
            $age       = $this->weightedAge();
            $birthdate = $now->copy()->subYears($age)->subDays(rand(0, 364))->format('Y-m-d');

            $contact = $this->pick($this->contactPrefixes) . rand(1000000, 9999999);
            $email   = $this->rand() < 0.45
                ? strtolower(str_replace(' ','.', $firstName)) . '.' . strtolower($lastName) . rand(1,999) . '@' . $this->pick(['gmail.com','yahoo.com','outlook.com'])
                : null;

            // Geography
            $muniName = $this->weightedPick($municipalityNames, $muniWeights);
            $barangayOptions = $barangayMap->get($muniName);
            if (!$barangayOptions || $barangayOptions->isEmpty()) {
                // fallback
                $muniName = 'Virac';
                $barangayOptions = $barangayMap->get('Virac');
            }
            $selectedBarangay = $barangayOptions->random();
            $barangayId = $selectedBarangay->barangay_id;

            $address = $this->pick($this->streets) . ', ' . $selectedBarangay->barangay_name;

            // Education
            $eduLevel = $this->weightedPick($this->educationLevels, $this->eduWeights);
            $course   = null;
            $school   = null;
            $yearGrad = null;
            if (!in_array($eduLevel, ['Elementary','High School'])) {
                $course   = $this->rand() < 0.80 ? $this->pick($this->courses) : null;
                $school   = $this->rand() < 0.85 ? $this->pick($this->schools) : null;
                $maxGrad  = (int)$now->format('Y');
                $minGrad  = max(1990, $maxGrad - $age + 16);
                $yearGrad = $minGrad < $maxGrad ? rand($minGrad, $maxGrad) : $maxGrad;
            }

            $status    = $this->rand() < 0.65 ? 'Pending' : ($this->rand() < 0.85 ? 'Verified' : 'Flagged');
            $isActive  = $status === 'Inactive' ? false : true;
            $refId     = 'PESO-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 13));
            $metaphone = metaphone($lastName);

            // Consent: all 1000 gave consent
            $consentAt = Carbon::parse($birthdate)->addYears($age)->subDays(rand(0, 365))->format('Y-m-d H:i:s');
            if ($consentAt > $now->format('Y-m-d H:i:s')) $consentAt = $now->format('Y-m-d H:i:s');

            // Stagger created_at over the past 18 months for realistic trend chart
            $createdAt = $now->copy()->subDays(rand(0, 540))->subHours(rand(0, 23))->format('Y-m-d H:i:s');

            $applicantRows[] = [
                'reference_id'        => $refId,
                'last_name'           => $lastName,
                'first_name'          => $firstName,
                'middle_name'         => $middleName,
                'birthdate'           => $birthdate,
                'sex'                 => $sex,
                'civil_status'        => $this->weightedPick(
                    ['Single','Married','Widowed','Separated'],
                    [55, 35, 5, 5]
                ),
                'contact_number'      => $contact,
                'email'               => $email,
                'address'             => $address,
                'barangay_id'         => $barangayId,
                'status'              => $status,
                'is_active'           => $isActive,
                'consent_given'       => true,
                'consent_given_at'    => $consentAt,
                'last_name_metaphone' => $metaphone,
                'created_at'          => $createdAt,
                'updated_at'          => $createdAt,
            ];

            $bar->advance();
        }

        // ── Bulk insert applicants in chunks ─────────────────────────────
        $chunks = array_chunk($applicantRows, 100);
        foreach ($chunks as $chunk) {
            DB::table('applicants')->insert($chunk);
        }

        // ── Fetch inserted applicant IDs ──────────────────────────────────
        $insertedApplicants = DB::table('applicants')
            ->whereIn('reference_id', array_column($applicantRows, 'reference_id'))
            ->select('id','reference_id','birthdate','last_name')
            ->get()
            ->keyBy('reference_id');

        // ── Build education + skill rows ──────────────────────────────────
        foreach ($applicantRows as $idx => $row) {
            $applicant   = $insertedApplicants->get($row['reference_id']);
            if (!$applicant) continue;
            $applicantId = $applicant->id;

            // Education row
            $eduLevel = $this->weightedPick($this->educationLevels, $this->eduWeights);
            $course   = null;
            $school   = null;
            $yearGrad = null;
            if (!in_array($eduLevel, ['Elementary','High School'])) {
                $course   = $this->rand() < 0.80 ? $this->pick($this->courses) : null;
                $school   = $this->rand() < 0.85 ? $this->pick($this->schools) : null;
                $age      = Carbon::parse($row['birthdate'])->age;
                $maxGrad  = (int)$now->format('Y');
                $minGrad  = max(1990, $maxGrad - $age + 16);
                $yearGrad = $minGrad < $maxGrad ? rand($minGrad, $maxGrad) : $maxGrad;
            }
            $educationRows[] = [
                'applicant_id'  => $applicantId,
                'highest_level' => $eduLevel,
                'course_program'=> $course,
                'school_name'   => $school,
                'year_graduated'=> $yearGrad,
                'created_at'    => $row['created_at'],
                'updated_at'    => $row['updated_at'],
            ];

            // Skills: pick 1–5 skills from 1–3 random categories
            $numCategories = rand(1, 3);
            $categoryNames = array_keys($this->skillsByCategory);
            shuffle($categoryNames);
            $selectedCategories = array_slice($categoryNames, 0, $numCategories);

            $usedSkillIds = [];
            foreach ($selectedCategories as $catName) {
                $catSkills = $skillMap->get($catName);
                if (!$catSkills || $catSkills->isEmpty()) continue;

                $numSkills = rand(1, min(3, $catSkills->count()));
                $shuffled  = $catSkills->shuffle()->take($numSkills);

                foreach ($shuffled as $skill) {
                    if (in_array($skill->skill_id, $usedSkillIds)) continue;
                    $usedSkillIds[] = $skill->skill_id;
                    $skillRows[] = [
                        'applicant_id'      => $applicantId,
                        'skill_id'          => $skill->skill_id,
                        'proficiency_level' => $this->pick($proficiencies),
                    ];
                }
            }
        }

        // ── Bulk insert education in chunks ───────────────────────────────
        foreach (array_chunk($educationRows, 100) as $chunk) {
            DB::table('education')->insert($chunk);
        }

        // ── Bulk insert skills in chunks (deduplicate first) ───────────────
        $uniqueSkillRows = [];
        $seenPairs = [];
        foreach ($skillRows as $sr) {
            $key = $sr['applicant_id'] . '_' . $sr['skill_id'];
            if (!isset($seenPairs[$key])) {
                $seenPairs[$key]   = true;
                $uniqueSkillRows[] = $sr;
            }
        }
        foreach (array_chunk($uniqueSkillRows, 200) as $chunk) {
            DB::table('applicant_skill')->insert($chunk);
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('Done! 1,000 applicants seeded with education and skills.');
        $this->command->info('Education rows: ' . count($educationRows));
        $this->command->info('Skill pivot rows: ' . count($uniqueSkillRows));
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
        $total  = array_sum($weights);
        $rand   = mt_rand(1, $total);
        $cumulative = 0;
        foreach ($items as $i => $item) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) return $item;
        }
        return $items[array_key_last($items)];
    }

    private function weightedAge(): int
    {
        // 18-24: 20%, 25-35: 35%, 36-45: 25%, 46-55: 15%, 56-65: 5%
        $r = $this->rand() * 100;
        if ($r < 20)  return rand(18, 24);
        if ($r < 55)  return rand(25, 35);
        if ($r < 80)  return rand(36, 45);
        if ($r < 95)  return rand(46, 55);
        return rand(56, 65);
    }
}