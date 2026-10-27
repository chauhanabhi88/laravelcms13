<?php

declare(strict_types=1);

namespace Modules\Product\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Error report of a bulk upload: the rows that were not saved, as the seller uploaded them,
 * plus an "Errors" column. Fix the rows and upload the file again.
 */
class UploadErrorReportExport implements FromArray, WithHeadings, WithStyles
{
    use Exportable;

    /**
     * @param  list<string>  $headings  the uploaded heading row
     * @param  array<int, list<string>>  $rows  row number => cells, as uploaded
     * @param  array<int, list<string>>  $errors  row number => messages
     */
    public function __construct(
        protected array $headings,
        protected array $rows,
        protected array $errors,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [...$this->headings, trans('product::seller.uploads.errors_column')];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        $width = count($this->headings);
        $lines = [];

        foreach ($this->rows as $rowNumber => $cells) {
            $cells = array_pad(array_slice(array_values($cells), 0, $width), $width, '');
            $lines[] = [...$cells, implode(' | ', $this->errors[$rowNumber] ?? [])];
        }

        return $lines;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
