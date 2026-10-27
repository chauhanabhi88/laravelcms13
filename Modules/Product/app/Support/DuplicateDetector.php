<?php

declare(strict_types=1);

namespace Modules\Product\Support;

use Illuminate\Support\Collection;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

/**
 * Possible duplicate listings (FR-PRD-010): another seller's live product whose primary
 * image has the same content hash and whose name is at least
 * config('product.duplicate_name_similarity') % similar. Flags only; QC decides.
 */
class DuplicateDetector
{
    /**
     * @param  Collection<int, Product>  $products  with translations loaded
     * @return array<int, list<int>> product id => ids of possible duplicates
     */
    public function forProducts(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->map(fn (Product $product): int => $product->id)->all();

        $hashes = ProductImage::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('content_hash')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['product_id', 'attribute_option_id', 'content_hash'])
            ->groupBy('product_id')
            ->map(fn (Collection $images): ?string => ($images->firstWhere('attribute_option_id', null) ?? $images->first())?->content_hash)
            ->filter();

        if ($hashes->isEmpty()) {
            return [];
        }

        $candidates = Product::query()
            ->with('translations')
            ->select('product.*', 'product_image.content_hash as matched_hash')
            ->join('product_image', function ($join): void {
                $join->on('product_image.product_id', '=', 'product.id')
                    ->on('product_image.path', '=', 'product.primary_image');
            })
            ->whereIn('product_image.content_hash', $hashes->values()->unique()->all())
            ->where('product.status', ProductStatus::Live->value)
            ->whereNotIn('product.id', $productIds)
            ->get();

        $threshold = (float) config('product.duplicate_name_similarity', 85);
        $duplicates = [];

        foreach ($products as $product) {
            $hash = $hashes->get($product->id);
            $name = mb_strtolower((string) $product->name);

            if ($hash === null || $name === '') {
                continue;
            }

            foreach ($candidates as $candidate) {
                if ($candidate->matched_hash !== $hash || $candidate->seller_id === $product->seller_id) {
                    continue;
                }

                similar_text($name, mb_strtolower((string) $candidate->name), $percent);

                if ($percent >= $threshold) {
                    $duplicates[$product->id][] = $candidate->id;
                }
            }
        }

        return $duplicates;
    }
}
