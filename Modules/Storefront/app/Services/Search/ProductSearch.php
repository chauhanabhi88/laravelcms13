<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Services\ProductCardPresenter;
use Modules\Storefront\Support\StorefrontCache;
use Modules\Storefront\Support\StorefrontSettings;

/**
 * MySQL listing engine over product_search_index (FR-DSC-002/003/005/006).
 *
 * Text search tries, per term: natural-language FULLTEXT (ranked) → boolean prefix
 * ("+kur* +cot*") → LIKE on the name. The first strategy with any match is remembered for a
 * few minutes, so filters, counts and facets of one query all use the same strategy.
 *
 * Filters: category subtree (path prefix), seller, brands, price range, rating, discount,
 * COD, attribute options (OR within an attribute, AND across attributes), plus collection
 * constraints. In-stock products always sort first.
 */
class ProductSearch implements ProductSearchEngine
{
    public const MODE_NONE = 'none';

    public const MODE_NATURAL = 'natural';

    public const MODE_BOOLEAN = 'boolean';

    public const MODE_LIKE = 'like';

    public function __construct(
        protected ProductCardPresenter $cards,
        protected StorefrontCache $cache,
    ) {}

    public function run(ListingQuery $query, bool $withTotal = true): ListingResult
    {
        $mode = $this->textMode($query->q);
        $builder = $this->filtered($query, [], $mode);
        $this->applySort($builder, $query, $mode);

        // One extra row tells whether another page follows.
        $ids = $builder->select('psi.product_id')
            ->offset(($query->page - 1) * $query->perPage)
            ->limit($query->perPage + 1)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $hasMore = count($ids) > $query->perPage;
        $ids = array_slice($ids, 0, $query->perPage);

        $total = null;
        if ($withTotal) {
            $total = $query->page === 1 && ! $hasMore ? count($ids) : $this->count($query);
        }

        return new ListingResult($ids, $this->cards->present($ids), $total, $query->page, $query->perPage, $hasMore, $mode);
    }

    public function count(ListingQuery $query): int
    {
        return (int) $this->cache->remember(
            'listing',
            'count.'.$query->cacheKey(),
            (int) config('storefront.count_cache_ttl', 60),
            fn (): int => $this->filtered($query, [], $this->textMode($query->q))->count(),
        );
    }

    /**
     * The filtered result set as an unsorted query on "psi", without some of the shopper's
     * filters: "category", "brand", "price", "rating", "discount", "cod" or "attr:{id}".
     * Facet counts use this to leave out the facet's own filter.
     *
     * @param  list<string>  $except
     */
    public function filtered(ListingQuery $query, array $except = [], ?string $mode = null): Builder
    {
        $mode ??= $this->textMode($query->q);
        $yes = (int) config('core.yes');

        $builder = DB::table('product_search_index as psi')
            ->where('psi.locale', SearchIndexer::searchLocale())
            ->where('psi.is_visible', $yes);

        if (! StorefrontSettings::showOutOfStock()) {
            $builder->where('psi.in_stock', $yes);
        }

        $this->applyText($builder, $query->q, $mode);
        $this->applyConstraints($builder, $query->constraints);

        if ($query->categoryPath !== null && ! ($query->categoryIsFilter && in_array('category', $except, true))) {
            $builder->where('psi.category_path', 'like', $query->categoryPath.'%');
        }

        if ($query->sellerId !== null) {
            $builder->where('psi.seller_id', $query->sellerId);
        }

        if ($query->brandIds !== [] && ! in_array('brand', $except, true)) {
            $builder->whereIn('psi.catalog_brand_id', $query->brandIds);
        }

        if (! in_array('price', $except, true)) {
            if ($query->priceFrom !== null) {
                $builder->where('psi.min_price', '>=', $query->priceFrom);
            }
            if ($query->priceTo !== null) {
                $builder->where('psi.min_price', '<=', $query->priceTo);
            }
        }

        if ($query->rating !== null && ! in_array('rating', $except, true)) {
            $builder->where('psi.rating_avg', '>=', $query->rating);
        }

        if ($query->discount !== null && ! in_array('discount', $except, true)) {
            $builder->where('psi.discount', '>=', $query->discount);
        }

        if ($query->codOnly && ! in_array('cod', $except, true)) {
            $builder->where('psi.is_cod_allowed', $yes);
        }

        foreach ($query->attributes as $attributeId => $optionIds) {
            if ($optionIds === [] || in_array('attr:'.$attributeId, $except, true)) {
                continue;
            }

            $builder->whereExists(fn (Builder $exists) => $exists->select(DB::raw(1))
                ->from('product_facet as f')
                ->whereColumn('f.product_id', 'psi.product_id')
                ->where('f.attribute_id', $attributeId)
                ->whereIn('f.attribute_option_id', $optionIds));
        }

        return $builder;
    }

