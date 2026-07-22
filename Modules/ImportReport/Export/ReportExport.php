<?php
namespace Modules\ImportReport\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    protected $reports;

    public function __construct(array $reports)
    {
        $this->reports = $reports;
    }

    public function array(): array
    {
        return $this->reports;
    }
    public function headings(): array
    {
        return [
            'Product Name',
            'Product Code',
            'Product Brand',
            'Price',
            'Site',
           // 'Ref_Url'
        ];
    }

    public function columnFormats(): array
    {
        return [
            //'E' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $cellRange = 'A1:E1'; 
                $styles = [
                   
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FF000000']
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => [ 'argb' => 'FF000000' ],
                    ]
                ];
                $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styles)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
            },
        ];
    }
}
?>