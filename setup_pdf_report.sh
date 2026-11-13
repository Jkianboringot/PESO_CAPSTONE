#!/usr/bin/env bash
# setup_monthly_report.sh : adds the PESO Monthly Report as a second report type.
# Run from the Laravel project root (the folder containing "artisan").
set -euo pipefail

if [ ! -f artisan ]; then
    echo "ERROR: run this from the Laravel project root (where artisan is)."
    exit 1
fi

STAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="backups/$STAMP"

# write_file <path>  -- reads new content from stdin.
# If the file exists it is backed up first, then replaced. Otherwise it is created.
write_file() {
    local path="$1"
    mkdir -p "$(dirname "$path")"
    if [ -f "$path" ]; then
        mkdir -p "$BACKUP_DIR/$(dirname "$path")"
        cp "$path" "$BACKUP_DIR/$path"
        echo "replaced: $path  (backup: $BACKUP_DIR/$path)"
    else
        echo "created:  $path"
    fi
    cat > "$path"
}

# 1. Livewire component (replaces the whole file)
write_file app/Livewire/ReportGenerator.php <<'PHP'
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
    public string $reportType = 'listing'; // listing | monthly (monthly is produced as PDF)

    // Reset to page 1 whenever a filter changes (not when the format or report type changes)
    public function updated($property)
    {
        if (!in_array($property, ['format', 'reportType'], true)) {
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
            'dateFrom'   => 'nullable|date',
            'dateTo'     => 'nullable|date|after_or_equal:dateFrom',
            'format'     => 'required|in:xlsx,csv,pdf',
            'reportType' => 'required|in:listing,monthly',
        ]);

        $params = array_filter([
            'dateFrom'   => $this->dateFrom,
            'dateTo'     => $this->dateTo,
            'barangayId' => $this->barangayId,
            'categoryId' => $this->categoryId,
            'educLevel'  => $this->educLevel,
            'sex'        => $this->sex,
            'reportType' => $this->reportType,
        ]);

        $audit->logReportDownloaded(json_encode($params));

        $stamp = now()->format('Ymd_His');

        if ($this->format === 'pdf') {
            return $this->downloadPdf($stamp);
        }

        // Monthly report is PDF only; Excel and CSV always export the applicant listing
        $writerType = $this->format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        return Excel::download(
            new ApplicantsExport($params),
            'PESO_Catanduanes_Workforce_' . $stamp . '.' . $this->format,
            $writerType
        );
    }

    private function downloadPdf(string $stamp)
    {
        if ($this->reportType === 'monthly') {
            $html     = view('reports.peso-monthly-report', $this->buildMonthlyData())->render();
            $fileName = 'PESO_Monthly_Report_' . $stamp . '.pdf';
        } else {
            $applicants = $this->filteredApplicants()->get();
            $report     = $this->buildReport($applicants);
            $html       = view('reports.workforce-report', ['r' => $report])->render();
            $fileName   = 'PESO_Catanduanes_Workforce_' . $stamp . '.pdf';
        }

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
        }, $fileName, [
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

    // ───────── PESO Monthly Report ─────────

    private function buildMonthlyData(): array
    {
        $end = $this->dateTo
            ? Carbon::parse($this->dateTo)->endOfDay()
            : now()->endOfDay();

        $start = $this->dateFrom
            ? Carbon::parse($this->dateFrom)->startOfDay()
            : $end->copy()->startOfMonth();

        // Same non-date filters for both columns of the form
        $base = fn () => Applicant::query()
            ->active()
            ->byBarangay($this->barangayId ?: null)
            ->byEducation($this->educLevel ?: null)
            ->bySkillCategory($this->categoryId ?: null)
            ->when($this->sex, fn ($q) => $q->where('sex', $this->sex));

        $inPeriod = $base()->whereBetween('created_at', [$start, $end])->get(['sex']);
        $asOf     = $base()->where('created_at', '<=', $end)->get(['sex']);

        $tally = fn (Collection $c): array => [
            'male'   => $c->where('sex', 'Male')->count(),
            'female' => $c->where('sex', 'Female')->count(),
            'total'  => $c->count(),
        ];

        $period = $tally($inPeriod);
        $total  = $tally($asOf);

        return [
            'monthYear'   => $end->format('F Y'),
            'periodLabel' => $start->format('M d, Y') . ' to ' . $end->format('M d, Y'),
            'generatedAt' => now()->format('F d, Y, h:i A'),
            'preparedBy'  => auth()->user()->name,
            'rows'        => $this->monthlyRows($period, $total),
        ];
    }

    // Row layout follows the PESO Monthly Report form.
    // 'values' => null  : blank cells (section headings and subtotal rows)
    // 'values' => NA    : not stored in the registry
    // Only 2.1 Regular Program is filled from the registry.
    private function monthlyRows(array $p, array $t): array
    {
        $reg = [$p['male'], $p['female'], $p['total'], $t['male'], $t['female'], $t['total']];
        $na  = array_fill(0, 6, 'NA');

        $heading = fn (string $label) => ['label' => $label, 'level' => 0, 'values' => null];
        $parent  = fn (string $label, int $level) => ['label' => $label, 'level' => $level, 'values' => null];
        $item    = function (string $label, int $level, ?array $values = null) use ($na) {
            return ['label' => $label, 'level' => $level, 'values' => $values ?? $na];
        };

        return [
            $heading('A. PUBLIC EMPLOYMENT SERVICE'),

            $parent('1. Job Vacancies solicited/reported', 0),
            $parent('1.1 Regular Program', 1),
            $item('1.1.1 Local Employment', 2),
            $item('1.1.2 Overseas Employment', 2),
            $parent('1.2 SPES', 1),
            $item('1.2.1 Public Sector', 2),
            $item('1.2.2 Private Sector', 2),
            $item('1.3 WAP', 1),
            $parent('1.4 TUPAD 2020', 1),
            $item('1.4.1 Wage Employment', 2),
            $item('1.4.2 Self Employment', 2),

            $parent('2. Applicants Registered', 0),
            $item('2.1 Regular Program', 1, $reg),
            $item('2.2 SPES', 1),
            $item('2.3 WAP', 1),
            $parent('2.4 TUPAD 2020', 1),
            $item('2.4.1 Wage Employment', 2),
            $item('2.4.2 Self Employment', 2),
            $item('2.5 Retrenched/Displaced workers', 1),
            $item('2.6 Returning OFWs', 1),
            $item('2.7 Migratory Workers', 1),
            $item('2.8 Rural workers', 1),

            $parent('3. Applicants Referred', 0),
            $parent('3.1 Regular Program', 1),
            $item('3.1.1 Local Employment', 2),
            $item('3.1.2 Overseas Employment', 2),
            $item('3.1.3 Self-employment', 2),
            $item('3.1.4 Training', 2),
            $item('3.2 SPES', 1),
            $item('3.3 WAP', 1),
            $item('3.4 TUPAD 2020', 1),
            $item('3.5 Retrenched/Displaced workers', 1),
            $item('3.6 Returning OFWs', 1),
            $item('3.7 Migratory Workers', 1),
            $item('3.8 Rural workers', 1),

            $parent('4. Applicants Placed/Assisted', 0),
            $parent('4.1 Regular Program', 1),
            $item('4.1.1 Local Employment', 2),
            $item('4.1.2 Overseas Employment', 2),
            $item('4.1.3 Self-Employment', 2),
            $parent('4.1.4 Training', 2),
            $item('a. Basic', 3),
            $item('b. Upgrading', 3),
            $parent('4.2 SPES', 1),
            $item('4.2.1 Public Sector', 2),
            $item('4.2.2 Private Sector', 2),
            $item('4.3 WAP', 1),
            $parent('4.4 TUPAD 2020', 1),
            $item('4.4.1 Wage Employment', 2),
            $item('4.4.2 Self-Employment', 2),
            $item('4.5 Retrenched/Displaced workers', 1),
            $item('4.6 Returning OFWs', 1),
            $item('4.7 Migratory workers', 1),
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
PHP

# 2. Monthly report layout (new file)
write_file resources/views/reports/peso-monthly-report.blade.php <<'BLADE'
@php
    $logoPath = public_path('pesoLogoLogin.png');
    $logo = is_file($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 30px 48px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #000; line-height: 1.3; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td, th { border: 1px solid #000; padding: 4px 5px; vertical-align: middle; word-wrap: break-word; }
    th { font-size: 8px; font-weight: bold; text-align: center; }
    td.num { text-align: center; font-size: 8px; }
    td.lbl { font-size: 8.5px; }
    .noborder td { border: none; padding: 0; }
    .small { font-size: 7.5px; color: #333; }
    .note { font-size: 7.5px; color: #333; margin-top: 8px; }
    .sigline { border-bottom: 1px solid #000; height: 26px; }
    .sign td { border: none; padding: 0 12px 0 0; }
</style>
</head>
<body>

{{-- ═════════════ HEADER BLOCK ═════════════ --}}
<table>
    <tr>
        <td style="width:20%; text-align:center;">
            @if($logo)<img src="{{ $logo }}" alt="PESO" style="height:44px;"><br>@endif
            <b style="font-size:8px;">PESO MONTHLY REPORT</b>
        </td>
        <td style="width:58%; text-align:center;">
            Republic of the Philippines<br>
            Department of Labor and Employment<br>
            <b style="font-size:10px;">PUBLIC EMPLOYMENT SERVICE OFFICE</b><br>
            Province of Catanduanes &nbsp;&nbsp; LGU: ______________________
        </td>
        <td style="width:22%; text-align:center;">
            <div class="small">Reference Month / Year</div>
            <b style="font-size:10px;">{{ $monthYear }}</b>
        </td>
    </tr>
    <tr>
        <td colspan="3"><b>Name and Address of PESO:</b> PESO, Province of Catanduanes</td>
    </tr>
    <tr>
        <td colspan="3" class="small">Period covered: {{ $periodLabel }} &nbsp;&nbsp;|&nbsp;&nbsp; Generated: {{ $generatedAt }}</td>
    </tr>
</table>

{{-- ═════════════ MAIN GRID ═════════════ --}}
<table style="margin-top:8px;">
    <thead>
        <tr>
            <th rowspan="2" style="width:34%; text-align:left;">PROGRAM</th>
            <th colspan="3">CURRENT REPORTING PERIOD</th>
            <th colspan="3">TOTAL as of this Period</th>
        </tr>
        <tr>
            <th style="width:11%;">MALE</th>
            <th style="width:11%;">FEMALE</th>
            <th style="width:11%;">TOTAL</th>
            <th style="width:11%;">MALE</th>
            <th style="width:11%;">FEMALE</th>
            <th style="width:11%;">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td class="lbl"
                    style="padding-left: {{ 5 + $row['level'] * 12 }}px; font-weight: {{ $row['level'] === 0 ? 'bold' : 'normal' }};">
                    {{ $row['label'] }}
                </td>
                @for($i = 0; $i < 6; $i++)
                    <td class="num">
                        @if(is_array($row['values']))
                            {{ is_int($row['values'][$i]) ? number_format($row['values'][$i]) : $row['values'][$i] }}
                        @else
                            &nbsp;
                        @endif
                    </td>
                @endfor
            </tr>
        @endforeach
    </tbody>
</table>

<p class="note">
    NA = not recorded in the PESO registry system. Blank cells are section headings or subtotal rows.
    Counts come from the applicant registry for the filters chosen on the report screen.
</p>

{{-- ═════════════ SIGNATURES ═════════════ --}}
<table class="sign" style="margin-top:26px;">
    <tr>
        <td style="width:33%;">
            <div class="sigline"></div>
            <div class="small">Prepared by: {{ $preparedBy }}</div>
        </td>
        <td style="width:33%;">
            <div class="sigline"></div>
            <div class="small">Reviewed by</div>
        </td>
        <td style="width:34%;">
            <div class="sigline"></div>
            <div class="small">Noted by (PESO Manager)</div>
        </td>
    </tr>
</table>

</body>
</html>
BLADE

# Checks and reminders
php artisan view:clear >/dev/null 2>&1 || true
php artisan config:clear >/dev/null 2>&1 || true

echo ""
echo "Done. Backups of replaced files (if any): $BACKUP_DIR"
echo ""
echo "MANUAL STEP: add the Report Type dropdown to resources/views/livewire/report-generator.blade.php."
echo "Paste this block directly after the opening <div class=\"p-5\"> of the 'Configure Report Parameters' panel,"
echo "and before the <div class=\"grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4\"> line:"
echo ""
cat <<'SNIPPET'
            <div class="mb-5 max-w-sm">
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Report Type</label>
                <select wire:model="reportType"
                        class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        style="border-color: #d1d5db;">
                    <option value="listing">Applicant Listing (standard)</option>
                    <option value="monthly">PESO Monthly Employment Report</option>
                </select>
                <p class="text-xs mt-1" style="color: #64748b;">The monthly report downloads as PDF. Choose PDF in Export Format.</p>
            </div>
SNIPPET
echo ""
echo "Then reload the report page and test: Report Type = PESO Monthly Report, Export Format = PDF, Download Report."