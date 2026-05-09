<?php
namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class SkillsGapAnalysis extends Component {
    public int $threshold = 10;

    public function render() {
        $skillRanking = DB::table('applicant_skill')
            ->join('skills',           'skills.id',           '=', 'applicant_skill.skill_id')
            ->join('skill_categories', 'skill_categories.id', '=', 'skills.skill_category_id')
            ->join('applicants', function($join) {
                $join->on('applicants.id', '=', 'applicant_skill.applicant_id')
                     ->where('applicants.is_active', true);
            })
            ->selectRaw('skills.name as skill,
                skill_categories.name as category,
                COUNT(DISTINCT applicant_skill.applicant_id) as applicant_count')
            ->groupBy('skills.id','skills.name','skill_categories.name')
            ->orderByDesc('applicant_count')
            ->get();

        $categoryTotals = DB::table('applicant_skill')
            ->join('skills',           'skills.id',           '=', 'applicant_skill.skill_id')
            ->join('skill_categories', 'skill_categories.id', '=', 'skills.skill_category_id')
            ->selectRaw('skill_categories.name, SUM(1) as total')
            ->groupBy('skill_categories.id','skill_categories.name')
            ->orderByDesc('total')
            ->get();

        return view('livewire.skills-gap-analysis', [
            'skillRanking'  => $skillRanking,
            'gapSkills'     => $skillRanking->where('applicant_count', '<',  $this->threshold),
            'surplusSkills' => $skillRanking->where('applicant_count', '>=', $this->threshold),
            'categoryTotals'=> $categoryTotals,
            'threshold'     => $this->threshold,
        ])->layout('layouts.app');
    }
}
