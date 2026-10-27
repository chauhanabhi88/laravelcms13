<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Catalog\Support\ResolvedAttribute;
use Modules\Storefront\Support\StorefrontCache;

/**
 * Facet counts for a listing (FR-DSC-005). Each facet is counted over the filtered result
 * set without its own filter (standard multi-select behaviour); facets whose filter is not
 * active share one query. Results are plain arrays (no URLs), cached briefly per query.
 *
 * @phpstan-type Option array{id: int, name: string, count: int, hex: string|null}
 * @phpstan-type AttributeFacet array{id: int, code: string, name: string, is_colour: bool, options: list<Option>}
 */
class FacetService
{
    /**
     * Attribute input types whose values are options (and so can be filters).
     */
    protected const OPTION_TYPES = ['select', 'radio', 'multiselect', 'checkbox'];

    public function __construct(
        protected ProductSearch $search,
        protected CategoryAttributeResolver $attributeResolver,
        protected CategoryTreeService $tree,
        protected StorefrontCache $cache,
    ) {}

    /**
     * @return array{categories: array{node: array<string, mixed>|null, parent: array<string, mixed>|null, children: list<array{id: int, name: string, slug: string, count: int}>}, attributes: list<AttributeFacet>, brands: list<array{id: int, name: string, count: int}>, price: array{min: int, max: int, presets: list<array{from: int|null, to: int|null, count: int}>}, rating: list<array{value: int, count: int}>, discount: list<array{value: int, count: int}>, cod: int}
     */
    public function facets(ListingQuery $query): array
    {
        return $this->cache->remember(
            'listing',
            'facets.'.$query->cacheKey(),
            (int) config('storefront.facet_cache_ttl', 120),
            fn (): array => $this->build($query),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function build(ListingQuery $query): array
    {
        $mode = $this->search->textMode($query->q);

        return [
            'categories' => $this->categories($query, $mode),
            'attributes' => $this->attributes($query, $mode),
            'brands' => $this->brands($query, $mode),
        ] + $this->stats($query, $mode);
    }

    /**
     * Children of the current category (or the root categories) with product counts.
     *
     * @return array{node: array<string, mixed>|null, parent: array<string, mixed>|null, children: list<array{id: int, name: string, slug: string, count: int}>}
     */
    protected function categories(ListingQuery $query, string $mode): array
    {
        $nodes = $this->tree->nodesById();
        $enabled = (int) config('core.enabled');
        $node = $query->categoryId !== null ? ($nodes[$query->categoryId] ?? null) : null;
        $parent = $node !== null && $node['parent_id'] !== null ? ($nodes[$node['parent_id']] ?? null) : null;

        $children = array_filter($nodes, fn (array $candidate): bool => $candidate['parent_id'] === ($node['id'] ?? null) && $candidate['status'] === $enabled);

        if ($children === []) {
            return ['node' => $node, 'parent' => $parent, 'children' => []];
        }

        $base = $this->search->filtered($query, ['category'], $mode);
        if ($node !== null) {
            $base->where('psi.category_path', 'like', $node['path'].'%');
        }

        $leafCounts = $base->select('psi.category_path', DB::raw('COUNT(*) as aggregate'))->groupBy('psi.category_path')->pluck('aggregate', 'category_path');
        $level = $node !== null ? $node['depth'] + 1 : 0;
        $counts = [];

        foreach ($leafCounts as $path => $count) {
            $ids = array_values(array_filter(explode('/', (string) $path), 'strlen'));
            if (isset($ids[$level])) {
                $counts[(int) $ids[$level]] = ($counts[(int) $ids[$level]] ?? 0) + (int) $count;
            }
        }

        $result = [];
        foreach ($children as $child) {
            if (($counts[$child['id']] ?? 0) > 0) {
                $result[] = ['id' => $child['id'], 'name' => $child['name'], 'slug' => $child['slug'], 'count' => $counts[$child['id']]];
            }
        }

        return ['node' => $node, 'parent' => $parent, 'children' => $result];
    }

    /**
     * Filterable attributes of the category (or, without one, the attributes most common in
     * the results) with option counts.
     *
     * @return list<AttributeFacet>
     */
    protected function attributes(ListingQuery $query, string $mode): array
    {
        $definitions = $query->categoryId !== null
            ? $this->categoryAttributes($query->categoryId)
            : $this->commonAttributes($query, $mode);

        if ($definitions === []) {
            return [];
        }

        $counts = [];
        $shared = array_values(array_diff(array_keys($definitions), array_keys(array_filter($query->attributes))));

        if ($shared !== []) {
            $counts = $this->optionCounts($this->search->filtered($query, [], $mode), $shared);
        }

        foreach (array_keys(array_filter($query->attributes)) as $attributeId) {
            if (isset($definitions[$attributeId])) {
                $counts += $this->optionCounts($this->search->filtered($query, ['attr:'.$attributeId], $mode), [$attributeId]);
            }
        }

        $facets = [];
        foreach ($definitions as $attributeId => $definition) {
            $selected = $query->attributes[$attributeId] ?? [];
            $options = [];

            foreach ($definition['options'] as $option) {
                $count = $counts[$attributeId][$option['id']] ?? 0;

                if ($count > 0 || in_array($option['id'], $selected, true)) {
                    $options[] = $option + ['count' => $count];
                }
            }

            if ($options !== []) {
                $facets[] = [
                    'id' => $attributeId,
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'is_colour' => collect($options)->contains(fn (array $option): bool => $option['hex'] !== null),
                    'options' => $options,
                ];
            }
        }

        return $facets;
    }

    /**
     * @return array<int, array{code: string, name: string, options: list<array{id: int, name: string, hex: string|null}>}>
     */
    protected function categoryAttributes(int $categoryId): array
    {
        $definitions = [];

        foreach ($this->attributeResolver->filterable($categoryId) as $attribute) {
            /** @var ResolvedAttribute $attribute */
            if (! in_array($attribute->inputType, self::OPTION_TYPES, true)) {
                continue;
            }

            $definitions[$attribute->attributeId] = [
                'code' => $attribute->code,
                'name' => $attribute->name,
                'options' => array_map(fn (array $option): array => [
                    'id' => $option['id'],
                    'name' => $option['name'],
                    'hex' => $this->hex($option['code']),
                ], $attribute->options),
            ];
        }

        return $definitions;
    }

    /**
     * The attributes filterable in some category that occur most often in the results,
     * plus the ones already filtered on.
     *
     * @return array<int, array{code: string, name: string, options: list<array{id: int, name: string, hex: string|null}>}>
     */
    protected function commonAttributes(ListingQuery $query, string $mode): array
    {
        $except = array_map(fn (int $id): string => 'attr:'.$id, array_keys($query->attributes));
        $ids = $this->search->filtered($query, $except, $mode)
            ->join('product_facet as f', 'f.product_id', '=', 'psi.product_id')
            ->whereIn('f.attribute_id', DB::table('catalog_category_attribute')->where('is_filterable', config('core.yes'))->select('attribute_id'))
            ->select('f.attribute_id', DB::raw('COUNT(DISTINCT f.product_id) as aggregate'))
            ->groupBy('f.attribute_id')
            ->orderByDesc('aggregate')
            ->limit((int) config('storefront.facet_search_attribute_limit', 6))
            ->pluck('f.attribute_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $ids = array_values(array_unique(array_merge($ids, array_keys($query->attributes))));

        if ($ids === []) {
            return [];
        }

        $attributes = Attribute::query()
            ->with(['translations', 'attributeOption' => fn ($options) => $options->with('translations')->orderBy('sort_order')->orderBy('id')])
            ->whereIn('id', $ids)
            ->whereIn('input_type', self::OPTION_TYPES)
            ->get()
            ->keyBy('id');

        $definitions = [];
        foreach ($ids as $id) {
            $attribute = $attributes->get($id);

            if ($attribute === null) {
                continue;
            }

            $definitions[$id] = [
                'code' => (string) $attribute->code,
                'name' => (string) ($attribute->name ?? $attribute->code),
                'options' => $attribute->attributeOption->map(fn (AttributeOption $option): array => [
                    'id' => (int) $option->id,
                    'name' => (string) ($option->name ?? $option->custom_option),
                    'hex' => $this->hex($option->custom_option),
                ])->values()->all(),
            ];
        }

        return $definitions;
    }

    /**
     * @param  list<int>  $attributeIds
     * @return array<int, array<int, int>> attribute id => option id => products
     */
    protected function optionCounts(Builder $base, array $attributeIds): array
    {
        $rows = DB::table('product_facet as f')
            ->whereIn('f.attribute_id', $attributeIds)
            ->whereIn('f.product_id', $base->select('psi.product_id'))
            ->groupBy('f.attribute_id', 'f.attribute_option_id')
            ->get(['f.attribute_id', 'f.attribute_option_id', DB::raw('COUNT(*) as aggregate')]);

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->attribute_id][(int) $row->attribute_option_id] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * The 20 most frequent brands in the results, plus the selected ones.
     *
     * @return list<array{id: int, name: string, count: int}>
     */
    protected function brands(ListingQuery $query, string $mode): array
    {
        $base = $this->search->filtered($query, ['brand'], $mode)->whereNotNull('psi.catalog_brand_id');

        $counts = (clone $base)
            ->select('psi.catalog_brand_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('psi.catalog_brand_id')
            ->orderByDesc('aggregate')
            ->limit((int) config('storefront.facet_brand_limit', 20))
            ->pluck('aggregate', 'catalog_brand_id')
            ->all();

        $missing = array_diff($query->brandIds, array_keys($counts));
        if ($missing !== []) {
            $counts += (clone $base)->whereIn('psi.catalog_brand_id', $missing)
                ->select('psi.catalog_brand_id', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('psi.catalog_brand_id')
                ->pluck('aggregate', 'catalog_brand_id')
                ->all();

            foreach ($missing as $id) {
                $counts[$id] ??= 0;
            }
        }

        if ($counts === []) {
            return [];
        }

        $brands = CatalogBrand::query()->with('translations')->enabled()->whereIn('id', array_keys($counts))->get()->keyBy('id');
        $result = [];

        foreach ($counts as $id => $count) {
            if ($brand = $brands->get((int) $id)) {
                $result[] = ['id' => (int) $id, 'name' => (string) ($brand->name ?? $brand->slug), 'count' => (int) $count];
            }
        }

        return $result;
    }

    /**
     * Price range and presets, rating and discount buckets and the COD count. A facet with an
     * active filter is counted without it; the others share one query.
     *
     * @return array{price: array{min: int, max: int, presets: list<array{from: int|null, to: int|null, count: int}>}, rating: list<array{value: int, count: int}>, discount: list<array{value: int, count: int}>, cod: int}
     */
    protected function stats(ListingQuery $query, string $mode): array
    {
        $active = [
            'price' => $query->priceFrom !== null || $query->priceTo !== null,
            'rating' => $query->rating !== null,
            'discount' => $query->discount !== null,
            'cod' => $query->codOnly,
        ];

        $shared = null;
        $stats = [];

        foreach ($active as $facet => $isActive) {
            $stats[$facet] = $isActive
                ? $this->aggregate($this->search->filtered($query, [$facet], $mode))
                : ($shared ??= $this->aggregate($this->search->filtered($query, [], $mode)));
        }

        $presets = [];
        foreach ((array) config('storefront.price_presets') as $index => [$from, $to]) {
            $presets[] = ['from' => $from, 'to' => $to, 'count' => (int) ($stats['price']->{'p'.$index} ?? 0)];
        }

        return [
            'price' => [
                'min' => (int) floor((float) ($stats['price']->min_price ?? 0)),
                'max' => (int) ceil((float) ($stats['price']->max_price ?? 0)),
                'presets' => $presets,
            ],
            'rating' => array_map(fn ($value): array => ['value' => (int) $value, 'count' => (int) ($stats['rating']->{'r'.$value} ?? 0)], (array) config('storefront.rating_buckets')),
            'discount' => array_map(fn ($value): array => ['value' => (int) $value, 'count' => (int) ($stats['discount']->{'d'.$value} ?? 0)], (array) config('storefront.discount_buckets')),
            'cod' => (int) ($stats['cod']->cod ?? 0),
        ];
    }

    protected function aggregate(Builder $base): object
    {
        $columns = ['MIN(psi.min_price) as min_price', 'MAX(psi.min_price) as max_price'];
        $bindings = [];

        foreach ((array) config('storefront.price_presets') as $index => [$from, $to]) {
            $conditions = [];
            if ($from !== null) {
                $conditions[] = 'psi.min_price >= ?';
                $bindings[] = $from;
            }
            if ($to !== null) {
                $conditions[] = 'psi.min_price <= ?';
                $bindings[] = $to;
            }
            $columns[] = 'SUM(CASE WHEN '.($conditions === [] ? '1 = 1' : implode(' AND ', $conditions)).' THEN 1 ELSE 0 END) as p'.(int) $index;
        }

        foreach ((array) config('storefront.rating_buckets') as $value) {
            $columns[] = 'SUM(CASE WHEN psi.rating_avg >= ? THEN 1 ELSE 0 END) as r'.(int) $value;
            $bindings[] = (int) $value;
        }

        foreach ((array) config('storefront.discount_buckets') as $value) {
            $columns[] = 'SUM(CASE WHEN psi.discount >= ? THEN 1 ELSE 0 END) as d'.(int) $value;
            $bindings[] = (int) $value;
        }

        $columns[] = 'SUM(CASE WHEN psi.is_cod_allowed = ? THEN 1 ELSE 0 END) as cod';
        $bindings[] = (int) config('core.yes');

        return $base->selectRaw(implode(', ', $columns), $bindings)->first() ?? (object) [];
    }

    protected function hex(?string $code): ?string
    {
        return is_string($code) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $code) ? $code : null;
    }
}
