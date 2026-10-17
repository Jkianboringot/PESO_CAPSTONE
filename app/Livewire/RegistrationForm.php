<?php
namespace App\Livewire;

use App\Models\{Applicant, Education, Barangay, Municipality, Skill, SkillCategory};
use App\Services\DuplicateDetectionService;
use App\Services\AuditLogService;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationForm extends Component {
    public int $step = 1;
    public int $totalSteps = 6;

    // PREVIEW: true while the applicant is editing one section from the review screen
    public bool $returnToReview = false;

    // Step 1: Personal Information
    public string $last_name     = '';
    public string $first_name    = '';
    public string $middle_name   = '';
    public string $birthdate     = '';
    public string $sex           = '';
    public string $civil_status  = '';
    public string $contact_number = '';
    public string $email         = '';
    public string $password              = '';
    public string $password_confirmation = '';

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
    public string $other_skills        = '';

    // Step 5: Experience & Training (each row is an array, added/removed with addRow/removeRow)
    public array $work_experiences = [];
    public array $trainings        = [];
    public array $seminars         = [];
    public array $certifications   = [];

    // Step 6: Oath + Consent
    public bool $oath_given    = false;
    public bool $consent_given = false;

    // Result
    public ?string $reference_id = null;
    public bool    $submitted    = false;

    // readable names for the repeating rows in error messages
    protected $validationAttributes = [
        'other_skills'                    => 'other skills',
        'work_experiences.*.company_name' => 'company / place of work',
        'work_experiences.*.position'     => 'position',
        'work_experiences.*.duration'     => 'duration',
        'work_experiences.*.description'  => 'description',
        'trainings.*.title'               => 'training title',
        'trainings.*.provider'            => 'training provider',
        'trainings.*.year'                => 'training year',
        'seminars.*.title'                => 'seminar title',
        'seminars.*.organizer'            => 'organizer',
        'seminars.*.year'                 => 'seminar year',
        'certifications.*.name'           => 'certification name',
        'certifications.*.issuing_body'   => 'issuing body',
        'certifications.*.year_issued'    => 'year issued',
        'certifications.*.expiry_year'    => 'expiry year',
    ];

    public function mount() {
        if ($this->municipality_id) $this->loadBarangays();
    }

    public function updatedMunicipalityId($value) {
        $this->barangay_id = null;
        $this->loadBarangays();
    }

    private function loadBarangays() {
    //CACHE - maybe i can cache this, so when one applies server just cache it and next applicant can just get that
        $this->barangays = Barangay::where('municipality_id', $this->municipality_id)
            ->orderBy('name')->pluck('name', 'id')->toArray();
    }

    // NEW: takes an optional step number so allRules() can ask for any step.
    // Leave it null and it uses the current step like before.
    private function rulesForStep(?int $step = null): array {
        return match($step ?? $this->step) {
            //needs to be customize to comply with ISO , we need error message to be
            //more detailed
                // example - example below is put as second parameter $this->validate([...],['this one'])
            //    [
            //     'name.required' => 'Name is required.',
            //     'name.max' => 'Name must not exceed 50 characters.',
            //     'name.regex' => 'Name must contain letters only and single spaces between words.',
            // ] use this 'regex:/^[A-Za-z]+(?: [A-Za-z]+)*$/' in validation
            1 => [
                'last_name'      => 'required|string|max:100',
                'first_name'     => 'required|string|max:100',
                'middle_name'    => 'nullable|string|max:100', // NEW
                'birthdate'      => 'required|date|before:today',
                'sex'            => 'required|in:Male,Female,Prefer not to say',
                'civil_status'   => 'required|in:Single,Married,Widowed,Separated',
                'contact_number' => 'required|regex:/^[0-9+\s\-]{10,15}$/|min:11|max:11',
                'email'          => 'nullable|email|max:150',
                'password'       => 'required|string|min:8|confirmed',
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
                'selected_skills'       => 'required|array|min:1',
                'selected_skills.*'     => 'exists:skills,id',
                'skill_proficiencies.*' => 'nullable|in:Beginner,Intermediate,Advanced,Expert', // NEW
                'other_skills'          => 'nullable|string|max:1000',
            ],
            5 => [
                // everything here is optional; a row you add must have its required fields filled
                'work_experiences.*.company_name' => 'required|string|max:150',
                'work_experiences.*.position'     => 'required|string|max:100',
                'work_experiences.*.duration'     => 'nullable|string|max:50',
                'work_experiences.*.description'  => 'nullable|string|max:1000',

                'trainings.*.title'    => 'required|string|max:150',
                'trainings.*.provider' => 'nullable|string|max:150',
                'trainings.*.year'     => 'nullable|integer|min:1950|max:'.(date('Y')+1),

                'seminars.*.title'     => 'required|string|max:150',
                'seminars.*.organizer' => 'nullable|string|max:150',
                'seminars.*.year'      => 'nullable|integer|min:1950|max:'.(date('Y')+1),

                'certifications.*.name'         => 'required|string|max:150',
                'certifications.*.issuing_body' => 'nullable|string|max:150',
                'certifications.*.year_issued'  => 'nullable|integer|min:1950|max:'.(date('Y')+1),
                'certifications.*.expiry_year'  => 'nullable|integer|min:1950|max:'.(date('Y')+50),
            ],
            6 => [
                'oath_given'    => 'accepted',
                'consent_given' => 'accepted',
            ],
            default => [],
        };
    }

//     private function allRules(): array
// {
//     return array_merge(
//         ...collect(range(1, 5))
//             ->map(fn($step) => $this->rulesForStep($step))
//             ->toArray()
//     );
// }

    // NEW: working version of the commented allRules above (merges rules of all 5 steps)
    private function allRules(): array {
        $rules = [];
        foreach (range(1, $this->totalSteps) as $s) {
            $rules = array_merge($rules, $this->rulesForStep($s));
        }
        return $rules;
    }

    // NEW: finds which step a field belongs to (handles dotted keys like skill_proficiencies.9)
    private function stepForField(string $field): int {
        $root = Str::before($field, '.');
        foreach (range(1, $this->totalSteps) as $s) {
            foreach (array_keys($this->rulesForStep($s)) as $key) {
                if (Str::before($key, '.') === $root) return $s;
            }
        }
        return 1;
    }

    // the four repeating sections of step 5 and the blank row each one starts with
    private function rowTemplates(): array {
        return [
            'work_experiences' => ['company_name' => '', 'position' => '', 'duration' => '', 'description' => ''],
            'trainings'        => ['title' => '', 'provider' => '', 'year' => ''],
            'seminars'         => ['title' => '', 'organizer' => '', 'year' => ''],
            'certifications'   => ['name' => '', 'issuing_body' => '', 'year_issued' => '', 'expiry_year' => ''],
        ];
    }

    public function addRow(string $type) {
        $templates = $this->rowTemplates();
        if (! isset($templates[$type])) return; // only the four known sections

        $this->{$type}[] = $templates[$type];
    }

    public function removeRow(string $type, int $index) {
        if (! isset($this->rowTemplates()[$type])) return;

        unset($this->{$type}[$index]);
        $this->{$type} = array_values($this->{$type}); // re-number so wire:model keys stay 0,1,2...
    }

    // keeps only the given fields and turns empty strings into null (year columns are integers)
    private function cleanRows(array $rows, array $fields): array {
        return array_map(function ($row) use ($fields) {
            $clean = [];
            foreach ($fields as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                $clean[$field] = $value === '' ? null : $value;
            }
            return $clean;
        }, array_values($rows));
    }

    public function nextStep() {
        $this->validate($this->rulesForStep());

        // PREVIEW: editing from the review screen -> save, then go straight back to review
        if ($this->returnToReview) {
            $this->returnToReview = false;
            $this->step = $this->totalSteps;
            return;
        }

        if ($this->step < $this->totalSteps) $this->step++;
    }

    public function prevStep() {
        if ($this->step > 1) $this->step--;
    }

    // PREVIEW: "Edit" button on the review screen jumps to that step
    public function goToStep(int $target) {
        if ($target >= 1 && $target < $this->totalSteps) {
            $this->step = $target;
            $this->returnToReview = true;
        }
    }

    public function submit(DuplicateDetectionService $detector, AuditLogService $audit) {

        //FIXME wrong use allRules - that current validate only validate the 5 one and you can skip prev step
        // FIXED: now validates all steps. It sits OUTSIDE the try/catch on purpose so a
        // ValidationException reaches Livewire and the @error blocks show,
        // instead of being swallowed as "Something went wrong".
        try {
            $this->validate($this->allRules());
        } catch (ValidationException $e) {
            // NEW: jump to the first step that has an error so the user can actually see it
            $firstField = array_key_first($e->validator->errors()->toArray());
            $target = $this->stepForField($firstField);

            $this->step = $target;
            // earlier step -> behave like "edit from review" (fix it, save, land back on review)
            $this->returnToReview = $target < $this->totalSteps;

            throw $e; // keeps the errors in the bag so the step shows them
        }

try {


// acid, fully succeed, or fully fail
        //SECURE - no try catch or errror hamd
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
                'password'         => Hash::make($this->password),
                'address'          => $this->address,
                'barangay_id'      => $this->barangay_id,
                'other_skills'     => trim($this->other_skills) ?: null,
                'oath_given'       => $this->oath_given,
                'oath_given_at'    => $this->oath_given ? now() : null,
                'consent_given'    => $this->consent_given, //CRITICAL they can skip process and go directly to submit so this is bad this need to be properly validated
                // FIXED: allRules() above now enforces consent_given = accepted, and we save the real value instead of hardcoded true
                'consent_given_at' => $this->consent_given ? now() : null, //should only be now() if consent is true, the proper way
                'status'           => 'Pending',
            ]);

            Education::create([
                'applicant_id'  => $applicant->id,
                'highest_level' => $this->highest_level,
                'course_program'=> $this->course_program ?: null,
                'school_name'   => $this->school_name ?: null,
                'year_graduated'=> $this->year_graduated ?: null,
            ]);

            $applicant->workExperiences()->createMany(
                $this->cleanRows($this->work_experiences, ['company_name', 'position', 'duration', 'description'])
            );
            $applicant->trainings()->createMany(
                $this->cleanRows($this->trainings, ['title', 'provider', 'year'])
            );
            $applicant->seminars()->createMany(
                $this->cleanRows($this->seminars, ['title', 'organizer', 'year'])
            );
            $applicant->certifications()->createMany(
                $this->cleanRows($this->certifications, ['name', 'issuing_body', 'year_issued', 'expiry_year'])
            );
            
            $skillData = [];
            foreach ($this->selected_skills as $skillId) { //REVIEW skill category is not store since skill holds that as FK
                //skillId is the id of skill
                $skillData[$skillId] = [ // REVIEW how skill proficiencies is encoded i dont get it, its like a double attach of skill id
                    // attach() wants [skill_id => [extra pivot columns]]
                    // the key is the skill id, the inner array is the extra data saved on the pivot row
                    'proficiency_level' => $this->skill_proficiencies[$skillId] ?? 'Beginner',
                ];
            }
            // dd( $skillData);

            $applicant->skills()->attach($skillData);

            $detector->detect($applicant); // QUEUE this but not sure
            $audit->logApplicantCreated($applicant);
            $this->reference_id = $applicant->reference_id;
        });

        $this->submitted = true;//CRITICAL they can skip process and go directly to submit so this is bad this need to be properly validated
        // FIXED: only reached after allRules() passed and the transaction did not throw
        $this->reset(['password', 'password_confirmation']);
    } catch (\Throwable $th) {
        //REVIEW - do i even need to log this, like is this important enough to log, imean error here will be db error so maybe
            Log::error($th); //TODO-LATER make sure all has this, logging
            $this->dispatch('done', error: "Something went wrong. Please try again.");
        }
    }

    public function render() {
        return view('livewire.registration-form', [
            'municipalities'   => Municipality::orderBy('name')->pluck('name', 'id'),
            'skillCategories'  => SkillCategory::with('skills')->orderBy('name')->get(),
            'proficiencyLevels'=> ['Beginner','Intermediate','Advanced','Expert'],
        ])->layout('layouts.registration');
    }
}