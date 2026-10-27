<?php

declare(strict_types=1);

namespace Modules\Product\Imports;

use Modules\Product\Support\Import\CatalogTemplate;

/**
 * Rows of a price & stock upload: Seller SKU, MRP, Price, Stock (FR-SCT-005).
 */
class PriceStockImport extends SpreadsheetRowsImport
{
    public const COLUMNS = ['seller_sku', 'mrp', 'price', 'stock'];

    /**
     * Template headings, in column order.
     *
     * @return list<string>
     */
    public static function templateHeadings(): array
    {
        return array_map(fn (string $column): string => trans('product::seller.template.columns.'.$column).($column === 'seller_sku' ? '*' : ''), self::COLUMNS);
    }

    /**
     * Heading row → position => column key (headings match without "*" and case).
     *
     * @return array<int, string>
     */
    public function columnMap(): array
    {
        $byHeading = [];
        foreach (self::COLUMNS as $column) {
            $byHeading[CatalogTemplate::normalise(trans('product::seller.template.columns.'.$column))] = $column;
        }

        $map = [];
        foreach ($this->headings() as $position => $heading) {
            $key = $byHeading[CatalogTemplate::normalise($heading)] ?? null;

            if ($key !== null && ! in_array($key, $map, true)) {
                $map[$position] = $key;
            }
        }

        return $map;
    }
}
