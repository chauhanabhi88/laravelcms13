<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Imports\PriceStockImport;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\SellerUser;

/**
 * Processes a price & stock upload (FR-SCT-005): one row per Seller SKU; a blank MRP, price
 * or stock leaves that value as it is. Each row applies at once through
 * VariantQuickEditService (prices checked by PricingService, stock ledgered by
 * InventoryService with the note "Bulk upload #id"); a bad row does not stop the others.
 */
class PriceStockImportService
{
    public function __construct(
        protected CatalogUploadService $uploads,
        protected VariantQuickEditService $quickEdit,
    ) {}

    public function process(CatalogUpload $upload): void
    {
        if (! $this->uploads->start($upload)) {
            return;
        }

        try {
            $this->run($upload);
        } catch (\Throwable $e) {
            Log::error('Price & stock upload failed', ['upload' => $upload->id, 'exception' => $e]);
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.processing'));
        }
    }

    protected function run(CatalogUpload $upload): void
    {
        $actor = $upload->seller_user_id !== null ? SellerUser::query()->find($upload->seller_user_id) : null;
        $reader = new PriceStockImport((int) config('product.bulk_max_rows', 5000));

        try {
            Excel::import($reader, $upload->file_path, $this->uploads->disk());
        } catch (\Throwable $e) {
            Log::warning('Price & stock upload unreadable', ['upload' => $upload->id, 'error' => $e->getMessage()]);
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.unreadable'));

            return;
        }

        if ($reader->tooManyRows()) {
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.too_many_rows', ['max' => config('product.bulk_max_rows')]), $reader->dataRowCount());

            return;
        }

        $map = $reader->columnMap();
        if (! in_array('seller_sku', $map, true)) {
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.missing_columns', ['columns' => trans('product::seller.template.columns.seller_sku')]));

            return;
        }

        $rows = $reader->keyedRows($map);
        if ($rows === []) {
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.empty'));

            return;
        }

        $variants = $this->variants($upload->seller_id, array_column($rows, 'seller_sku'));
        $note = trans('product::seller.price_stock.movement_note', ['id' => $upload->id]);
        $problems = [];
        $failedRows = [];
        $seen = [];
        $success = 0;

        foreach ($rows as $rowNumber => $row) {
            $errors = $this->applyRow($row, $variants, $seen, $actor, $note);

            if ($errors === []) {
                $success++;

                continue;
            }

            $problems[$rowNumber] = $errors;
            $failedRows[$rowNumber] = $reader->rows()[$rowNumber] ?? [];
        }

        $this->uploads->finish($upload, count($rows), $success, count($failedRows), $problems, $reader->headings(), $failedRows);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, ProductVariant>  $variants  lower-cased seller SKU => variant
     * @param  array<string, true>  $seen
     * @return list<array{column: string|null, message: string}>
     */
    protected function applyRow(array $row, array $variants, array &$seen, ?SellerUser $actor, string $note): array
    {
        $sku = trim($row['seller_sku'] ?? '');
        $key = Str::lower($sku);

        if ($sku === '') {
            return [['column' => 'seller_sku', 'message' => trans('product::seller.uploads.row_errors.required', ['column' => trans('product::seller.template.columns.seller_sku')])]];
        }

        if (isset($seen[$key])) {
            return [['column' => 'seller_sku', 'message' => trans('product::seller.price_stock.errors.repeated')]];
        }
        $seen[$key] = true;

        $variant = $variants[$key] ?? null;
        if ($variant === null) {
            return [['column' => 'seller_sku', 'message' => trans('product::seller.price_stock.errors.sku_unknown', ['sku' => $sku])]];
        }

        $changes = [];
        $errors = [];

        foreach (['mrp', 'price'] as $column) {
            $value = str_replace([',', '₹', ' '], '', $row[$column] ?? '');

            if ($value === '') {
                continue;
            }

            if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
                $changes[$column] = $value;
            } else {
                $errors[] = ['column' => $column, 'message' => trans('product::seller.uploads.row_errors.money')];
            }
        }

        $stock = str_replace([',', ' '], '', $row['stock'] ?? '');
        if ($stock !== '') {
            if (preg_match('/^\d{1,7}(\.0+)?$/', $stock)) {
                $changes['stock'] = (int) $stock;
            } else {
                $errors[] = ['column' => 'stock', 'message' => trans('product::seller.uploads.row_errors.integer')];
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        if ($changes === []) {
            return [];
        }

        try {
            $this->quickEdit->update($variant, $changes, $actor, $note);
        } catch (ProductValidationException $e) {
            foreach ($e->errors() as $field => $message) {
                $errors[] = ['column' => (string) $field, 'message' => $message];
            }
        }

        return $errors;
    }

    /**
     * The seller's variants (of products not deleted) for these SKUs.
     *
     * @param  list<string>  $skus
     * @return array<string, ProductVariant>
     */
    protected function variants(int $sellerId, array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map('trim', $skus))));
        $variants = [];

        foreach (array_chunk($skus, 1000) as $chunk) {
            ProductVariant::query()
                ->where('seller_id', $sellerId)
                ->whereIn('seller_sku', $chunk)
                ->whereHas('product')
                ->get()
                ->each(function (ProductVariant $variant) use (&$variants): void {
                    $variants[Str::lower($variant->seller_sku)] = $variant;
                });
        }

        return $variants;
    }
}
