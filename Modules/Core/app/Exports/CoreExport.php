<?php

namespace  Modules\Core\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class CoreExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    private $data;
    private $headers;
    private $columnOption;

    public function __construct($data, $headers, $columnOption = [])
    {
        $this->data = $data;
        $this->headers = $headers;
        $this->columnOption = $columnOption;
    }

    public function collection()
    {
        if (!$this->data) {
            return collect([]);
        }
        return collect($this->data);
    }

    public function headings(): array
    {
        if (!$this->headers) {
            return [];
        }
        return $this->headers;
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $cellRange = 'A1:' . chr(65 + (count($this->headers) - 1)) . '1';
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setSize(14)->setBold(true)->getColor()->applyFromArray(['rgb' => '000000']);
                $event->sheet->getDelegate()->getStyle($cellRange)->getFill()->applyFromArray(
                    [
                        'fillType' => Fill::FILL_SOLID,
                        // 'rotation' => 0,
                        'startColor' => [
                            'rgb' => '#FFFFFF'
                        ],
                    ]
                );
            },
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => 'mm/dd/yyyy',
        ];
    }
}
