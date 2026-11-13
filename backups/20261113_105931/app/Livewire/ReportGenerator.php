<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Exports\ApplicantsExport;
use App\Models\{Applicant, Barangay, SkillCategory};
use App\Services\AuditLogService;
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

    // Same filters as the export, applied to the on-screen table
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
        // (unchanged from your current version)
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
            'applicants' => $this->filteredApplicants()->paginate(15),
        ])->layout('layouts.app');
    }
}
