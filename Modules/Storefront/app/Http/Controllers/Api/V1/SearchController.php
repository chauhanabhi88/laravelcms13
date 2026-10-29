<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Seller\Repositories\SellerRepository;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Models\ProductCollection;
use Modules\Storefront\Services\CollectionQuery;
use Modules\Storefront\Services\Search\FacetService;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Transformers\ProductCardResource;

/**
 * GET /api/v1/products — the listing engine for apps: the web listing parameters (q, sort,
 * page, price, rating, discount, cod, brand, attribute codes) plus an optional context:
 * category (slug), store (store slug) or collection (slug). An unknown context is a 404.
 */
class SearchController extends ApiController
{
    public function __construct(
        protected ProductSearchEngine $search,
        protected FacetService $facets,
        protected CatalogCategoryRepository $categories,
        protected SellerRepository $sellers,
        protected CollectionQuery $collections,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $fixed = $this->context($request);

        if ($fixed === null) {
            return $this->fail(404, trans('core::core.messages.route_not_found'));
        }

        $query = ListingQuery::fromRequest($request, $fixed);
        $query = $query->with(['page' => min($query->page, (int) config('storefront.max_page', 250))]);
        $result = $this->search->run($query);

        return response()->json([
            'success' => true,
            'data' => ProductCardResource::collection($result->cards)->resolve($request),
            'meta' => [
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->perPage,
                'has_more' => $result->hasMore,
                'sort' => $query->sort,
                'filters' => $query->urlParams(false),
            ],
            'facets' => $request->boolean('facets', true) ? $this->facets->facets($query) : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function context(Request $request): ?array
    {
        if ($request->filled('category')) {
            $category = $this->categories->findEnabledBySlug((string) $request->query('category'));

            return $category ? ['categoryId' => $category->id, 'categoryPath' => $category->path] : null;
        }

        if ($request->filled('store')) {
            $seller = $this->sellers->findPublicBySlug((string) $request->query('store'));

            return $seller ? ['sellerId' => $seller->id] : null;
        }

        if ($request->filled('collection')) {
            $collection = ProductCollection::query()->enabled()->where('slug', (string) $request->query('collection'))->first();

            return $collection ? $this->collections->fixed($collection) : null;
        }

        return [];
    }
}
