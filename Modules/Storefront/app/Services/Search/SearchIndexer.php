<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttributeValue;
use Modules\Product\Models\ProductVariant;

/**
 * Maintains the denormalised search tables (FR-PRD-011): one product_search_index row per
 * live product per content locale, and its product_facet rows (specification options plus
 * the variant-axis options that still have stock). Products that are not live (or deleted)
 * are removed. Rows of a suspended seller's products stay, with is_visible = 2.
 */
class SearchIndexer
{
    protected const CHUNK = 200;

    /**
     * Locales the index is written in: the content languages plus the fallback locale.
     *
     * @return list<string>
     */
    public static function locales(): array
    {
        $fallback = (string) config('translatable.fallback_locale', config('app.fallback_locale', 'en'));

        return array_values(array_unique(array_merge(CatalogSettings::contentLocales(), [$fallback])));
    }

    /**
     * The index locale for the current request (the fallback when the app locale is not indexed).
     */
    public static function searchLocale(): string
    {
        $locale = app()->getLocale();
        $locales = self::locales();

        return in_array($locale, $locales, true) ? $locale : (string) config('translatable.fallback_locale', $locales[0] ?? 'en');
    }

    public function index(Product|int $product): void
    {
        $this->indexMany([$product instanceof Product ? $product->id : $product]);
    }

    /**
     * (Re)index products, removing those that are no longer live.
     *
     * @param  list<int>  $productIds
     * @return int number of products written to the index
     */
    public function indexMany(array $productIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $indexed = 0;

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $products = Product::query()->withTrashed()
                ->with([
                    'translations',
                    'brand.translations',
                    'attributeValues',
                    'variants' => fn ($query) => $query->where('status', config('core.enabled'))->with('attributeValues'),
                ])
                ->whereIn('id', $chunk)
                ->get();

            $live = $products->filter(fn (Product $product): bool => $product->status === ProductStatus::Live && ! $product->trashed());
            $gone = array_values(array_diff($chunk, $live->modelKeys()));

            if ($gone !== []) {
                $this->remove($gone);
            }

            if ($live->isNotEmpty()) {
                $this->write($live);
                $indexed += $live->count();
            }
        }

