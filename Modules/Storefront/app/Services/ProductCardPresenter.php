<?php

declare(strict_types=1);

namespace Modules\Storefront\Services;

use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Storefront\Support\StorefrontSettings;
use Modules\Storefront\Support\StorefrontUrls;
use Modules\Theme\Contracts\WishlistProvider;

/**
 * Product ids → card DTOs for <x-theme::product-card> (FR-DSC-008), in the given order.
 * Three queries for any number of cards: products (+ translations, brand, seller) and the
 * hover images; wishlist flags come from the bound WishlistProvider.
 *
 * @phpstan-type Card array{id: int, name: string, url: string, image: string|null, image_alt2: string|null, price: \Modules\Core\Support\Money, mrp: \Modules\Core\Support\Money, rating: float|null, rating_count: int, free_delivery: bool, cod: bool, badge: string|null, wishlisted: bool, in_stock: bool, slug: string}
 */
class ProductCardPresenter
{
    public function __construct(protected WishlistProvider $wishlist) {}

    /**
     * @param  list<int>  $productIds
     * @return list<Card>
     */
    public function present(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $products = Product::query()
            ->with(['translations', 'brand:id,is_verified', 'seller:id,rating_avg,rating_count'])
            ->whereIn('id', $productIds)
            ->get(['id', 'seller_id', 'catalog_brand_id', 'slug', 'primary_image', 'min_price', 'min_mrp', 'rating_avg', 'rating_count', 'is_cod_allowed', 'total_stock'])
            ->keyBy('id');

        $hoverImages = $this->hoverImages($products);
        $wishlisted = array_flip(array_map('strval', $this->wishlist->ids()));
        $freeDelivery = StorefrontSettings::freeDeliveryBadge();

        $cards = [];
        foreach ($productIds as $id) {
            $product = $products->get($id);

            if ($product !== null) {
                $cards[] = $this->card($product, $hoverImages[$id] ?? null, isset($wishlisted[(string) $id]), $freeDelivery);
            }
        }

        return $cards;
    }

    /**
     * @return Card
     */
    protected function card(Product $product, ?string $hoverImage, bool $wishlisted, bool $freeDelivery): array
    {
        $ratingCount = (int) $product->rating_count;

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => (string) ($product->name ?? $product->slug),
            'url' => StorefrontUrls::product($product->slug, $product->id),
            'image' => StorefrontUrls::productImage($product->primary_image),
            'image_alt2' => StorefrontUrls::productImage($hoverImage),
            'price' => $product->min_price,
            'mrp' => $product->min_mrp,
            'rating' => $ratingCount > 0 ? (float) $product->rating_avg : null,
            'rating_count' => $ratingCount,
            'free_delivery' => $freeDelivery,
            'cod' => $product->isCodAllowed(),
            'badge' => $this->badge($product),
            'wishlisted' => $wishlisted,
            'in_stock' => $product->total_stock > 0,
        ];
    }

    protected function badge(Product $product): ?string
    {
        $seller = $product->seller;
        $topSeller = (array) config('storefront.top_seller');

        if ($seller !== null && (int) $seller->rating_count >= (int) ($topSeller['min_ratings'] ?? 100) && (float) $seller->rating_avg >= (float) ($topSeller['min_rating'] ?? 4.3)) {
            return trans('storefront::storefront.card.top_seller');
        }

        if ($product->brand?->isVerified()) {
            return trans('storefront::storefront.card.verified_brand');
        }

        return null;
    }

    /**
     * The first general image after the primary one, per product.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, string>
     */
    protected function hoverImages(Collection $products): array
    {
        $primary = $products->mapWithKeys(fn (Product $product): array => [$product->id => $product->primary_image])->all();
        $hover = [];

        ProductImage::query()
            ->whereIn('product_id', array_keys($primary))
            ->whereNull('attribute_option_id')
            ->orderBy('product_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['product_id', 'path'])
            ->each(function (ProductImage $image) use ($primary, &$hover): void {
                if (! isset($hover[$image->product_id]) && $image->path !== $primary[$image->product_id]) {
                    $hover[$image->product_id] = $image->path;
                }
            });

        return $hover;
    }
}
