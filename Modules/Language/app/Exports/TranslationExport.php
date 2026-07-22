<?php

namespace  Modules\Language\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TranslationExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    private $data;

    public function __construct($data = null)
    {
        $this->data = $data;
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
        $languages = getLanguageOptions();
        $languages = array_flip($languages);
        $fileHeaders = [
            'module',
            'file',
            'key',
        ];
        $result = array_merge($fileHeaders, $languages);
        return $result;
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $cellRange = 'A1:Z1'; // All headers
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setSize(14)->setBold(true)->getColor()->applyFromArray(['rgb' => '000000']);
                // $event->sheet->getDelegate()->getStyle($cellRange)->getFill()->applyFromArray(
                //     [
                //         'fillType' => Fill::FILL_SOLID,
                //         // 'rotation' => 0,
                //         'startColor' => [
                //             'rgb' => '#000000'
                //         ],
                //     ]
                // );
            },
        ];
    }
}
