<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Services\CategoryTreeService;

/**
 * Everything that shapes a listing: the text query, the page context (category, seller,
 * collection constraints), the shopper's filters, sort and page. Parsed from whitelisted
 * request parameters (FR-DSC-009):
 *
 *   /c/women-kurtis?size=12,13&colour=40&price=199-499&rating=4&discount=30&cod=1&brand=3&sort=price_asc&page=2
 *
 * Attribute filters use attribute.code as the parameter name; values may also arrive as
 * arrays (size[]=12&size[]=13) from the no-JS filter form.
 *
 * @phpstan-type Constraints array{category_paths?: list<string>, max_price?: int|float|null, min_discount?: int|null, min_rating?: int|float|null, brand_ids?: list<int>, seller_ids?: list<int>, product_ids?: list<int>}
 */
final readonly class ListingQuery
{
    /**
     * Parameters that are never attribute codes.
     */
    public const RESERVED = ['q', 'sort', 'page', 'price', 'price_from', 'price_to', 'rating', 'discount', 'cod', 'brand', 'category', 'partial', 'locale', 'sq'];

    protected const MAX_VALUES = 50;

    /**
     * @param  list<int>  $brandIds
     * @param  array<int, list<int>>  $attributes  attribute id => option ids (OR within, AND across)
     * @param  array<int, string>  $attributeCodes  attribute id => code (URL parameter name)
     * @param  Constraints  $constraints  fixed rules of a collection (never shown as filters)
     */
    public function __construct(
        public ?string $q = null,
        public ?int $categoryId = null,
        public ?string $categoryPath = null,
        public bool $categoryIsFilter = false,
        public ?int $sellerId = null,
        public array $brandIds = [],
        public ?int $priceFrom = null,
        public ?int $priceTo = null,
        public ?int $rating = null,
        public ?int $discount = null,
        public bool $codOnly = false,
        public array $attributes = [],
        public array $attributeCodes = [],
        public array $constraints = [],
        public string $sort = 'relevance',
        public int $page = 1,
        public int $perPage = 40,
    ) {}

    /**
     * @param  array<string, mixed>  $fixed  page context (categoryId, categoryPath, sellerId, constraints, sort…)
     */
    public static function fromRequest(Request $request, array $fixed = []): self
    {
        $input = $request->query();
        $q = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['q'] ?? '')));
        $q = $q === '' ? null : mb_substr($q, 0, 120);

        [$priceFrom, $priceTo] = self::priceRange($input);

        $values = [
            'q' => $q,
            'brandIds' => self::ids($input['brand'] ?? null),
            'priceFrom' => $priceFrom,
            'priceTo' => $priceTo,
            'rating' => self::bucket($input['rating'] ?? null, (array) config('storefront.rating_buckets')),
            'discount' => self::bucket($input['discount'] ?? null, (array) config('storefront.discount_buckets')),
            'codOnly' => filter_var($input['cod'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort' => self::sortValue($input['sort'] ?? null, $fixed['sort'] ?? 'relevance'),
            'page' => max(1, (int) (is_numeric($input['page'] ?? null) ? $input['page'] : 1)),
            'perPage' => (int) config('storefront.per_page', 40),
        ];

        if (! array_key_exists('categoryId', $fixed) && is_numeric($input['category'] ?? null)) {
            $node = app(CategoryTreeService::class)->nodesById()[(int) $input['category']] ?? null;

            if ($node !== null && $node['status'] === (int) config('core.enabled')) {
                $values += ['categoryId' => $node['id'], 'categoryPath' => $node['path'], 'categoryIsFilter' => true];
            }
        }

        [$values['attributes'], $values['attributeCodes']] = self::attributeFilters($input);

        unset($fixed['sort']);

        return new self(...array_merge($values, $fixed));
    }

    /**
     * A copy with some values changed.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...array_merge($this->toArray(), $changes));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * The shopper's filters as URL parameters (q and sort included, page excluded).
     *
     * @return array<string, string>
     */
    public function urlParams(bool $withSort = true): array
    {
        $params = [];

        if ($this->q !== null) {
            $params['q'] = $this->q;
        }
        if ($this->categoryIsFilter && $this->categoryId !== null) {
            $params['category'] = (string) $this->categoryId;
        }
        foreach ($this->attributes as $attributeId => $optionIds) {
            if (isset($this->attributeCodes[$attributeId]) && $optionIds !== []) {
                $params[$this->attributeCodes[$attributeId]] = implode(',', $optionIds);
            }
        }
        if ($this->brandIds !== []) {
            $params['brand'] = implode(',', $this->brandIds);
        }
        if ($this->priceFrom !== null || $this->priceTo !== null) {
            $params['price'] = ($this->priceFrom ?? '').'-'.($this->priceTo ?? '');
        }
        if ($this->rating !== null) {
            $params['rating'] = (string) $this->rating;
        }
        if ($this->discount !== null) {
            $params['discount'] = (string) $this->discount;
        }
        if ($this->codOnly) {
            $params['cod'] = '1';
        }
        if ($withSort && $this->sort !== 'relevance') {
            $params['sort'] = $this->sort;
        }

        return $params;
    }

    /**
     * Any shopper filter (beyond the text query and the page context) is active.
     */
    public function hasFilters(): bool
    {
        return array_diff_key($this->urlParams(false), ['q' => true]) !== [];
    }

    /**
     * Stable cache key of the result set (page and sort optional).
     */
    public function cacheKey(bool $withSort = false, bool $withPage = false): string
    {
        $values = $this->toArray();
        unset($values['attributeCodes']);

        if (! $withSort) {
            unset($values['sort']);
        }
        if (! $withPage) {
            unset($values['page']);
        }

        ksort($values['attributes']);

        return md5(SearchIndexer::searchLocale().'|'.serialize($values));
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected static function priceRange(array $input): array
    {
        $from = $input['price_from'] ?? null;
        $to = $input['price_to'] ?? null;

        if (($from === null || $from === '') && ($to === null || $to === '') && is_string($input['price'] ?? null) && str_contains($input['price'], '-')) {
            [$from, $to] = explode('-', $input['price'], 2);
        }

        $from = is_numeric($from) ? max(0, (int) $from) : null;
        $to = is_numeric($to) ? max(0, (int) $to) : null;

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from ?: null, $to];
    }

    /**
     * Attribute filters from the non-reserved parameters whose name is an attribute code.
     *
     * @return array{0: array<int, list<int>>, 1: array<int, string>}
     */
    protected static function attributeFilters(array $input): array
    {
        $candidates = array_filter(
            array_diff_key($input, array_flip(self::RESERVED)),
            fn ($value, $key): bool => is_string($key) && strlen($key) <= 64 && self::ids($value) !== [],
            ARRAY_FILTER_USE_BOTH,
        );

        if ($candidates === []) {
            return [[], []];
        }

        $ids = DB::table('attribute')->whereIn('code', array_keys($candidates))->pluck('id', 'code');
        $attributes = [];
        $codes = [];

        foreach ($ids as $code => $id) {
            $attributes[(int) $id] = self::ids($candidates[$code]);
            $codes[(int) $id] = (string) $code;
        }

        ksort($attributes);

        return [$attributes, $codes];
    }

    /**
     * @return list<int>
     */
    protected static function ids(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_values(array_unique(array_filter(array_map(fn ($part): int => is_numeric($part) ? (int) $part : 0, $parts), fn (int $id): bool => $id > 0)));
        sort($ids);

        return array_slice($ids, 0, self::MAX_VALUES);
    }

    /**
     * @param  list<int>  $buckets
     */
    protected static function bucket(mixed $value, array $buckets): ?int
    {
        return is_numeric($value) && in_array((int) $value, array_map('intval', $buckets), true) ? (int) $value : null;
    }

    protected static function sortValue(mixed $value, string $default): string
    {
        $sorts = (array) config('storefront.sorts');

        return is_string($value) && in_array($value, $sorts, true) ? $value : (in_array($default, $sorts, true) ? $default : 'relevance');
    }
}
