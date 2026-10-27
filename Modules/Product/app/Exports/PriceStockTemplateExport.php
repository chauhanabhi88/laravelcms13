<?php

declare(strict_types=1);

namespace Modules\Product\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Modules\Product\Imports\PriceStockImport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Price & stock template (FR-SCT-005): Seller SKU, MRP, Price, Stock, pre-filled with the
 * seller's current variants. A blank cell leaves that value unchanged.
 */
class PriceStockTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    use Exportable;

    /**
     * @param  list<array{seller_sku: string, mrp: string, price: string, stock: int}>  $variants
     */
    public function __construct(protected array $variants) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return PriceStockImport::templateHeadings();
    }

    /**
     * @return list<list<string|int>>
     */
    public function array(): array
    {
        return array_map(fn (array $variant): array => [$variant['seller_sku'], $variant['mrp'], $variant['price'], $variant['stock']], $this->variants);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