        return $indexed;
    }

    /**
     * @param  int|list<int>  $productIds
     */
    public function remove(int|array $productIds): void
    {
        $ids = array_values(array_filter(array_map('intval', (array) $productIds)));

        if ($ids === []) {
            return;
        }

        DB::table('product_search_index')->whereIn('product_id', $ids)->delete();
        DB::table('product_facet')->whereIn('product_id', $ids)->delete();
    }

    /**
     * Re-derive is_visible for a seller's rows after a suspension or reactivation (the
     * Product module has already updated product.seller_active).
     */
    public function syncSellerVisibility(int $sellerId): int
    {
        return DB::table('product_search_index as psi')
            ->join('product as p', 'p.id', '=', 'psi.product_id')
            ->where('psi.seller_id', $sellerId)
            ->update(['psi.is_visible' => DB::raw(sprintf(
                "CASE WHEN p.status = '%s' AND p.seller_active = %d AND p.deleted_at IS NULL THEN %d ELSE %d END",
                ProductStatus::Live->value,
                (int) config('core.yes'),
                (int) config('core.yes'),
                (int) config('core.no'),
            ))]);
    }

    /**
     * Copy moved category paths into the index (one statement for the whole tree).
     */
    public function syncCategoryPaths(): int
    {
        return DB::table('product_search_index as psi')
            ->join('catalog_category as c', 'c.id', '=', 'psi.catalog_category_id')
            ->whereColumn('psi.category_path', '<>', 'c.path')
            ->update(['psi.category_path' => DB::raw('c.path')]);
    }

    /**
     * Backfill: drop stale rows, then index every live product (optionally one seller's, or
     * one category subtree's) in chunks.
     *
     * @param  Closure(int): void|null  $progress  called with the number of products done
     * @return int products indexed
     */
    public function reindex(?int $sellerId = null, ?int $categoryId = null, ?Closure $progress = null): int
    {
        $categoryIds = null;
        if ($categoryId !== null) {
            $category = CatalogCategory::query()->withTrashed()->findOrFail($categoryId);
            $categoryIds = CatalogCategory::query()->withTrashed()->where('path', 'like', $category->path.'%')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        $stale = DB::table('product_search_index as psi')
            ->leftJoin('product as p', 'p.id', '=', 'psi.product_id')
            ->where(fn ($query) => $query->whereNull('p.id')->orWhere('p.status', '<>', ProductStatus::Live->value)->orWhereNotNull('p.deleted_at'))
            ->when($sellerId !== null, fn ($query) => $query->where('psi.seller_id', $sellerId))
            ->when($categoryIds !== null, fn ($query) => $query->whereIn('psi.catalog_category_id', $categoryIds))
            ->distinct()
            ->pluck('psi.product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach (array_chunk($stale, 1000) as $chunk) {
            $this->remove($chunk);
        }

        $done = 0;

        Product::query()
            ->where('status', ProductStatus::Live->value)
            ->when($sellerId !== null, fn ($query) => $query->where('seller_id', $sellerId))
            ->when($categoryIds !== null, fn ($query) => $query->whereIn('catalog_category_id', $categoryIds))
            ->select('id')
            ->chunkById(self::CHUNK, function (Collection $chunk) use (&$done, $progress): void {
                $done += $this->indexMany($chunk->modelKeys());

                if ($progress !== null) {
                    $progress($done);
                }
            });

        return $done;
    }

    /**
     * @param  Collection<int, Product>  $products  live products with their relations loaded
     */
    protected function write(Collection $products): void
    {
        $locales = self::locales();
        $fallback = (string) config('translatable.fallback_locale', 'en');

        $categories = CatalogCategory::query()->withTrashed()
            ->whereIn('id', $products->pluck('catalog_category_id')->unique()->all())
            ->get(['id', 'path'])
            ->keyBy('id');

        $pathIds = $categories->flatMap(fn (CatalogCategory $category): array => $category->pathIds())->unique()->values()->all();
        $categoryNames = $this->names('catalog_category_translation', 'catalog_category_id', $pathIds);

        $optionIds = $products->flatMap(fn (Product $product): array => array_merge(
            $product->attributeValues->pluck('attribute_option_id')->filter()->all(),
            $product->variants->flatMap(fn (ProductVariant $variant) => $variant->attributeValues->pluck('attribute_option_id'))->all(),
        ))->unique()->values()->all();
        $optionNames = $this->names('attribute_option_translation', 'attribute_option_id', $optionIds);

        $rows = [];
        $facets = [];

        foreach ($products as $product) {
            $category = $categories->get($product->catalog_category_id);
            $pathOfCategory = $category?->pathIds() ?? [$product->catalog_category_id];
            $productOptionIds = $this->optionIds($product);

            foreach ($locales as $locale) {
                $name = $this->translated($product->translations, $locale, $fallback, 'name') ?? $product->slug;
                $brand = $product->brand ? $this->translated($product->brand->translations, $locale, $fallback, 'name') : null;

                $parts = array_merge(
                    [$name, str_replace(',', ' ', (string) $product->search_keywords), $brand],
                    array_map(fn (int $id): ?string => $this->localised($categoryNames, $id, $locale, $fallback), $pathOfCategory),
                    array_map(fn (int $id): ?string => $this->localised($optionNames, $id, $locale, $fallback), $productOptionIds),
                    $product->attributeValues->pluck('value_text')->all(),
                );

                $rows[] = $this->row($product, $locale, $name, $parts, $category?->path);
            }

            foreach ($this->facetPairs($product) as [$attributeId, $optionId]) {
                $facets[] = ['product_id' => $product->id, 'attribute_id' => $attributeId, 'attribute_option_id' => $optionId];
            }
        }

        $ids = $products->modelKeys();

        DB::transaction(function () use ($rows, $facets, $ids, $locales): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('product_search_index')->upsert($chunk, ['product_id', 'locale'], array_keys(array_diff_key($chunk[0], array_flip(['product_id', 'locale']))));
            }

            DB::table('product_search_index')->whereIn('product_id', $ids)->whereNotIn('locale', $locales)->delete();
            DB::table('product_facet')->whereIn('product_id', $ids)->delete();

            foreach (array_chunk($facets, 1000) as $chunk) {
                DB::table('product_facet')->insert($chunk);
            }
        });
    }

    /**
     * @param  list<string|null>  $parts
     * @return array<string, mixed>
     */
    protected function row(Product $product, string $locale, string $name, array $parts, ?string $path): array
    {
        $price = $product->min_price;
        $mrp = $product->min_mrp;
        $discount = $mrp->isPositive() && $mrp->compare($price) > 0 ? intdiv(($mrp->paise - $price->paise) * 100, $mrp->paise) : 0;
        $yes = (int) config('core.yes');
        $no = (int) config('core.no');

        $document = collect($parts)
            ->map(fn (?string $part): string => trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $part))))
            ->filter(fn (string $part): bool => $part !== '')
            ->unique()
            ->implode(' ');

        return [
            'product_id' => $product->id,
            'locale' => $locale,
            'document' => mb_substr($document, 0, 60000),
            'name' => mb_substr($name, 0, 200),
            'catalog_category_id' => $product->catalog_category_id,
            'category_path' => (string) ($path ?? '/'.$product->catalog_category_id.'/'),
            'seller_id' => $product->seller_id,
            'catalog_brand_id' => $product->catalog_brand_id,
            'min_price' => $price->toDecimal(),
            'min_mrp' => $mrp->toDecimal(),
            'discount' => max(0, min(100, $discount)),
            'rating_avg' => number_format((float) $product->rating_avg, 2, '.', ''),
            'rating_count' => (int) $product->rating_count,
            'in_stock' => $product->total_stock > 0 ? $yes : $no,
            'is_cod_allowed' => $product->isCodAllowed() ? $yes : $no,
            'popularity' => (int) $product->sold_count * 3 + intdiv((int) $product->view_count, 100) + (int) $product->rating_count,
            'published_at' => $product->published_at?->toDateTimeString(),
            'is_visible' => $product->seller_active === $yes ? $yes : $no,
        ];
    }

    /**
     * Specification options, then every variant-axis option (named in the document even when
     * sold out, so a search for a colour still finds the product).
     *
     * @return list<int>
     */
    protected function optionIds(Product $product): array
    {
        return array_values(array_unique(array_merge(
            $product->attributeValues->pluck('attribute_option_id')->filter()->map(fn ($id): int => (int) $id)->all(),
            $product->variants->flatMap(fn (ProductVariant $variant) => $variant->attributeValues->pluck('attribute_option_id'))->map(fn ($id): int => (int) $id)->all(),
        )));
    }

    /**
     * Filterable (attribute, option) pairs: specification options and the axis options of
     * enabled variants with stock available.
     *
     * @return list<array{0: int, 1: int}>
     */
    protected function facetPairs(Product $product): array
    {
        $pairs = [];

        foreach ($product->attributeValues as $value) {
            /** @var ProductAttributeValue $value */
            if ($value->attribute_option_id !== null) {
                $pairs[$value->attribute_id.':'.$value->attribute_option_id] = [$value->attribute_id, $value->attribute_option_id];
            }
        }

        foreach ($product->variants as $variant) {
            if ($variant->available <= 0) {
                continue;
            }

            foreach ($variant->attributeValues as $value) {
                $pairs[$value->attribute_id.':'.$value->attribute_option_id] = [$value->attribute_id, $value->attribute_option_id];
            }
        }

        return array_values($pairs);
    }

    /**
     * Translation names keyed by id then locale.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, string>>
     */
    protected function names(string $table, string $foreignKey, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table($table)->whereIn($foreignKey, $chunk)->get([$foreignKey, 'locale', 'name']) as $row) {
                $names[(int) $row->{$foreignKey}][(string) $row->locale] = (string) $row->name;
            }
        }

        return $names;
    }

    /**
     * @param  array<int, array<string, string>>  $names
     */
    protected function localised(array $names, int $id, string $locale, string $fallback): ?string
    {
        $forId = $names[$id] ?? [];

        return $forId[$locale] ?? $forId[$fallback] ?? (reset($forId) ?: null);
    }

    /**
     * @param  Collection<int, Model>  $translations
     */
    protected function translated(Collection $translations, string $locale, string $fallback, string $field): ?string
    {
        $value = $translations->firstWhere('locale', $locale)?->{$field}
            ?? $translations->firstWhere('locale', $fallback)?->{$field}
            ?? $translations->first()?->{$field};

        return $value === null || $value === '' ? null : (string) $value;
    }
}
