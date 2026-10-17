<?php

namespace App\Livewire\Applicant;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.applicant-app')]
#[Title('Skills')]
class Skills extends Component
{
    public const LEVELS = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];

    public string $category_id = '';
    public string $skill_id = '';
    public string $proficiency = 'Beginner';

    public function updatedCategoryId(): void
    {
        $this->skill_id = '';
    }

    public function addSkill(): void
    {
        $this->validate([
            'skill_id'    => ['required', 'integer', 'exists:skills,id'],
            'proficiency' => ['required', Rule::in(self::LEVELS)],
        ], [
            'skill_id.required' => 'Please choose a skill.',
        ]);

        $applicantId = Auth::guard('applicant')->id();

        $exists = DB::table('applicant_skill')
            ->where('applicant_id', $applicantId)
            ->where('skill_id', (int) $this->skill_id)
            ->exists();

        if ($exists) {
            $this->addError('skill_id', 'That skill is already on your record.');
            return;
        }

        DB::table('applicant_skill')->insert([
            'applicant_id'      => $applicantId,
            'skill_id'          => (int) $this->skill_id,
            'proficiency_level' => $this->proficiency,
        ]);

        $this->reset('skill_id', 'proficiency');
        session()->flash('status', 'Skill added.');
    }

    public function removeSkill(int $skillId): void
    {
        DB::table('applicant_skill')
            ->where('applicant_id', Auth::guard('applicant')->id())
            ->where('skill_id', $skillId)
            ->delete();

        session()->flash('status', 'Skill removed.');
    }

    public function render()
    {
        $applicantId = Auth::guard('applicant')->id();

        $mine = DB::table('applicant_skill')
            ->join('skills', 'skills.id', '=', 'applicant_skill.skill_id')
            ->join('skill_categories', 'skill_categories.id', '=', 'skills.skill_category_id')
            ->where('applicant_skill.applicant_id', $applicantId)
            ->orderBy('skill_categories.name')
            ->orderBy('skills.name')
            ->get([
                'skills.id as skill_id',
                'skills.name as skill_name',
                'skill_categories.name as category_name',
                'applicant_skill.proficiency_level',
            ]);

        $categories = DB::table('skill_categories')->orderBy('name')->get(['id', 'name']);

        $available = $this->category_id !== ''
            ? DB::table('skills')
                ->where('skill_category_id', (int) $this->category_id)
                ->whereNotIn('id', $mine->pluck('skill_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('livewire.applicant.applicant-skill', [
            'mine'       => $mine,
            'categories' => $categories,
            'available'  => $available,
            'levels'     => self::LEVELS,
        ]);
    }
}