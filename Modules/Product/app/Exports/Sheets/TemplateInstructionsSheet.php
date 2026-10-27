<?php

declare(strict_types=1);

namespace Modules\Product\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Product\Exports\CategoryTemplateExport;
use Modules\Product\Support\Import\CatalogTemplate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 2: how to fill the template, then every column with "required" and its hint.
 */
class TemplateInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(protected CatalogTemplate $template) {}

    public function title(): string
    {
        return CategoryTemplateExport::INSTRUCTIONS_SHEET;
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        $rows = [
            [trans('product::seller.template.instructions.title', ['category' => app(CategoryTreeService::class)->breadcrumb($this->template->category->id)])],
            [''],
        ];

        foreach ((array) trans('product::seller.template.instructions.steps', ['max' => config('product.bulk_max_rows'), 'variants' => config('product.max_variants'), 'separator' => CatalogTemplate::SEPARATOR]) as $step) {
            $rows[] = [(string) $step];
        }

        $rows[] = [''];
        $rows[] = [trans('product::seller.template.instructions.column'), trans('product::seller.template.instructions.required'), trans('product::seller.template.instructions.hint')];

        foreach ($this->template->columns() as $column) {
            $rows[] = [
                $column['heading'],
                $column['required'] ? trans('product::seller.template.yes') : trans('product::seller.template.no'),
                $column['hint'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 40, 'B' => 12, 'C' => 90];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 14]]];
    }
}
