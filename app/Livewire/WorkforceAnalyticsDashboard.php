<?php
namespace App\Livewire;

use App\Models\{Applicant, SkillCategory, Barangay};
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class WorkforceAnalyticsDashboard extends Component {
    public string $filterCategory = '';
    public string $filterBarangay = '';
    public string $filterEdLevel  = '';
    public string $filterFrom     = '';
    public string $filterTo       = '';
    public string $filterSex      = '';

    public function updated() {
        $this->dispatch('refresh-charts', charts: $this->buildChartData());
    }

    private function baseQuery() {
        return Applicant::active()
            ->byDateRange($this->filterFrom, $this->filterTo)
            ->byBarangay($this->filterBarangay)
            ->byEducation($this->filterEdLevel)
            ->bySkillCategory($this->filterCategory)
            ->when($this->filterSex, fn($q) => $q->where('sex', $this->filterSex));
    }

    public function buildChartData(): array {
        // Chart 1: Top 10 skills by applicant count
        $skillsData = DB::table('applicant_skill')
            ->join('skills',           'skills.id',           '=', 'applicant_skill.skill_id')
            ->join('skill_categories', 'skill_categories.id', '=', 'skills.skill_category_id')
            ->joinSub($this->baseQuery()->select('id'), 'a', 'a.id', '=', 'applicant_skill.applicant_id')
            ->selectRaw('skills.name, skill_categories.name as category, COUNT(*) as total')
            ->groupBy('skills.id','skills.name','skill_categories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Chart 2: Education attainment distribution
        $eduData = DB::table('education')
            ->joinSub($this->baseQuery()->select('id'), 'a', 'a.id', '=', 'education.applicant_id')
            ->selectRaw('highest_level, COUNT(*) as total')
            ->groupBy('highest_level')
            ->orderByDesc('total')
            ->get();

        // Chart 3: Applicants by barangay (top 20)
        $barangayData = DB::table('applicants')
            ->join('barangays', 'barangays.id', '=', 'applicants.barangay_id')
            ->where('applicants.is_active', true)
            ->selectRaw('barangays.name, COUNT(*) as total')
            ->groupBy('barangays.id','barangays.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        // Chart 4: Monthly registration trend (last 12 months)
        $trendData = DB::table('applicants')
            ->where('is_active', true)
            ->where('created_at', '>=', now()->subMonths(12))
            // ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total") for mysql only
            ->selectRaw("strftime('%Y-%m', created_at) as month, COUNT(*) as total") //for sqlite
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'skills'    => ['labels' => $skillsData->pluck('name'),         'data' => $skillsData->pluck('total')],
            'education' => ['labels' => $eduData->pluck('highest_level'),   'data' => $eduData->pluck('total')],
            'barangay'  => ['labels' => $barangayData->pluck('name'),        'data' => $barangayData->pluck('total')],
            'trend'     => ['labels' => $trendData->pluck('month'),          'data' => $trendData->pluck('total')],
            'totals'    => [
                'total'     => $this->baseQuery()->count(),
                'thisMonth' => $this->baseQuery()->whereMonth('created_at', now()->month)->count(),
            ],
        ];
    }

    public function render() {
        return view('livewire.workforce-analytics-dashboard', [
            'chartData'      => $this->buildChartData(),
            'categories'     => SkillCategory::orderBy('name')->pluck('name','id'),
            'barangays'      => Barangay::orderBy('name')->pluck('name','id'),
            'educationLevels'=> [
                'Elementary','High School','Senior High School',
                'Vocational/Technical','College Undergraduate',
                'College Graduate','Post-Graduate',
            ],
        ])->layout('layouts.app');
    }
}