    /**
     * Which full-text strategy matches the term (remembered per term and locale).
     */
    public function textMode(?string $q): string
    {
        if ($q === null || TextQuery::tokens($q) === []) {
            return self::MODE_NONE;
        }

        if (TextQuery::indexableTokens($q) === []) {
            return self::MODE_LIKE;
        }

        return (string) $this->cache->remember(
            'listing',
            'mode.'.md5(SearchIndexer::searchLocale().'|'.mb_strtolower($q)),
            (int) config('storefront.text_mode_cache_ttl', 300),
            function () use ($q): string {
                foreach ([self::MODE_NATURAL, self::MODE_BOOLEAN] as $mode) {
                    $probe = DB::table('product_search_index as psi')
                        ->where('psi.locale', SearchIndexer::searchLocale())
                        ->where('psi.is_visible', config('core.yes'));
                    $this->applyText($probe, $q, $mode);

                    if ($probe->exists()) {
                        return $mode;
                    }
                }

                return self::MODE_LIKE;
            },
        );
    }

    protected function applyText(Builder $builder, ?string $q, string $mode): void
    {
        match ($mode) {
            self::MODE_NATURAL => $builder->whereRaw('MATCH(psi.document) AGAINST(? IN NATURAL LANGUAGE MODE)', [TextQuery::natural($q)]),
            self::MODE_BOOLEAN => $builder->whereRaw('MATCH(psi.document) AGAINST(? IN BOOLEAN MODE)', [TextQuery::booleanPrefix($q)]),
            self::MODE_LIKE => $builder->where(function (Builder $where) use ($q): void {
                foreach (TextQuery::tokens($q) as $token) {
                    $where->where('psi.name', 'like', TextQuery::like($token));
                }
            }),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    protected function applyConstraints(Builder $builder, array $constraints): void
    {
        $paths = array_values(array_filter((array) ($constraints['category_paths'] ?? []), 'is_string'));
        if ($paths !== []) {
            $builder->where(function (Builder $where) use ($paths): void {
                foreach ($paths as $path) {
                    $where->orWhere('psi.category_path', 'like', $path.'%');
                }
            });
        }

        if (is_numeric($constraints['max_price'] ?? null)) {
            $builder->where('psi.min_price', '<=', $constraints['max_price']);
        }
        if (is_numeric($constraints['min_discount'] ?? null)) {
            $builder->where('psi.discount', '>=', (int) $constraints['min_discount']);
        }
        if (is_numeric($constraints['min_rating'] ?? null)) {
            $builder->where('psi.rating_avg', '>=', $constraints['min_rating']);
        }

        foreach (['brand_ids' => 'psi.catalog_brand_id', 'seller_ids' => 'psi.seller_id', 'product_ids' => 'psi.product_id'] as $key => $column) {
            if (! array_key_exists($key, $constraints) || ! is_array($constraints[$key])) {
                continue;
            }

            $ids = array_values(array_filter(array_map('intval', $constraints[$key])));

            if ($ids !== []) {
                $builder->whereIn($column, $ids);
            } elseif ($key === 'product_ids') {
                // An empty manual collection matches nothing rather than everything.
                $builder->whereRaw('1 = 0');
            }
        }
    }

    protected function applySort(Builder $builder, ListingQuery $query, string $mode): void
    {
        $builder->orderBy('psi.in_stock');

        $productIds = $query->constraints['product_ids'] ?? null;
        $manualOrder = is_array($productIds) && $productIds !== [] && $query->sort === 'relevance';

        match (true) {
            $manualOrder => $builder->orderByRaw('FIELD(psi.product_id, '.implode(',', array_map('intval', $productIds)).')'),
            $query->sort === 'new' => $builder->orderByDesc('psi.published_at'),
            $query->sort === 'price_asc' => $builder->orderBy('psi.min_price'),
            $query->sort === 'price_desc' => $builder->orderByDesc('psi.min_price'),
            $query->sort === 'rating' => $builder
                ->orderByRaw('(psi.rating_count >= ?) DESC', [(int) config('storefront.rating_sort_min_count', 5)])
                ->orderByDesc('psi.rating_avg')
                ->orderByDesc('psi.rating_count'),
            $query->sort === 'discount' => $builder->orderByDesc('psi.discount'),
            $mode === self::MODE_NATURAL => $builder
                ->orderByRaw('MATCH(psi.document) AGAINST(? IN NATURAL LANGUAGE MODE) DESC', [TextQuery::natural($query->q)])
                ->orderByDesc('psi.popularity'),
            $mode === self::MODE_BOOLEAN => $builder
                ->orderByRaw('MATCH(psi.document) AGAINST(? IN BOOLEAN MODE) DESC', [TextQuery::booleanPrefix($query->q)])
                ->orderByDesc('psi.popularity'),
            default => $builder->orderByDesc('psi.popularity'),
        };

        $builder->orderByDesc('psi.product_id');
    }
}
