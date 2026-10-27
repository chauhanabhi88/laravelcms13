<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Support\Collection;
use Modules\Core\Support\Money;
use Modules\Product\Events\ProductAggregatesChanged;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;

/**
 * Recomputes a product's denormalised listing columns from its enabled variants and
 * images: min / max price, the MRP of the cheapest variant, the best discount, available
 * stock and the primary image. Writes only changed columns (without touching updated_at)
 * and fires ProductAggregatesChanged for each product that changed.
 */
class ProductAggregateService
{
    public function __construct(protected PricingService $pricing) {}

    /**
     * @param  int|list<int>  $productIds
     * @return list<int> ids of the products whose aggregates changed
     */
    public function refresh(int|array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $productIds))));
        $changed = [];

        foreach (array_chunk($ids, 200) as $chunk) {
            $products = Product::query()->withTrashed()->whereIn('id', $chunk)
                ->get(['id', 'primary_image', 'min_price', 'max_price', 'min_mrp', 'max_discount_percent', 'total_stock']);

            $variants = ProductVariant::query()
                ->whereIn('product_id', $chunk)
                ->where('status', config('core.enabled'))
                ->get(['id', 'product_id', 'mrp', 'price', 'stock', 'reserved'])
                ->groupBy('product_id');

            $images = ProductImage::query()
                ->whereIn('product_id', $chunk)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'product_id', 'attribute_option_id', 'path'])
                ->groupBy('product_id');

            foreach ($products as $product) {
                $values = $this->compute($variants->get($product->id, new Collection), $images->get($product->id, new Collection));
                $dirty = $this->diff($product, $values);

                if ($dirty === []) {
                    continue;
                }

                Product::query()->withTrashed()->whereKey($product->id)->toBase()->update(array_intersect_key($values, array_flip($dirty)));
                $changed[] = $product->id;

                event(new ProductAggregatesChanged($product->id, $dirty));
            }
        }

        return $changed;
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants  enabled variants of one product
     * @param  Collection<int, ProductImage>  $images  ordered images of one product
     * @return array{primary_image: string|null, min_price: string, max_price: string, min_mrp: string, max_discount_percent: int, total_stock: int}
     */
    protected function compute(Collection $variants, Collection $images): array
    {
        $cheapest = null;
        $highest = null;
        $discount = 0;
        $stock = 0;

        foreach ($variants as $variant) {
            if ($cheapest === null || $variant->price->compare($cheapest->price) < 0) {
                $cheapest = $variant;
            }

            if ($highest === null || $variant->price->compare($highest) > 0) {
                $highest = $variant->price;
            }

            $discount = max($discount, $this->pricing->discountPercent($variant->price, $variant->mrp));
            $stock += $variant->available;
        }

        // General images first; a colour image is primary only when there is no general one.
        $primary = $images->first(fn (ProductImage $image): bool => $image->attribute_option_id === null) ?? $images->first();

        return [
            'primary_image' => $primary?->path,
            'min_price' => ($cheapest?->price ?? Money::zero())->toDecimal(),
            'max_price' => ($highest ?? Money::zero())->toDecimal(),
            'min_mrp' => ($cheapest?->mrp ?? Money::zero())->toDecimal(),
            'max_discount_percent' => min(100, $discount),
            'total_stock' => $stock,
        ];
    }

    /**
     * @param  array<string, string|int|null>  $values
     * @return list<string>
     */
    protected function diff(Product $product, array $values): array
    {
        $current = [
            'primary_image' => $product->primary_image,
            'min_price' => $product->min_price?->toDecimal() ?? '0.00',
            'max_price' => $product->max_price?->toDecimal() ?? '0.00',
            'min_mrp' => $product->min_mrp?->toDecimal() ?? '0.00',
            'max_discount_percent' => $product->max_discount_percent,
            'total_stock' => $product->total_stock,
        ];

        return array_values(array_keys(array_filter($values, fn ($value, string $key): bool => $value !== $current[$key], ARRAY_FILTER_USE_BOTH)));
    }
}
