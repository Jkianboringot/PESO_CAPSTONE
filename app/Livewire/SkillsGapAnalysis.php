<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class SkillsGapAnalysis extends Component
{
    public int $threshold = 10;

    // SGA-2: keep the threshold at least 1, whatever is typed in the input.
    public function updatedThreshold($value): void
    {
        $this->threshold = max(1, (int) $value);
    }

    public function render()
    {
        // SGA-1: only admin and staff may view skill counts.
        abort_if(!auth()->user()?->hasRole(['admin', 'staff']), 403);

        // SGA-4: start from every skill, so skills with no active applicants
        // appear with a count of 0. Only active applicants are joined in.
        $skillRanking = DB::table('skills')
            ->join('skill_categories', 'skill_categories.id', '=', 'skills.skill_category_id')
            ->leftJoin('applicant_skill', 'applicant_skill.skill_id', '=', 'skills.id')
            ->leftJoin('applicants', function ($join) {
                $join->on('applicants.id', '=', 'applicant_skill.applicant_id')
                     ->where('applicants.is_active', true);
            })
            ->selectRaw('skills.name as skill,
                         skill_categories.name as category,
                         COUNT(DISTINCT applicants.id) as applicant_count')
            ->groupBy('skills.id', 'skills.name', 'skill_categories.name')
            ->orderByDesc('applicant_count')
            ->orderBy('skills.name')
            ->get();

        $gaps    = $skillRanking->where('applicant_count', '<', $this->threshold);
        $surplus = $skillRanking->where('applicant_count', '>=', $this->threshold);

        // SGA-3: category totals count only active applicants, so they match the skill panels.
        $categoryTotals = DB::table('skill_categories')
            ->leftJoin('skills', 'skills.skill_category_id', '=', 'skill_categories.id')
            ->leftJoin('applicant_skill', 'applicant_skill.skill_id', '=', 'skills.id')
            ->leftJoin('applicants', function ($join) {
                $join->on('applicants.id', '=', 'applicant_skill.applicant_id')
                     ->where('applicants.is_active', true);
            })
            ->selectRaw('skill_categories.id, skill_categories.name, COUNT(applicants.id) as total')
            ->groupBy('skill_categories.id', 'skill_categories.name')
            ->orderByDesc('total')
            ->orderBy('skill_categories.name')
            ->get();

        return view('livewire.skills-gap-analysis', [
            'skillRanking'   => $skillRanking,
            'gapSkills'      => $gaps,
            'surplusSkills'  => $surplus,
            'categoryTotals' => $categoryTotals,
            'threshold'      => $this->threshold,
        ])->layout('layouts.app');
    }
}