<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Product\Events\VariantLowStock;
use Modules\Product\Exceptions\InsufficientStock;
use Modules\Product\Exceptions\InvalidPricing;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

/**
 * Single-variant edits from the supplier inventory screen and the bulk price & stock upload
 * (FR-SCT-005/006). Price, stock, threshold and status apply at once, without QC, also on
 * live products (FR-PRD-005). Stock goes through InventoryService (ledgered); price edits are
 * checked by PricingService; product aggregates refresh after commit.
 *
 * Errors are ProductValidationException keyed by field (mrp, price, stock,
 * low_stock_threshold, status).
 */
class VariantQuickEditService
{
    public function __construct(
        protected PricingService $pricing,
        protected InventoryService $inventory,
        protected ProductAggregateService $aggregates,
    ) {}

    /**
     * Apply any of: mrp / price (null = unchanged), stock (counted value), low-stock threshold,
     * status. Validation runs before anything is written.
     *
     * @param  array{mrp?: string|null, price?: string|null, stock?: int|null, low_stock_threshold?: int|null, status?: int|null}  $changes
     *
     * @throws ProductValidationException
     */
    public function update(ProductVariant $variant, array $changes, ?Model $actor = null, ?string $note = null): ProductVariant
    {
        $product = Product::query()->withTrashed()->findOrFail($variant->product_id);
        $category = CatalogCategory::query()->findOrFail($product->catalog_category_id);

        $mrp = isset($changes['mrp']) && $changes['mrp'] !== '' ? Money::of((string) $changes['mrp']) : null;
        $price = isset($changes['price']) && $changes['price'] !== '' ? Money::of((string) $changes['price']) : null;
        $stock = $changes['stock'] ?? null;
        $threshold = $changes['low_stock_threshold'] ?? null;
        $status = $changes['status'] ?? null;

        $errors = [];

        if ($mrp !== null || $price !== null) {
            try {
                $this->pricing->validatePrices($price ?? $variant->price, $mrp ?? $variant->mrp, $category);
            } catch (InvalidPricing $e) {
                $errors[$e->field] = $e->getMessage();
            }
        }

        if ($stock !== null && ($stock < 0 || $stock > 1000000)) {
            $errors['stock'] = trans('product::product.validation.stock');
        } elseif ($stock !== null && $stock < $variant->reserved) {
            $errors['stock'] = trans('product::product.validation.stock_below_reserved', ['reserved' => $variant->reserved]);
        }

        if ($threshold !== null && ($threshold < 0 || $threshold > 100000)) {
            $errors['low_stock_threshold'] = trans('product::product.validation.threshold');
        }

        if ($status !== null && ! in_array($status, [(int) config('core.enabled'), (int) config('core.disabled')], true)) {
            $errors['status'] = trans('product::seller.inventory.messages.status_invalid');
        }

        if ($errors !== []) {
            throw new ProductValidationException($errors);
        }

        DB::transaction(function () use ($variant, $mrp, $price, $stock, $threshold, $status, $actor, $note): void {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            $thresholdBefore = $locked->low_stock_threshold;

            if ($mrp !== null) {
                $locked->mrp = $mrp;
            }

            if ($price !== null) {
                $locked->price = $price;
            }

            if ($threshold !== null) {
                $locked->low_stock_threshold = $threshold;
            }

            if ($status !== null) {
                $locked->status = $status;
            }

            if ($locked->isDirty()) {
                $locked->save();
                $productId = $locked->product_id;
                DB::afterCommit(fn () => $this->aggregates->refresh($productId));
            }

            // A higher threshold can put the variant into low stock without a stock change.
            if ($threshold !== null && $locked->available > $thresholdBefore && $locked->available <= $locked->low_stock_threshold) {
                event(new VariantLowStock($locked, $locked->available));
            }

            if ($stock !== null && $stock !== $locked->stock) {
                try {
                    $this->inventory->adjust($locked, $stock, $actor, $note ?? trans('product::seller.inventory.adjust_note'));
                } catch (InsufficientStock) {
                    throw new ProductValidationException(['stock' => trans('product::product.validation.stock_below_reserved', ['reserved' => $locked->reserved])]);
                }
            }
        });

        return ProductVariant::query()->findOrFail($variant->id);
    }
}
