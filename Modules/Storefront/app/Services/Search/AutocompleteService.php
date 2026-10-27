<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Storefront\Support\StorefrontCache;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * Search-box suggestions (FR-DSC-004), grouped: categories (with breadcrumb), brands,
 * product names (FULLTEXT prefix, then LIKE) and trending terms. An empty query returns
 * trending terms only (recent searches live in the browser). Cached per prefix.
 *
 * @phpstan-type Item array{label: string, sublabel: string|null, url: string, type: string}
 * @phpstan-type Group array{key: string, label: string, items: list<Item>}
 */
class AutocompleteService
{
    public function __construct(
        protected CategoryTreeService $tree,
        protected SearchAnalytics $analytics,
        protected StorefrontCache $cache,
    ) {}

    /**
     * @return array{query: string, groups: list<Group>}
     */
    public function suggest(?string $query): array
    {
        $query = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $query)));
        $query = mb_substr($query, 0, 60);
        $limits = (array) config('storefront.autocomplete_limits');

        if (mb_strlen($query) < (int) config('storefront.autocomplete_min_length', 2)) {
            return ['query' => $query, 'groups' => $this->groups([
                'trending' => $this->trendingItems((int) ($limits['trending_empty'] ?? 8), null),
            ])];
        }

        return $this->cache->remember(
            'listing',
            'suggest.'.md5(SearchIndexer::searchLocale().'|'.$query),
            (int) config('storefront.autocomplete_cache_ttl', 300),
            fn (): array => ['query' => $query, 'groups' => $this->groups([
                'categories' => $this->categories($query, (int) ($limits['categories'] ?? 4)),
                'brands' => $this->brands($query, (int) ($limits['brands'] ?? 3)),
                'products' => $this->products($query, (int) ($limits['products'] ?? 5)),
                'trending' => $this->trendingItems((int) ($limits['trending'] ?? 4), $query),
            ])],
        );
    }

    /**
     * @param  array<string, list<Item>>  $groups
     * @return list<Group>
     */
    protected function groups(array $groups): array
    {
        $result = [];

        foreach ($groups as $key => $items) {
            if ($items !== []) {
                $result[] = ['key' => $key, 'label' => trans('storefront::storefront.autocomplete.'.$key), 'items' => $items];
            }
        }

        return $result;
    }

    /**
     * Enabled categories whose name starts with (ranked first) or contains the query.
     *
     * @return list<Item>
     */
    protected function categories(string $query, int $limit): array
    {
        $enabled = (int) config('core.enabled');
        $nodes = $this->tree->nodesById();
        $matches = [];

        foreach ($nodes as $node) {
            $name = mb_strtolower($node['name']);
            $position = mb_strpos($name, $query);

            if ($position === false || ! $this->visible($node, $nodes, $enabled)) {
                continue;
            }

            $matches[] = [$position === 0 ? 0 : 1, $node['depth'], $node];
        }

        usort($matches, fn (array $a, array $b): int => [$a[0], $a[1], $a[2]['name']] <=> [$b[0], $b[1], $b[2]['name']]);

        return array_map(fn (array $match): array => [
            'label' => $match[2]['name'],
            'sublabel' => $match[2]['parent_id'] !== null ? $this->tree->breadcrumb((int) $match[2]['parent_id']) : null,
            'url' => StorefrontUrls::category($match[2]['slug']),
            'type' => 'category',
        ], array_slice($matches, 0, $limit));
    }

    /**
     * @return list<Item>
     */
    protected function brands(string $query, int $limit): array
    {
        return CatalogBrand::query()
            ->with('translations')
            ->enabled()
            ->whereHas('translations', fn ($translation) => $translation->where('name', 'like', TextQuery::like($query)))
            ->limit($limit * 3)
            ->get()
            ->sortBy(fn (CatalogBrand $brand): array => [str_starts_with(mb_strtolower((string) $brand->name), $query) ? 0 : 1, mb_strtolower((string) $brand->name)])
            ->take($limit)
            ->map(fn (CatalogBrand $brand): array => [
                'label' => (string) $brand->name,
                'sublabel' => trans('storefront::storefront.autocomplete.brand'),
                'url' => StorefrontUrls::search('', ['brand' => $brand->id]),
                'type' => 'brand',
            ])
            ->values()
            ->all();
    }

    /**
     * Distinct product names: FULLTEXT prefix match first, topped up with LIKE matches.
     *
     * @return list<Item>
     */
    protected function products(string $query, int $limit): array
    {
        $base = fn () => DB::table('product_search_index as psi')
            ->where('psi.locale', SearchIndexer::searchLocale())
            ->where('psi.is_visible', config('core.yes'));

        $names = [];
        $boolean = TextQuery::booleanPrefix($query);

        if ($boolean !== '') {
            $names = $base()
                ->whereRaw('MATCH(psi.name) AGAINST(? IN BOOLEAN MODE)', [$boolean])
                ->orderByDesc('psi.popularity')
                ->limit($limit * 3)
                ->pluck('psi.name')
                ->all();
        }

        if (count(array_unique($names)) < $limit) {
            $names = array_merge($names, $base()
                ->where('psi.name', 'like', TextQuery::like($query))
                ->orderByDesc('psi.popularity')
                ->limit($limit * 3)
                ->pluck('psi.name')
                ->all());
        }

        $unique = [];
        foreach ($names as $name) {
            $unique[mb_strtolower((string) $name)] ??= (string) $name;
        }

        return array_map(fn (string $name): array => [
            'label' => $name,
            'sublabel' => null,
            'url' => StorefrontUrls::search($name),
            'type' => 'product',
        ], array_slice(array_values($unique), 0, $limit));
    }

    /**
     * @return list<Item>
     */
    protected function trendingItems(int $limit, ?string $prefix): array
    {
        return array_map(fn (string $term): array => [
            'label' => $term,
            'sublabel' => null,
            'url' => StorefrontUrls::search($term),
            'type' => 'trending',
        ], $this->analytics->trending($limit, $prefix));
    }

    /**
     * A category is offered only when it and all of its ancestors are enabled.
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $nodes
     */
    protected function visible(array $node, array $nodes, int $enabled): bool
    {
        foreach (array_filter(explode('/', (string) $node['path']), 'strlen') as $id) {
            if (($nodes[(int) $id]['status'] ?? null) !== $enabled) {
                return false;
            }
        }

        return true;
    }
}
