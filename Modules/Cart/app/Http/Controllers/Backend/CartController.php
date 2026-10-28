<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Cart\Models\Cart;
use Modules\Cart\Repositories\CartRepository;
use Modules\Cart\Services\CartService;
use Modules\Cart\Services\CartTotals;
use Modules\Cart\Support\CartOptions;
use Modules\Core\Http\Controllers\BackendController;

/**
 * Read-only list of carts for customer support (plan M10 §6): who has what in their cart,
 * which carts are abandoned, and the revalidated content of one cart.
 */
class CartController extends BackendController
{
    public function __construct(private CartRepository $carts)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('cart.cache.cart'), $request->input('per_page'))]);

            return view('cart::backend.cart.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('cart.cache.cart'), $request->input('per_page'))]);
            setFilterSession(config('cart.cache.cart'), $request);

            $content = view('cart::backend.cart.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.cart.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * One cart, revalidated without saving corrections (support must not change what the
     * buyer sees next).
     */
    public function show(Request $request, CartService $service, CartTotals $totals): View|RedirectResponse
    {
        try {
            $cart = $this->carts->find((int) $request->route('id'));

            if (! $cart instanceof Cart) {
                throw new \RuntimeException(trans('cart::cart.messages.data_invalid'));
            }

            $cart->loadMissing('customer');
            $snapshot = $service->snapshot($cart, persist: false);

            return view('cart::backend.cart.show', [
                'cart' => $cart,
                'snapshot' => $snapshot,
                'totals' => $totals->calculate($snapshot),
                'types' => CartOptions::types(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.cart.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'owners' => CartOptions::owners(),
            'types' => CartOptions::types(),
            'states' => CartOptions::states(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->carts->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->carts->getFilters($request, $options),
        ] + $options;
    }
}
