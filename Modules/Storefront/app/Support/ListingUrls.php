<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Modules\Core\Support\Money;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Services\Search\ListingResult;

/**
 * URLs of one listing page: pagination, sort, every filter toggle, the applied-filter chips
 * and the canonical URL (filters kept, page and sort dropped).
 */
final class ListingUrls
{
    public function __construct(
        protected ListingPage $page,
        protected ListingQuery $query,
    ) {}

    /**
     * @param  array<string, string|int|null>  $params
     */
    public function build(array $params): string
    {
        $params = array_filter($params, fn ($value): bool => $value !== null && $value !== '');

        return $this->page->baseUrl.($params !== [] ? '?'.http_build_query($params) : '');
    }

    /**
     * The current filters with some changed (null removes one); always back to page 1.
     *
     * @param  array<string, string|int|null>  $changes
     */
    public function with(array $changes): string
    {
        return $this->build(array_merge($this->query->urlParams(), $changes));
    }

    public function current(): string
    {
        return $this->pageUrl($this->query->page);
    }

    public function pageUrl(int $page): string
    {
        return $this->build($this->query->urlParams() + ($page > 1 ? ['page' => $page] : []));
    }

    public function next(ListingResult $result): ?string
    {
        return $result->hasMore ? $this->pageUrl($result->page + 1) : null;
    }

    public function previous(ListingResult $result): ?string
    {
        return $result->page > 1 ? $this->pageUrl($result->page - 1) : null;
    }

    /**
     * Partial-response URL for infinite scroll.
     */
    public function nextGrid(ListingResult $result): ?string
    {
        return $result->hasMore ? $this->build($this->query->urlParams() + ['page' => $result->page + 1, 'partial' => 'grid']) : null;
    }

    public function canonical(): string
    {
        return $this->build($this->query->urlParams(false));
    }

    public function sort(string $sort): string
    {
        return $this->with(['sort' => $sort === 'relevance' ? null : $sort]);
    }

    /**
     * Everything cleared except the search term.
     */
    public function clearAll(): string
    {
        return $this->build(['q' => $this->query->q]);
    }

    public function toggleAttribute(int $attributeId, int $optionId): string
    {
        $code = $this->query->attributeCodes[$attributeId] ?? null;
        $selected = $this->query->attributes[$attributeId] ?? [];

        if ($code === null) {
            return $this->current();
        }

        return $this->with([$code => $this->toggled($selected, $optionId)]);
    }

    public function toggleBrand(int $brandId): string
    {
        return $this->with(['brand' => $this->toggled($this->query->brandIds, $brandId)]);
    }

    public function price(?int $from, ?int $to): string
    {
        $isCurrent = $this->query->priceFrom === $from && $this->query->priceTo === $to;

        return $this->with(['price' => $isCurrent || ($from === null && $to === null) ? null : ($from ?? '').'-'.($to ?? '')]);
    }

    public function rating(int $value): string
    {
        return $this->with(['rating' => $this->query->rating === $value ? null : $value]);
    }

    public function discount(int $value): string
    {
        return $this->with(['discount' => $this->query->discount === $value ? null : $value]);
    }

    public function cod(): string
    {
        return $this->with(['cod' => $this->query->codOnly ? null : 1]);
    }

    /**
     * A child category: its own page on category listings (keeping the generic filters), a
     * filter elsewhere.
     *
     * @param  array{id: int, slug: string}  $category
     */
    public function category(array $category): string
    {
        if ($this->page->linksCategoriesToPages()) {
            $keep = array_intersect_key($this->query->urlParams(), array_flip(['price', 'rating', 'discount', 'cod', 'brand', 'sort']));

            return StorefrontUrls::category($category['slug']).($keep !== [] ? '?'.http_build_query($keep) : '');
        }

        return $this->with(['category' => $category['id']]);
    }

    /**
     * Applied-filter chips with the URL that removes each one.
     *
     * @param  array<string, mixed>  $facets  FacetService::facets()
     * @return list<array{label: string, url: string}>
     */
    public function chips(array $facets): array
    {
        $chips = [];

        if ($this->query->categoryIsFilter && ($node = $facets['categories']['node'] ?? null) !== null) {
            $chips[] = ['label' => (string) $node['name'], 'url' => $this->with(['category' => null])];
        }

        foreach ($facets['attributes'] ?? [] as $attribute) {
            foreach ($attribute['options'] as $option) {
                if (in_array($option['id'], $this->query->attributes[$attribute['id']] ?? [], true)) {
                    $chips[] = ['label' => $option['name'], 'url' => $this->toggleAttribute($attribute['id'], $option['id'])];
                }
            }
        }

        foreach ($facets['brands'] ?? [] as $brand) {
            if (in_array($brand['id'], $this->query->brandIds, true)) {
                $chips[] = ['label' => $brand['name'], 'url' => $this->toggleBrand($brand['id'])];
            }
        }

        if ($this->query->priceFrom !== null || $this->query->priceTo !== null) {
            $chips[] = ['label' => self::priceLabel($this->query->priceFrom, $this->query->priceTo), 'url' => $this->with(['price' => null])];
        }
        if ($this->query->rating !== null) {
            $chips[] = ['label' => trans('storefront::storefront.filters.rating_value', ['value' => $this->query->rating]), 'url' => $this->with(['rating' => null])];
        }
        if ($this->query->discount !== null) {
            $chips[] = ['label' => trans('storefront::storefront.filters.discount_value', ['value' => $this->query->discount]), 'url' => $this->with(['discount' => null])];
        }
        if ($this->query->codOnly) {
            $chips[] = ['label' => trans('storefront::storefront.filters.cod'), 'url' => $this->with(['cod' => null])];
        }

        return $chips;
    }

    public static function priceLabel(?int $from, ?int $to): string
    {
        $rupees = fn (int $amount): string => Money::of($amount)->format();

        return match (true) {
            $from === null && $to !== null => trans('storefront::storefront.filters.price_under', ['to' => $rupees($to)]),
            $from !== null && $to === null => trans('storefront::storefront.filters.price_over', ['from' => $rupees($from)]),
            default => trans('storefront::storefront.filters.price_between', ['from' => $rupees((int) $from), 'to' => $rupees((int) $to)]),
        };
    }

    /**
     * @param  list<int>  $selected
     */
    protected function toggled(array $selected, int $value): ?string
    {
        $values = in_array($value, $selected, true)
            ? array_values(array_diff($selected, [$value]))
            : array_merge($selected, [$value]);
        sort($values);

        return $values === [] ? null : implode(',', $values);
    }
}
