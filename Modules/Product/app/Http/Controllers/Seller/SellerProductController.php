<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Repositories\SellerCatalogRepository;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Services\ProductModerationService;
use Modules\Product\Support\Listing\ListingWizard;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Supplier panel › Catalog › Products: status tabs with counts, search, pause / resume and
 * delete (drafts and rejected products only).
 */
class SellerProductController extends SellerPanelController
{
    public function __construct(
        private readonly SellerCatalogRepository $catalog,
        private readonly ProductModerationService $moderation,
        private readonly ProductImageService $images,
        private readonly CategoryTreeService $tree,
    ) {}

    public function index(Request $request): View
    {
        return view('product::seller.product.index', $this->gridData($request) + [
            'sellerStoreName' => $this->seller()->displayName(),
            'counts' => $this->catalog->tabCounts($this->seller()->id),
        ]);
    }

    /**
     * The grid alone, for AJAX tab / search changes.
     */
    public function filters(Request $request): JsonResponse
    {
        return response()->json([
            'html' => view('product::seller.product.partials.grid', $this->gridData($request))->render(),
        ]);
    }

    public function edit(Request $request): RedirectResponse
    {
        $product = $this->ownedProduct($request);

        return redirect()->to(frontRoute('seller.listing.step', [$product->id, ListingWizard::STEP_DETAILS]));
    }

    public function pause(Request $request): RedirectResponse
    {
        return $this->move($request, fn (Product $product) => $this->moderation->pause($product, $this->sellerUser()), 'paused');
    }

    public function resume(Request $request): RedirectResponse
    {
        return $this->move($request, fn (Product $product) => $this->moderation->resume($product, $this->sellerUser()), 'resumed');
    }

    /**
     * Drafts and rejected products only. Never-published ones are removed for good (their
     * Seller SKUs become free again); a product that was live once is soft-deleted.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $product = $this->ownedProduct($request);

        if (! in_array($product->status, [ProductStatus::Draft, ProductStatus::Rejected], true)) {
            return back()->with('error', trans('product::seller.products.messages.delete_locked'));
        }

        if ($product->published_at === null) {
            $paths = ProductImage::query()->where('product_id', $product->id)->pluck('path')->all();

            DB::transaction(fn () => $product->forceDelete());
            $this->images->deleteUnreferenced($paths);
        } else {
            $product->delete();
        }

        return redirect()->to(frontRoute('seller.product.index'))->with('success', trans('product::seller.products.messages.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request): array
    {
        $tab = in_array($request->query('tab'), SellerCatalogRepository::TABS, true) ? (string) $request->query('tab') : SellerCatalogRepository::TAB_ALL;
        $search = trim((string) $request->query('q', ''));

        $products = $this->catalog->products($this->seller()->id, $tab, $search, (int) config('product.seller_per_page', 20));
        $categoryLabels = [];

        foreach ($products as $product) {
            $categoryLabels[$product->catalog_category_id] ??= $this->tree->breadcrumb($product->catalog_category_id);
        }

        return [
            'tab' => $tab,
            'search' => $search,
            'products' => $products,
            'categoryLabels' => $categoryLabels,
        ];
    }

    /**
     * @param  \Closure(Product): Product  $action
     */
    private function move(Request $request, \Closure $action, string $message): RedirectResponse
    {
        $product = $this->ownedProduct($request);

        try {
            $action($product);
        } catch (InvalidStateTransition) {
            return back()->with('error', trans('product::seller.products.messages.not_allowed', ['status' => $product->status->label()]));
        }

        return back()->with('success', trans('product::seller.products.messages.'.$message));
    }

    private function ownedProduct(Request $request): Product
    {
        $product = Product::query()->findOrFail((int) $request->route('product'));
        $this->authorizeOwned($product);

        return $product;
    }
}
