<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Storefront\Models\ProductCollection;
use Modules\Storefront\Services\CollectionQuery;
use Modules\Storefront\Services\ListingRenderer;
use Modules\Storefront\Support\ListingPage;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * A curated collection /collection/{slug} on the listing template. Disabled or unknown
 * collections are a 404.
 */
class CollectionController extends Controller
{
    public function __construct(
        protected CollectionQuery $collections,
        protected ListingRenderer $listings,
    ) {}

    public function show(Request $request): View|JsonResponse
    {
        $collection = ProductCollection::query()->with('translations')->enabled()
            ->where('slug', (string) $request->route('slug'))
            ->first();

        abort_if($collection === null, 404);

        $page = new ListingPage(
            kind: ListingPage::COLLECTION,
            title: (string) ($collection->name ?? $collection->slug),
            baseUrl: StorefrontUrls::collection($collection->slug),
            fixed: $this->collections->fixed($collection),
            breadcrumbs: [(string) ($collection->name ?? $collection->slug) => null],
            metaDescription: $collection->description,
            image: $collection->bannerUrl(),
        );

        return $this->listings->render($request, $page, 'storefront::frontend.collection', ['collection' => $collection]);
    }
}
