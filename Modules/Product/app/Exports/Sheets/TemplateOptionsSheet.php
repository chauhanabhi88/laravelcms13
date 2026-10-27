<?php

declare(strict_types=1);

namespace Modules\Product\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Product\Exports\CategoryTemplateExport;
use Modules\Product\Support\Import\CatalogTemplate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 3: one column per dropdown (brand, GST %, each attribute) listing its allowed values.
 */
class TemplateOptionsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(protected CatalogTemplate $template) {}

    public function title(): string
    {
        return CategoryTemplateExport::OPTIONS_SHEET;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        $headings = [];

        foreach (array_keys(CategoryTemplateExport::optionColumns($this->template)) as $key) {
            $headings[] = (string) $this->template->column($key)['heading'];
        }

        return $headings;
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        $lists = array_map(
            fn (string $key): array => $this->template->column($key)['options'],
            array_keys(CategoryTemplateExport::optionColumns($this->template)),
        );

        $height = $lists === [] ? 0 : max(array_map('count', $lists));
        $rows = [];

        for ($i = 0; $i < $height; $i++) {
            $rows[] = array_map(fn (array $list): string => (string) ($list[$i] ?? ''), $lists);
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
