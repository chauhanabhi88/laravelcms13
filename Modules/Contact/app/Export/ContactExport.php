<?php
namespace Modules\Contact\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ContactExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    protected $contacts;

    public function __construct(array $contacts)
    {
        $this->contacts = $contacts;
    }

    public function array(): array
    {
        return $this->contacts;
    }
    public function headings(): array
    {
        return [
            'Name',
            'E-mail',
            'Contact No.',
            'Enquiry',
            'Created at'
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $cellRange = 'A1:E1'; 
                $styles = [
                    // 'borders' => [
                    //     'outline' => [
                    //         'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                    //         'color' => ['argb' => 'FFFF0000'],
                    //     ],
                    // ]
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FF000000']
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => [ 'argb' => 'FF000000' ],
                    ]
                    // 'fill' => [
                    //     'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                    //     'rotation' => 90,
                    //     'startColor' => [
                    //         'argb' => 'FFA0A0A0',
                    //     ],
                    //     'endColor' => [
                    //         'argb' => 'FFFFFFFF',
                    //     ],
                    // ]
                ];
                // $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setSize(14);
                $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styles)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
                // $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styles);
            },
        ];
    }
}
?>