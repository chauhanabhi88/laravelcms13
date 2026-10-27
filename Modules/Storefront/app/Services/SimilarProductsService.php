<?php

declare(strict_types=1);

namespace Modules\Storefront\Services;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Storefront\Services\Search\SearchIndexer;
use Modules\Storefront\Support\StorefrontCache;
use Modules\Storefront\Support\StorefrontSettings;

/**
 * "Similar products" and "More from this seller" on the product page (FR-PDP-009), read from
 * the search index: visible, in stock, never the product itself, most popular first. Ids are
 * cached per product (storefront.pdp.similar_cache_ttl); cards are built fresh.
 */
class SimilarProductsService
{
    public const SIMILAR = 'similar';

    public const SELLER = 'seller';

    public function __construct(
        protected StorefrontCache $cache,
        protected ProductCardPresenter $cards,
    ) {}

    /**
     * Same leaf category, min price within ± similar_price_band_percent of this product's.
     *
     * @return list<int>
     */
    public function similar(Product $product, ?int $limit = null): array
    {
        $limit ??= $this->defaultLimit();
        $band = StorefrontSettings::similarPriceBandPercent();
        $paise = $product->min_price->paise;
        $from = intdiv($paise * max(0, 100 - $band), 100) / 100;
        $to = intdiv($paise * (100 + $band), 100) / 100;

        return $this->remember(self::SIMILAR, $product, $limit, fn (): array => $this->ids(
            $this->baseQuery($product)
                ->where('catalog_category_id', $product->catalog_category_id)
                ->whereBetween('min_price', [$from, $to]),
            $limit,
        ));
    }

    /**
     * @return list<int>
     */
    public function moreFromSeller(Product $product, ?int $limit = null): array
    {
        $limit ??= $this->defaultLimit();

        return $this->remember(self::SELLER, $product, $limit, fn (): array => $this->ids(
            $this->baseQuery($product)->where('seller_id', $product->seller_id),
            $limit,
        ));
    }

    /**
     * Popular products of a category whatever their price: the suggestions on the "product
     * not available" page.
     *
     * @return list<int>
     */
    public function inCategory(int $categoryId, ?int $excludeProductId = null, ?int $limit = null): array
    {
        $limit ??= $this->defaultLimit();

        return $this->cache->remember(
            'pdp',
            'category.'.$categoryId.'.'.(int) $excludeProductId.'.'.$limit.'.'.SearchIndexer::searchLocale(),
            (int) config('storefront.pdp.similar_cache_ttl', 3600),
            fn (): array => $this->ids(
                DB::table('product_search_index')
                    ->where('locale', SearchIndexer::searchLocale())
                    ->where('is_visible', config('core.yes'))
                    ->where('in_stock', config('core.yes'))
                    ->where('catalog_category_id', $categoryId)
                    ->when($excludeProductId !== null, fn (Builder $query) => $query->where('product_id', '!=', $excludeProductId)),
                $limit,
            ),
        );
    }

    /**
     * Cards for one of the two rows (type = similar | seller).
     *
     * @return list<array<string, mixed>>
     */
    public function cards(Product $product, string $type, ?int $limit = null): array
    {
        return $this->visibleCards($type === self::SELLER ? $this->moreFromSeller($product, $limit) : $this->similar($product, $limit));
    }

    /**
     * Cards for cached ids, dropping products hidden since the ids were cached.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function visibleCards(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $visible = DB::table('product_search_index')
            ->where('locale', SearchIndexer::searchLocale())
            ->where('is_visible', config('core.yes'))
            ->whereIn('product_id', $ids)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->cards->present(array_values(array_intersect($ids, $visible)));
    }

    protected function baseQuery(Product $product): Builder
    {
        return DB::table('product_search_index')
            ->where('locale', SearchIndexer::searchLocale())
            ->where('is_visible', config('core.yes'))
            ->where('in_stock', config('core.yes'))
            ->where('product_id', '!=', $product->id);
    }

    /**
     * @return list<int>
     */
    protected function ids(Builder $query, int $limit): array
    {
        return $query->orderByDesc('popularity')
            ->orderByDesc('product_id')
            ->limit($limit)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  Closure(): list<int>  $callback
     * @return list<int>
     */
    protected function remember(string $type, Product $product, int $limit, Closure $callback): array
    {
        return $this->cache->remember(
            'pdp',
            $type.'.'.$product->id.'.'.$limit.'.'.SearchIndexer::searchLocale(),
            (int) config('storefront.pdp.similar_cache_ttl', 3600),
            $callback,
        );
    }

    protected function defaultLimit(): int
    {
        return (int) config('storefront.pdp.similar_limit', 12);
    }
}
