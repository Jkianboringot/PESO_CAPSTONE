<?php
namespace App\Livewire;

use App\Models\{Applicant, Education, Barangay, Municipality, Skill, SkillCategory};
use App\Services\{DuplicateDetectionService, AuditLogService};
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class RegistrationForm extends Component {
    public int $step = 1;
    public int $totalSteps = 5;

    // Step 1: Personal Information
    public string $last_name     = '';
    public string $first_name    = '';
    public string $middle_name   = '';
    public string $birthdate     = '';
    public string $sex           = '';
    public string $civil_status  = '';
    public string $contact_number = '';
    public string $email         = '';

    // Step 2: Geographic
    public string $address          = '';
    public ?int   $municipality_id  = null;
    public ?int   $barangay_id      = null;
    public array  $barangays        = [];

    // Step 3: Education
    public string $highest_level   = '';
    public string $course_program  = '';
    public string $school_name     = '';
    public string $year_graduated  = '';

    // Step 4: Skills
    public array $selected_skills      = [];
    public array $skill_proficiencies  = [];

    // Step 5: Consent
    public bool $consent_given = false;

    // Result
    public ?string $reference_id = null;
    public bool    $submitted    = false;

    public function mount() {
        if ($this->municipality_id) $this->loadBarangays();
    }

    public function updatedMunicipalityId($value) {
        $this->barangay_id = null;
        $this->loadBarangays();
    }

    private function loadBarangays() {
        $this->barangays = Barangay::where('municipality_id', $this->municipality_id)
            ->orderBy('name')->pluck('name', 'id')->toArray();
    }

    private function rulesForStep(): array {
        return match($this->step) {
            1 => [
                'last_name'      => 'required|string|max:100',
                'first_name'     => 'required|string|max:100',
                'birthdate'      => 'required|date|before:today',
                'sex'            => 'required|in:Male,Female,Prefer not to say',
                'civil_status'   => 'required|in:Single,Married,Widowed,Separated',
                'contact_number' => 'required|regex:/^[0-9+\s\-]{10,15}$/',
                'email'          => 'nullable|email|max:150',
            ],
            2 => [
                'address'         => 'required|string|max:255',
                'municipality_id' => 'required|exists:municipalities,id',
                'barangay_id'     => 'required|exists:barangays,id',
            ],
            3 => [
                'highest_level'  => 'required|string',
                'course_program' => 'nullable|string|max:200',
                'school_name'    => 'nullable|string|max:200',
                'year_graduated' => 'nullable|integer|min:1950|max:'.(date('Y')+1),
            ],
            4 => [
                'selected_skills'   => 'required|array|min:1',
                'selected_skills.*' => 'exists:skills,id',
            ],
            5 => ['consent_given' => 'accepted'],
            default => [],
        };
    }

    public function nextStep() {
        $this->validate($this->rulesForStep());
        if ($this->step < $this->totalSteps) $this->step++;
    }

    public function prevStep() {
        if ($this->step > 1) $this->step--;
    }

    public function submit(DuplicateDetectionService $detector, AuditLogService $audit) {
        $this->validate($this->rulesForStep());

        DB::transaction(function () use ($detector, $audit) {
            $applicant = Applicant::create([
                'last_name'        => $this->last_name,
                'first_name'       => $this->first_name,
                'middle_name'      => $this->middle_name ?: null,
                'birthdate'        => $this->birthdate,
                'sex'              => $this->sex,
                'civil_status'     => $this->civil_status,
                'contact_number'   => $this->contact_number,
                'email'            => $this->email ?: null,
                'address'          => $this->address,
                'barangay_id'      => $this->barangay_id,
                'consent_given'    => true,
                'consent_given_at' => now(),
                'status'           => 'Pending',
            ]);

            Education::create([
                'applicant_id'  => $applicant->id,
                'highest_level' => $this->highest_level,
                'course_program'=> $this->course_program ?: null,
                'school_name'   => $this->school_name ?: null,
                'year_graduated'=> $this->year_graduated ?: null,
            ]);

            $skillData = [];
            foreach ($this->selected_skills as $skillId) {
                $skillData[$skillId] = [
                    'proficiency_level' => $this->skill_proficiencies[$skillId] ?? 'Beginner',
                ];
            }
            $applicant->skills()->attach($skillData);

            $detector->detect($applicant);
            $audit->logApplicantCreated($applicant);
            $this->reference_id = $applicant->reference_id;
        });

        $this->submitted = true;
    }

    public function render() {
        return view('livewire.registration-form', [
            'municipalities'   => Municipality::orderBy('name')->pluck('name', 'id'),
            'skillCategories'  => SkillCategory::with('skills')->orderBy('name')->get(),
            'proficiencyLevels'=> ['Beginner','Intermediate','Advanced','Expert'],
        ])->layout('layouts.guest');
    }
}
