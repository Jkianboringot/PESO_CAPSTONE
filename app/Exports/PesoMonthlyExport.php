<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PesoMonthlyExport implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    // Lines before the first program row: 8 header lines, 1 blank, 2 table header rows
    private const HEADER_ROWS = 10;

    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'PESO Monthly Report';
    }

    public function array(): array
    {
        $rows = [
            ['Republic of the Philippines'],
            ['Department of Labor and Employment'],
            ['PUBLIC EMPLOYMENT SERVICE OFFICE - PROVINCE OF CATANDUANES'],
            ['PESO MONTHLY REPORT'],
            ['Name and Address of PESO:', 'PESO, Province of Catanduanes'],
            ['Reference Month / Year:', $this->data['monthYear']],
            ['Period covered:', $this->data['periodLabel']],
            [],
            ['PROGRAM', 'CURRENT REPORTING PERIOD', '', '', 'TOTAL as of this Period', '', ''],
            ['', 'MALE', 'FEMALE', 'TOTAL', 'MALE', 'FEMALE', 'TOTAL'],
        ];

        foreach ($this->data['rows'] as $row) {
            $label  = str_repeat('    ', $row['level']) . $row['label'];
            $values = $row['values'] === null ? ['', '', '', '', '', ''] : $row['values'];

            $rows[] = array_merge([$label], $values);
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A9:A10');
                $sheet->mergeCells('B9:D9');
                $sheet->mergeCells('E9:G9');
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 50,
            'B' => 12,
            'C' => 12,
            'D' => 12,
            'E' => 12,
            'F' => 12,
            'G' => 12,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $last = self::HEADER_ROWS + count($this->data['rows']);

        $sheet->getStyle('A1:G7')->getFont()->setBold(true);
        $sheet->getStyle('A9:G10')->getFont()->setBold(true);
        $sheet->getStyle('A9:G10')->getAlignment()
            ->setHorizontal('center')
            ->setVertical('center')
            ->setWrapText(true);

        $sheet->getStyle("A9:G{$last}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        $sheet->getStyle("B11:G{$last}")->getAlignment()->setHorizontal('center');

        return [];
    }
}
