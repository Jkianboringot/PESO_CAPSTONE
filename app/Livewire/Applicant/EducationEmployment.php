<?php

namespace App\Livewire\Applicant;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.applicant-app')]
#[Title('Education & Employment')]
class EducationEmployment extends Component
{
    public const LEVELS = [
        'Elementary', 'High School', 'Senior High School',
        'Vocational/Technical', 'College Undergraduate',
        'College Graduate', 'Post-Graduate',
    ];

    public string $highest_level = '';
    public string $course_program = '';
    public string $school_name = '';
    public string $year_graduated = '';

    public function mount(): void
    {
        $edu = DB::table('education')
            ->where('applicant_id', Auth::guard('applicant')->id())
            ->first();

        if ($edu) {
            $this->highest_level  = (string) $edu->highest_level;
            $this->course_program = (string) $edu->course_program;
            $this->school_name    = (string) $edu->school_name;
            $this->year_graduated = (string) $edu->year_graduated;
        }
    }

    protected function rules(): array
    {
        return [
            'highest_level'  => ['required', Rule::in(self::LEVELS)],
            'course_program' => ['nullable', 'string', 'max:255'],
            'school_name'    => ['nullable', 'string', 'max:255'],
            'year_graduated' => ['nullable', 'integer', 'between:1950,' . now()->year],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $applicantId = Auth::guard('applicant')->id();

        $values = [
            'highest_level'  => $this->highest_level,
            'course_program' => $this->course_program !== '' ? $this->course_program : null,
            'school_name'    => $this->school_name !== '' ? $this->school_name : null,
            'year_graduated' => $this->year_graduated !== '' ? (int) $this->year_graduated : null,
            'updated_at'     => now(),
        ];

        $exists = DB::table('education')->where('applicant_id', $applicantId)->exists();

        if ($exists) {
            DB::table('education')->where('applicant_id', $applicantId)->update($values);
        } else {
            DB::table('education')->insert($values + ['applicant_id' => $applicantId, 'created_at' => now()]);
        }

        session()->flash('status', 'Education details saved.');
    }

    public function render()
    {
        return view('livewire.applicant.applicant-education', ['levels' => self::LEVELS]);
    }
}