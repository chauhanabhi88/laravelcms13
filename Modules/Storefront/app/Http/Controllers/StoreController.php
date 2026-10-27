<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Seller\Repositories\SellerRepository;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Services\ListingRenderer;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Support\ListingPage;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * Public store page /s/{storeSlug} (FR-SEL-011): store header and the seller's products with
 * the usual filters. Replaces the M03 shell. Suspended stores stay reachable but list nothing
 * (their products are hidden from the index).
 */
class StoreController extends Controller
{
    public function __construct(
        protected SellerRepository $sellers,
        protected ListingRenderer $listings,
        protected ProductSearchEngine $search,
    ) {}

    public function show(Request $request): View|JsonResponse
    {
        $seller = $this->sellers->findPublicBySlug((string) $request->route('storeSlug'));
        abort_if($seller === null, 404);

        $logoUrl = $seller->logo ? Storage::disk('public')->url($seller->logo) : null;

        $page = new ListingPage(
            kind: ListingPage::STORE,
            title: $seller->displayName(),
            baseUrl: StorefrontUrls::store((string) $seller->store_slug),
            fixed: ['sellerId' => $seller->id],
            breadcrumbs: [$seller->displayName() => null],
            image: $logoUrl,
        );

        return $this->listings->render($request, $page, 'storefront::frontend.store', [
            'seller' => $seller,
            'logoUrl' => $logoUrl,
            'isAvailable' => $seller->isActive(),
            'productCount' => $this->search->count(new ListingQuery(sellerId: $seller->id)),
        ]);
    }
}
