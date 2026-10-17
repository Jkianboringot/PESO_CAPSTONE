<?php
namespace App\Livewire;

use App\Models\{Applicant, SkillCategory, Barangay};
use Livewire\Attributes\Computed;
use Livewire\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkforceAnalyticsDashboard extends Component {
    public string $filterCategory = '';
    public string $filterBarangay = '';
    public string $filterEdLevel  = '';
    public string $filterFrom     = '';
    public string $filterTo       = '';
    public string $filterSex      = '';

    // No updated() hook on purpose: filters are only applied when the
    // Filter button is clicked (see applyFilters below).

    private function baseQuery() {
        [$from, $to] = $this->dateRange();

        return Applicant::active()
            ->byDateRange($from, $to)
            ->byBarangay($this->filterBarangay)
            ->byEducation($this->filterEdLevel)
            ->bySkillCategory($this->filterCategory)
            ->when($this->filterSex, fn($q) => $q->where('sex', $this->filterSex));
    }

    // Returns [from, to], swapping them if the user entered them backwards
    private function dateRange(): array {
        $from = $this->filterFrom ?: null;
        $to   = $this->filterTo ?: null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    // ── Demographics helpers ─────────────────────────────────────────────

    // Count filtered applicants grouped by a single column (sex, civil_status)
    private function countBy(string $column): array {
        $rows = $this->baseQuery()
            ->selectRaw("$column as label, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->get();

        return ['labels' => $rows->pluck('label'), 'data' => $rows->pluck('total')];
    }

    // Count filtered applicants per age bracket, computed from birthdate
    private function ageGroups(): array {
        // [min age, max age]; null = no upper limit
        $groups = [
            'Under 18' => [0, 17],
            '18–24'    => [18, 24],
            '25–34'    => [25, 34],
            '35–44'    => [35, 44],
            '45–54'    => [45, 54],
            '55+'      => [55, null],
        ];

        $labels = [];
        $data   = [];

        foreach ($groups as $label => [$min, $max]) {
            // age >= min  ->  birthdate on or before (today - min years)
            $q = $this->baseQuery()
                ->where('birthdate', '<=', now()->subYears($min)->toDateString());

            // age <= max  ->  birthdate after (today - (max + 1) years)
            if ($max !== null) {
                $q->where('birthdate', '>', now()->subYears($max + 1)->toDateString());
            }

            $labels[] = $label;
            $data[]   = $q->count();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    // Registrations per month. Defaults to the last 12 months when no range is set.
    private function registrationTrend(): array {
        [$from, $to] = $this->dateRange();

        $end   = $to ? Carbon::parse($to)->endOfMonth() : now()->endOfMonth();
        $start = $from
            ? Carbon::parse($from)->startOfMonth()
            : $end->copy()->subMonths(11)->startOfMonth();

        // Safety cap so a huge range doesn't produce an unreadable chart
        if ($start->diffInMonths($end) > 59) {
            $start = $end->copy()->subMonths(59)->startOfMonth();
        }

        $monthExpr = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            default  => "DATE_FORMAT(created_at, '%Y-%m')",   // MySQL / MariaDB
        };

        $rows = $this->baseQuery()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("$monthExpr as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Fill months with no registrations with 0 so the line has no gaps
        $labels = [];
        $data   = [];
        for ($m = $start->copy(); $m->lte($end); $m->addMonth()) {
            $labels[] = $m->format('M Y');
            $data[]   = (int) ($rows[$m->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    // ─────────────────────────────────────────────────────────────────────

    // Computed = cached for the length of one request, so the queries run
    // once even though both the dispatch and render() use the result.
    #[Computed]
    public function chartData(): array {
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

        // Chart 3: Applicants by barangay (top 20) - not affected by filters
        $barangayData = DB::table('applicants')
            ->join('barangays', 'barangays.id', '=', 'applicants.barangay_id')
            ->where('applicants.is_active', true)
            ->selectRaw('barangays.name, COUNT(*) as total')
            ->groupBy('barangays.id','barangays.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return [
            'skills'      => ['labels' => $skillsData->pluck('name'),         'data' => $skillsData->pluck('total')],
            'education'   => ['labels' => $eduData->pluck('highest_level'),   'data' => $eduData->pluck('total')],
            'barangay'    => ['labels' => $barangayData->pluck('name'),        'data' => $barangayData->pluck('total')],

            // Demographics
            'sex'         => $this->countBy('sex'),
            'civilStatus' => $this->countBy('civil_status'),
            'ageGroups'   => $this->ageGroups(),

            // Registration trend (per month)
            'trend'       => $this->registrationTrend(),

            'totals'      => [
                'total'     => $this->baseQuery()->count(),
                'thisMonth' => $this->baseQuery()->whereMonth('created_at', now()->month)->count(),
            ],
        ];
    }

    public function render() {
        //CACHE - cache this as it is use available alot, but think about this am not an expert in cache

        return view('livewire.workforce-analytics-dashboard', [
            'chartData'      => $this->chartData,
            'categories'     => SkillCategory::orderBy('name')->pluck('name','id'),
            'barangays'      => Barangay::orderBy('name')->pluck('name','id'),
            'educationLevels'=> [
                'Elementary','High School','Senior High School',
                'Vocational/Technical','College Undergraduate',
                'College Graduate','Post-Graduate',
            ],
        ])->layout('layouts.app');
    }

    // Runs when the Filter button is clicked. By this point Livewire has
    // already synced the form values, so we just redraw the charts.
    public function applyFilters()
    {
        $this->dispatch('refresh-charts', charts: $this->chartData);
    }

    public function clearFilters()
    {
        $this->reset(['filterCategory', 'filterBarangay', 'filterEdLevel', 'filterSex', 'filterFrom', 'filterTo']);
        $this->dispatch('refresh-charts', charts: $this->chartData);
    }
}