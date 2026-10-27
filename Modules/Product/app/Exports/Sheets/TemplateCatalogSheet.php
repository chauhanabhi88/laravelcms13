<?php

declare(strict_types=1);

namespace Modules\Product\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Product\Exports\CategoryTemplateExport;
use Modules\Product\Support\Import\CatalogTemplate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 1: the heading row ("*" = required), the example row (Group ID "EXAMPLE", skipped on
 * import), a hint comment per heading and list dropdowns reading the Options sheet.
 */
class TemplateCatalogSheet implements FromArray, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(protected CatalogTemplate $template) {}

    public function title(): string
    {
        return CategoryTemplateExport::CATALOG_SHEET;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->template->headings();
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [array_values($this->template->exampleRow())];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E6F4F1']]],
            2 => ['font' => ['italic' => true, 'color' => ['rgb' => '64748B']]],
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = (int) config('product.bulk_max_rows', 5000) + 2;
                $optionColumns = CategoryTemplateExport::optionColumns($this->template);

                foreach ($this->template->columns() as $index => $column) {
                    $letter = Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->getColumnDimension($letter)->setWidth(max(14, min(40, mb_strlen($column['heading']) + 6)));

                    if ($column['hint'] !== '') {
                        $comment = $sheet->getComment($letter.'1');
                        $comment->getText()->createText($column['hint']);
                        $comment->setWidth('260pt')->setHeight('80pt');
                    }

                    if (! isset($optionColumns[$column['key']])) {
                        continue;
                    }

                    $optionLetter = Coordinate::stringFromColumnIndex($optionColumns[$column['key']]);
                    $strict = ! $column['multiple'] && $column['key'] !== 'brand';

                    $validation = new DataValidation;
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setAllowBlank(true)
                        ->setShowDropDown(true)
                        ->setShowInputMessage(false)
                        ->setShowErrorMessage($strict)
                        ->setErrorTitle(trans('product::seller.template.invalid_title'))
                        ->setError(trans('product::seller.template.invalid_value'))
                        ->setFormula1(sprintf("'%s'!\$%s\$2:\$%s\$%d", CategoryTemplateExport::OPTIONS_SHEET, $optionLetter, $optionLetter, count($column['options']) + 1));

                    $sheet->setDataValidation("{$letter}2:{$letter}{$lastRow}", $validation);
                }

                $sheet->freezePane('C2');
            },
        ];
    }
}
