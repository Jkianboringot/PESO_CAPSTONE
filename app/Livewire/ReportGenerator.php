<?php

namespace App\Livewire;

use Livewire\Component;
use App\Exports\ApplicantsExport;
use App\Models\{Barangay, SkillCategory};
use App\Services\AuditLogService;
use Maatwebsite\Excel\Facades\Excel;

class ReportGenerator extends Component
{
  
    public string $dateFrom     = '';
    public string $dateTo       = '';
    public string $barangayId   = '';
    public string $categoryId   = '';
    public string $educLevel    = '';
    public string $sex          = '';
    public string $format       = 'xlsx';  // xlsx or csv
 
    public function generate(AuditLogService $audit) {
        $this->validate([
            'dateFrom' => 'nullable|date',
            'dateTo'   => 'nullable|date|after_or_equal:dateFrom',
            'format'   => 'required|in:xlsx,csv',
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
 
        return Excel::download(new ApplicantsExport($params), $filename);
    }
 
    public function render() {
        return view('livewire.report-generator', [
            'barangays'  => Barangay::orderBy('name')->pluck('name', 'id'),
            'categories' => SkillCategory::orderBy('name')->pluck('name', 'id'),
        ])->layout('layouts.app');
    }

}
