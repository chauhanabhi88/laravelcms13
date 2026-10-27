<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Product\Models\Product;
use Modules\Seller\Models\SellerUser;
use Modules\Storefront\Services\ProductViewCounter;
use Modules\Storefront\Services\RecentlyViewedService;
use Modules\Storefront\Services\SimilarProductsService;
use Modules\Storefront\Support\DeliveryPincode;
use Modules\Storefront\Support\StorefrontUrls;
use Modules\Storefront\ViewModels\ProductPageViewModel;
use Modules\User\Models\User;

/**
 * Product detail page /p/{slug}-{id} (FR-PDP-001 … 011), its lazy similar-products rows and
 * the fresh variant availability. Admins (admin.product.show) and the owning seller can
 * preview a product that is not live with ?preview=1 (banner + noindex).
 */
class ProductController extends Controller
{
    public const PREVIEW_PERMISSION = 'admin.product.show';

    public function __construct(
        protected SimilarProductsService $similar,
        protected DeliveryPincode $pincodes,
    ) {}

    public function show(Request $request, ProductPageViewModel $page, ProductViewCounter $views, RecentlyViewedService $recentlyViewed): View|RedirectResponse|Response
    {
        if (! preg_match('/^(?<slug>[a-z0-9-]+)-(?<id>\d+)$/', (string) $request->route('slugId'), $matches)) {
            abort(404);
        }

        $product = Product::query()->find((int) $matches['id']);
        $preview = $product !== null && $request->boolean('preview') && $this->canPreview($product);

        if ($product === null || (! $product->isVisibleOnStorefront() && ! $preview)) {
            return $this->unavailable($product);
        }

        if ($matches['slug'] !== $product->slug) {
            $query = $request->getQueryString();

            return redirect()->to(StorefrontUrls::product($product->slug, $product->id).($query ? '?'.$query : ''), 301);
        }

        $variantId = $request->integer('variant');
        $page->load($product, $variantId > 0 ? $variantId : null, $preview);

        if (! $preview) {
            $views->hit($request, $product->id);

            if (($customerId = Auth::guard('customer')->id()) !== null) {
                $recentlyViewed->record((int) $customerId, $product->id);
            }
        }

        return view('storefront::frontend.product.show', [
            'page' => $page,
            'pincode' => $this->pincodes->current($request),
            'isReseller' => $this->isReseller(),
        ]);
    }

    /**
     * GET /p/{id}/similar?type=similar|seller — a carousel of cards, loaded when the row
     * scrolls into view.
     */
    public function similar(Request $request): JsonResponse
    {
        $product = $this->visibleProduct($request);
        $type = $request->query('type') === SimilarProductsService::SELLER ? SimilarProductsService::SELLER : SimilarProductsService::SIMILAR;
        $cards = $this->similar->cards($product, $type);

        return response()->json([
            'count' => count($cards),
            'html' => $cards === [] ? '' : view('storefront::frontend.product.partials.similar-cards', ['cards' => $cards])->render(),
        ]);
    }

    /**
     * GET /p/{id}/availability — fresh variant prices and availability (capped), for a page
     * that has been open for a while.
     */
    public function availability(Request $request, ProductPageViewModel $page): JsonResponse
    {
        $product = $this->visibleProduct($request);

        return response()->json(['variants' => $page->availability($product)])
            ->header('Cache-Control', 'no-store, private');
    }

    protected function visibleProduct(Request $request): Product
    {
        $product = Product::query()->visibleOnStorefront()->find((int) $request->route('id'));
        abort_if($product === null, 404);

        return $product;
    }

    /**
     * 404 that keeps the buyer shopping: popular products from the same category.
     */
    protected function unavailable(?Product $product): Response
    {
        $cards = $product !== null
            ? $this->similar->visibleCards($this->similar->inCategory($product->catalog_category_id, $product->id))
            : [];

        return response()
            ->view('storefront::frontend.product.unavailable', ['code' => 404, 'cards' => $cards], 404)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    protected function canPreview(Product $product): bool
    {
        $admin = Auth::guard('web')->user();

        if ($admin instanceof User && $admin->hasPermission(self::PREVIEW_PERMISSION)) {
            return true;
        }

        $sellerUser = Auth::guard('seller')->user();

        return $sellerUser instanceof SellerUser && $sellerUser->seller_id === $product->seller_id;
    }

    /**
     * Resellers (FR-CUS-007, M08) see "Share & Earn".
     */
    protected function isReseller(): bool
    {
        $customer = Auth::guard('customer')->user();

        return $customer !== null && (int) $customer->getAttribute('is_reseller') === (int) config('core.yes');
    }
}
