<?php
namespace App\Exports;

use App\Models\Applicant;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ApplicantsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles {

    public function __construct(private array $params = []) {}

    public function query() {
        return Applicant::with(['barangay.municipality','education','skills.category'])
            ->active()
            ->byDateRange($this->params['dateFrom'] ?? null, $this->params['dateTo'] ?? null)
            ->byBarangay($this->params['barangayId'] ?? null)
            ->byEducation($this->params['educLevel']  ?? null)
            ->bySkillCategory($this->params['categoryId'] ?? null)
            ->when($this->params['sex'] ?? null, fn($q, $v) => $q->where('sex', $v))
            ->orderByDesc('created_at');
    }

    public function headings(): array {
        return [
            'Reference ID','Last Name','First Name','Middle Name',
            'Birthdate','Age','Sex','Civil Status',
            'Contact Number','Email','Address',
            'Barangay','Municipality',
            'Highest Education','Course/Program',
            'Skills','Skill Categories',
            'Registration Status','Date Registered',
        ];
    }

    public function map($applicant): array {
        return [
            $applicant->reference_id,
            $applicant->last_name,
            $applicant->first_name,
            $applicant->middle_name ?? '',
            $applicant->birthdate->format('Y-m-d'),
            $applicant->birthdate->age,
            $applicant->sex,
            $applicant->civil_status,
            $applicant->contact_number,
            $applicant->email ?? '',
            $applicant->address ?? '',
            $applicant->barangay?->name ?? '',
            $applicant->barangay?->municipality?->name ?? '',
            $applicant->education?->highest_level ?? '',
            $applicant->education?->course_program ?? '',
            $applicant->skills->pluck('name')->implode(' | '),
            $applicant->skills->pluck('category.name')->unique()->implode(' | '),
            $applicant->status,
            $applicant->created_at->format('Y-m-d'),
        ];
    }

    public function styles(Worksheet $sheet) {
        return [1 => ['font' => ['bold' => true, 'size' => 11]]];
    }
}
