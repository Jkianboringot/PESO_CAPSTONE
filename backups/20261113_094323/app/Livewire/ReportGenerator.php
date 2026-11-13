<?php

namespace App\Livewire;

use Livewire\Component;
use App\Exports\ApplicantsExport;
use App\Models\{Barangay, SkillCategory};
use App\Services\AuditLogService;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ReportGenerator extends Component
{
    public string $dateFrom   = '';
    public string $dateTo     = '';
    public string $barangayId = '';
    public string $categoryId = '';
    public string $educLevel  = '';
    public string $sex        = '';
    public string $format     = 'xlsx'; // xlsx, csv or pdf

    public function generate(AuditLogService $audit)
    {
        abort_if( // TODO-LATER - replace with a proper error message
            !auth()->user()->hasRole(['staff', 'admin']),
            403
        );

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

        $filename = 'PESO_Catanduanes_Workforce_' . now()->format('Ymd_His') . '.' . $this->format;

        $writerType = match ($this->format) {
            'pdf'   => ExcelWriter::DOMPDF,
            'csv'   => ExcelWriter::CSV,
            default => ExcelWriter::XLSX,
        };

        return Excel::download(new ApplicantsExport($params), $filename, $writerType);
    }

    public function render()
    {
        return view('livewire.report-generator', [
            'barangays'  => Barangay::orderBy('name')->pluck('name', 'id'),
            'categories' => SkillCategory::orderBy('name')->pluck('name', 'id'),
        ])->layout('layouts.app');
    }
}