<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Storefront\Services\ListingRenderer;
use Modules\Storefront\Services\Search\SearchAnalytics;
use Modules\Storefront\Support\ListingPage;

/**
 * Search results /search?q= (FR-DSC-003): full-text search with filters, the zero-results
 * page (FR-DSC-011) and the result-click beacon for search analytics. Without a term it lists
 * every product, so brand links from the autocomplete work too.
 */
class SearchController extends Controller
{
    public function __construct(protected ListingRenderer $listings) {}

    public function index(Request $request): View|JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $title = $term !== ''
            ? trans('storefront::storefront.search.results_for', ['term' => mb_substr($term, 0, 120)])
            : trans('storefront::storefront.search.all_products');

        $page = new ListingPage(
            kind: ListingPage::SEARCH,
            title: $title,
            baseUrl: frontRoute('storefront.search'),
            breadcrumbs: [trans('storefront::storefront.search.breadcrumb') => null],
        );

        return $this->listings->render($request, $page, 'storefront::frontend.search');
    }

    /**
     * A shopper opened a product from the results (navigator.sendBeacon).
     */
    public function click(Request $request, SearchAnalytics $analytics): Response
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:120'],
            'product_id' => ['required', 'integer', 'min:1'],
        ]);

        $analytics->recordClick($validated['q'], (int) $validated['product_id'], $request->session()->getId());

        return response()->noContent();
    }
}
