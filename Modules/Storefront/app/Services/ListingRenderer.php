<?php

declare(strict_types=1);

namespace Modules\Storefront\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Jobs\LogSearchQuery;
use Modules\Storefront\Services\Search\FacetService;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Services\Search\ListingResult;
use Modules\Storefront\Services\Search\SearchAnalytics;
use Modules\Storefront\Support\ListingPage;
use Modules\Storefront\Support\ListingUrls;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * Renders any listing page (FR-DSC-002/003/007/009) in three forms:
 * - the full server-rendered page;
 * - ?partial=1 → JSON {results_html, facets_html, facets_mobile_html, next_page_url, total, url}
 *   for filter / sort changes without a reload;
 * - ?partial=grid → JSON {html, next_page_url} with the next page of cards (infinite scroll).
 */
class ListingRenderer
{
    public function __construct(
        protected ProductSearchEngine $search,
        protected FacetService $facets,
        protected SearchAnalytics $analytics,
        protected CategoryTreeService $tree,
    ) {}

    /**
     * @param  array<string, mixed>  $data  extra view data
     */
    public function render(Request $request, ListingPage $page, string $view = 'storefront::frontend.listing', array $data = []): View|JsonResponse
    {
        $query = $this->query($request, $page);
        $partial = (string) $request->query('partial', '');

        if ($partial === 'grid') {
            return $this->gridResponse($page, $query);
        }

        // Only full page loads count as a search; filter changes on the results do not.
        $listing = $this->listing($request, $page, $query, logSearch: $partial === '');

        if ($partial !== '') {
            return response()->json([
                'results_html' => view('storefront::frontend.partials.listing-results', $listing)->render(),
                'facets_html' => view('storefront::frontend.partials.filters', $listing + ['variant' => 'sidebar'])->render(),
                'facets_mobile_html' => view('storefront::frontend.partials.filters', $listing + ['variant' => 'sheet'])->render(),
                'sort_html' => view('storefront::frontend.partials.sort', $listing + ['variant' => 'list'])->render(),
                'filter_count' => count($listing['chips']),
                'next_page_url' => $listing['nextGridUrl'],
                'total' => $listing['result']->total,
                'url' => $listing['urls']->current(),
            ]);
        }

        return view($view, $listing + $data);
    }

    /**
     * View data of a listing (used by full pages, partial responses and the home grid).
     *
     * @return array<string, mixed>
     */
    public function listing(Request $request, ListingPage $page, ?ListingQuery $query = null, bool $logSearch = false): array
    {
        $query ??= $this->query($request, $page);
        $result = $this->search->run($query);

        abort_if($query->page > 1 && $result->isEmpty(), 404);

        $facets = $this->facets->facets($query);
        $urls = new ListingUrls($page, $query);

        if ($logSearch && $page->isSearch() && $query->q !== null && $query->page === 1) {
            $this->logSearch($request, $query->q, (int) $result->total);
        }

        return [
            'page' => $page,
            'query' => $query,
            'result' => $result,
            'facets' => $facets,
            'urls' => $urls,
            'chips' => $urls->chips($facets),
            'nextGridUrl' => $urls->nextGrid($result),
            'didYouMean' => $result->isEmpty() && $query->q !== null ? $this->analytics->didYouMean($query->q) : null,
            'popularCategories' => $result->isEmpty() ? $this->popularCategories() : [],
        ];
    }

    public function query(Request $request, ListingPage $page): ListingQuery
    {
        $query = ListingQuery::fromRequest($request, $page->fixed);

        abort_if($query->page > (int) config('storefront.max_page', 250), 404);

        return $query;
    }

    protected function gridResponse(ListingPage $page, ListingQuery $query): JsonResponse
    {
        $result = $this->search->run($query, false);
        $urls = new ListingUrls($page, $query);

        return response()->json([
            'html' => view('storefront::frontend.partials.product-grid-page', ['cards' => $result->cards, 'query' => $query])->render(),
            'next_page_url' => $urls->nextGrid($result),
            'url' => $urls->current(),
        ]);
    }

    protected function logSearch(Request $request, string $term, int $results): void
    {
        LogSearchQuery::dispatchAfterResponse(
            $term,
            $results,
            Auth::guard('customer')->id(),
            $request->hasSession() ? $request->session()->getId() : null,
        );
    }

    /**
     * Top-level categories for the zero-results page.
     *
     * @return list<array{name: string, url: string}>
     */
    protected function popularCategories(): array
    {
        $enabled = (int) config('core.enabled');
        $yes = (int) config('core.yes');

        return array_values(array_map(fn (array $node): array => [
            'name' => $node['name'],
            'url' => StorefrontUrls::category($node['slug']),
        ], array_slice(array_values(array_filter(
            $this->tree->nodes(),
            fn (array $node): bool => $node['parent_id'] === null && $node['status'] === $enabled && $node['show_in_menu'] === $yes,
        )), 0, (int) config('storefront.zero_result_categories', 8))));
    }

    /**
     * rel="prev" / rel="next" URLs for the page head.
     *
     * @return array{prev: string|null, next: string|null}
     */
    public static function relLinks(ListingUrls $urls, ListingResult $result): array
    {
        return ['prev' => $urls->previous($result), 'next' => $urls->next($result)];
    }
}
