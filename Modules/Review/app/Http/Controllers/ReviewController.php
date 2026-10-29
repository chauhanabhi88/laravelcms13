<?php

declare(strict_types=1);

namespace Modules\Review\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Product\Models\Product;
use Modules\Review\Services\ReviewListService;
use Modules\Review\Support\ReviewOptions;
use Modules\Storefront\Contracts\ReviewSummaryProvider;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * All reviews of a product, /p/{slug}-{id}/reviews (FR-REV-005): the ratings summary with
 * clickable star bars, filters (stars, with photos), sorts and pagination; each review with
 * "Helpful" and "Report" for signed-in shoppers. Only products visible on the storefront.
 */
class ReviewController extends Controller
{
    public function index(Request $request, ReviewListService $list, ReviewSummaryProvider $summaries): View|RedirectResponse
    {
        if (! preg_match('/^(?<slug>[a-z0-9-]+)-(?<id>\d+)$/', (string) $request->route('slugId'), $matches)) {
            abort(404);
        }

        $product = Product::query()->with('translations')->find((int) $matches['id']);

        abort_if($product === null || ! $product->isVisibleOnStorefront(), 404);

        if ($matches['slug'] !== $product->slug) {
            $query = $request->getQueryString();

            return redirect()->to(frontRoute('storefront.product.reviews', [$product->slug.'-'.$product->id]).($query ? '?'.$query : ''), 301);
        }

        $filters = $list->filters($request);
        $reviews = $list->forProduct($product->id, $filters)->withQueryString();
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        return view('review::frontend.product-reviews', [
            'product' => $product,
            'productName' => (string) ($product->name ?? Str::headline($product->slug)),
            'productUrl' => StorefrontUrls::product($product->slug, $product->id),
            'productImage' => StorefrontUrls::productImage($product->primary_image, 'thumb'),
            'summary' => $summaries->forProduct($product->id),
            'reviews' => $reviews,
            'filters' => $filters,
            'votedIds' => $list->votedIds($customer, $reviews->getCollection()->modelKeys()),
            'customerId' => $customer?->id,
            'reportReasons' => ReviewOptions::reportReasons(),
            'baseUrl' => frontRoute('storefront.product.reviews', [$product->slug.'-'.$product->id]),
        ]);
    }
}
