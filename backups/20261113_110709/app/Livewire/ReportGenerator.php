<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Exports\ApplicantsExport;
use App\Models\{Applicant, Barangay, SkillCategory};
use App\Services\AuditLogService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ReportGenerator extends Component
{
    use WithPagination;

    public string $dateFrom   = '';
    public string $dateTo     = '';
    public string $barangayId = '';
    public string $categoryId = '';
    public string $educLevel  = '';
    public string $sex        = '';
    public string $format     = 'xlsx';

    // Reset to page 1 whenever a filter changes (not when the format changes)
    public function updated($property)
    {
        if ($property !== 'format') {
            $this->resetPage();
        }
    }

    // Same filters as the export, applied to the on-screen table and the PDF
    private function filteredApplicants()
    {
        return Applicant::query()
            ->active()
            ->byDateRange($this->dateFrom ?: null, $this->dateTo ?: null)
            ->byBarangay($this->barangayId ?: null)
            ->byEducation($this->educLevel ?: null)
            ->bySkillCategory($this->categoryId ?: null)
            ->when($this->sex, fn ($q) => $q->where('sex', $this->sex))
            ->with(['barangay.municipality', 'education', 'skills',
                    'workExperiences', 'trainings', 'certifications'])
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    public function generate(AuditLogService $audit)
    {
        abort_if(!auth()->user()->hasRole(['staff', 'admin']), 403);

        $this->validate([
            'dateFrom' => 'nullable|date',
            'dateTo'   => 'nullable|date|after_or_equal:dateFrom',
            'format'   => 'required|in:xlsx,csv,pdf',
        ]);

        $params = array_filter([
            'dateFrom'   => $this->dateFrom,
            'dateTo'     => $this->dateTo,
            'barangayId' => $this->barangayId,
            'categoryId' => $this->categoryId,
            'educLevel'  => $this->educLevel,
            'sex'        => $this->sex,
        ]);

        $audit->logReportDownloaded(json_encode($params));

        $stamp = now()->format('Ymd_His');

        if ($this->format === 'pdf') {
            return $this->downloadPdf($stamp);
        }

        $writerType = $this->format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        return Excel::download(
            new ApplicantsExport($params),
            'PESO_Catanduanes_Workforce_' . $stamp . '.' . $this->format,
            $writerType
        );
    }

    private function downloadPdf(string $stamp)
    {
        $applicants = $this->filteredApplicants()->get();
        $report     = $this->buildReport($applicants);

        $html = view('reports.workforce-report', ['r' => $report])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->stampFooters($dompdf);

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, 'PESO_Catanduanes_Workforce_' . $stamp . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    // Footer on every page: confidentiality notice and page numbers
    private function stampFooters(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $w      = $canvas->get_width();
        $h      = $canvas->get_height();
        $grey   = [0.35, 0.35, 0.35];

        $canvas->page_text(40, $h - 28,
            'CONFIDENTIAL. Contains personal data protected under RA 10173 (Data Privacy Act of 2012).',
            $font, 7, $grey);

        $canvas->page_text($w - 130, $h - 28,
            'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, $grey);
    }

    private function ageBracket(?int $age): string
    {
        return match (true) {
            $age === null => 'Not specified',
            $age < 18     => 'Under 18',
            $age <= 24    => '18-24',
            $age <= 34    => '25-34',
            $age <= 44    => '35-44',
            $age <= 54    => '45-54',
            $age <= 64    => '55-64',
            default       => '65 and over',
        };
    }

    private function buildReport(Collection $applicants): array
    {
        $total = $applicants->count();

        // Turns a label => count collection into rows with a share of all registrants
        $toRows = function (Collection $counts) use ($total): array {
            return $counts->map(fn ($n, $label) => [
                'label' => $label,
                'count' => $n,
                'pct'   => $total ? round($n / $total * 100, 1) : 0,
            ])->values()->all();
        };

        // Puts the keys of a collection in a fixed order, unknown values last
        $inOrder = function (Collection $c, array $order): Collection {
            $rank = fn ($v) => ($i = array_search($v, $order, true)) === false ? 999 : $i;
            return $c->sortKeysUsing(fn ($a, $b) => $rank($a) <=> $rank($b));
        };

        $sex = $applicants
            ->groupBy(fn ($a) => $a->sex ?: 'Not specified')
            ->map(fn ($g) => $g->count());

        $civil = $applicants
            ->groupBy(fn ($a) => $a->civil_status ?: 'Not specified')
            ->map(fn ($g) => $g->count());

        $status = $applicants
            ->groupBy(fn ($a) => $a->status ?: 'Not specified')
            ->map(fn ($g) => $g->count());

        $edu = $applicants
            ->groupBy(fn ($a) => $a->education?->highest_level ?: 'Not specified')
            ->map(fn ($g) => $g->count());

        $age = $applicants
            ->groupBy(fn ($a) => $this->ageBracket($a->birthdate?->age))
            ->map(fn ($g) => $g->count());

        $barangay = $applicants
            ->groupBy(fn ($a) => $a->barangay?->name ?: 'Unassigned')
            ->map(fn ($g) => $g->count())
            ->sortDesc();

        $disabilities = $applicants
            ->flatMap(fn ($a) => $a->disabilities ?? [])
            ->countBy()
            ->sortDesc();

        $skills = $applicants
            ->flatMap(fn ($a) => $a->skills->pluck('name')->unique()->values())
            ->countBy()
            ->sortDesc()
            ->take(20);

        $trend = $applicants
            ->groupBy(fn ($a) => $a->created_at->format('Y-m'))
            ->map(fn ($g) => $g->count())
            ->sortKeys();

        $trendRows = $trend->map(fn ($n, $ym) => [
            'label' => Carbon::createFromFormat('Y-m', $ym)->format('F Y'),
            'count' => $n,
            'pct'   => $total ? round($n / $total * 100, 1) : 0,
        ])->values()->all();

        $ages   = $applicants->map(fn ($a) => $a->birthdate?->age)->filter(fn ($x) => $x !== null);
        $avgAge = $ages->count() ? (int) round($ages->avg()) : null;

        $coverage = $total
            ? Carbon::createFromTimestamp($applicants->min(fn ($a) => $a->created_at->timestamp))->format('M d, Y')
              . ' to '
              . Carbon::createFromTimestamp($applicants->max(fn ($a) => $a->created_at->timestamp))->format('M d, Y')
            : 'No registrations match the selected filters';

        $filters = collect([
            'Registered from' => $this->dateFrom ?: null,
            'Registered to'   => $this->dateTo ?: null,
            'Barangay'        => $this->barangayId ? Barangay::find($this->barangayId)?->name : null,
            'Skill category'  => $this->categoryId ? SkillCategory::find($this->categoryId)?->name : null,
            'Education level' => $this->educLevel ?: null,
            'Sex'             => $this->sex ?: null,
        ])->filter()->map(fn ($v, $k) => "$k: $v")->values()->implode('; ');

        $list = $applicants->values()->map(fn ($a, $i) => [
            'no'         => $i + 1,
            'ref'        => $a->reference_id,
            'name'       => $a->full_name,
            'sex'        => $a->sex ?: 'NA',
            'age'        => $a->birthdate?->age ?? 'NA',
            'barangay'   => $a->barangay?->name ?: 'NA',
            'education'  => $a->education?->highest_level ?: 'NA',
            'status'     => $a->status ?: 'NA',
            'registered' => $a->created_at->format('M d, Y'),
        ])->all();

        return [
            'generatedAt' => now()->format('F d, Y, h:i A'),
            'preparedBy'  => auth()->user()->name,
            'filters'     => $filters ?: 'None (all registrations)',
            'coverage'    => $coverage,
            'total'       => $total,
            'male'        => $sex->get('Male', 0),
            'female'      => $sex->get('Female', 0),
            'avgAge'      => $avgAge,

            'sex'          => $toRows($inOrder($sex, ['Male', 'Female', 'Not specified'])),
            'civil'        => $toRows($inOrder($civil, ['Single', 'Married', 'Widowed', 'Separated', 'Not specified'])),
            'education'    => $toRows($inOrder($edu, ['Elementary', 'High School', 'Senior High School',
                                'Vocational/Technical', 'College Undergraduate', 'College Graduate',
                                'Post-Graduate', 'Not specified'])),
            'age'          => $toRows($inOrder($age, ['Under 18', '18-24', '25-34', '35-44', '45-54',
                                '55-64', '65 and over', 'Not specified'])),
            'status'       => $toRows($inOrder($status, ['Pending', 'Verified', 'Flagged', 'Duplicated', 'Not specified'])),
            'disabilities' => $toRows($disabilities),
            'skills'       => $toRows($skills),
            'barangays'    => $toRows($barangay),
            'trend'        => $trendRows,
            'list'         => $list,
        ];
    }

    public function render()
    {
        return view('livewire.report-generator', [
            'barangays'  => Barangay::orderBy('name')->pluck('name', 'id'),
            'categories' => SkillCategory::orderBy('name')->pluck('name', 'id'),
            'applicants' => $this->filteredApplicants()->paginate(15),
        ])->layout('layouts.app');
    }
}
