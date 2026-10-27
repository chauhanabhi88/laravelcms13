<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Http\Requests\Backend\ProductDecisionRequest;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\ProductRepository;
use Modules\Product\Services\ProductModerationService;
use Modules\Product\Support\DuplicateDetector;
use Modules\Product\Support\ProductPresenter;
use Modules\User\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * All products: grid, read-only detail (variants, images, attributes, history, latest
 * stock movements), block / unblock and CSV export.
 */
class ProductController extends ProductAdminController
{
    public function __construct(
        ProductRepository $products,
        ProductPresenter $presenter,
        DuplicateDetector $duplicates,
        private readonly ProductModerationService $moderation,
    ) {
        parent::__construct($products, $presenter, $duplicates);
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('product.cache.product'), $request->input('per_page'))]);

            return view('product::backend.product.index', $this->gridData($request, ProductRepository::GRID_ALL, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('product.cache.product'), $request->input('per_page'))]);
            setFilterSession(config('product.cache.product'), $request);

            $content = view('product::backend.product.partials.grid', $this->gridData(
                $request,
                ProductRepository::GRID_ALL,
                getActiveMenuId($request, 'admin.product.index'),
            ));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request): View|RedirectResponse
    {
        try {
            $product = $this->findOrFail($request->route('id'));

            return view('product::backend.product.show', $this->detailData($product) + [
                'movements' => $product->inventoryMovements()->with('variant:id,sku')->latest('id')->limit(20)->get(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.product.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function block(ProductDecisionRequest $request): RedirectResponse
    {
        return $this->decide($request, fn (Product $product, User $admin, ?string $reason) => $this->moderation->block($product, $admin, $reason), 'blocked');
    }

    public function unblock(ProductDecisionRequest $request): RedirectResponse
    {
        return $this->decide($request, fn (Product $product, User $admin, ?string $reason) => $this->moderation->unblock($product, $admin, $reason), 'unblocked');
    }

    /**
     * The filtered grid as CSV (current filters from the request or the grid session).
     */
    public function export(Request $request): Response|RedirectResponse
    {
        try {
            $rows = $this->products->exportRows($request);
            $columns = ['id', 'name', 'seller', 'seller_code', 'category', 'status', 'min_price', 'max_price', 'min_mrp', 'max_discount_percent', 'total_stock', 'gst_percent', 'hsn_code', 'published_at', 'created_at'];

            return $this->products->exportData($columns, $rows, 'products-'.now()->format('Ymd-His').'.csv');
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.product.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * @param  \Closure(Product, User, ?string): mixed  $action
     */
    private function decide(ProductDecisionRequest $request, \Closure $action, string $message): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $action($this->findOrFail($id), $this->admin(), $request->reason());
        } catch (ProductException|InvalidStateTransition $e) {
            return redirect()->route('admin.product.show', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.product.show', updateUrlParams([$id]))->with('error', $e->getMessage());
        }

        return redirect()->route('admin.product.show', updateUrlParams([$id]))->with('success', trans('product::product.messages.'.$message));
    }
}
