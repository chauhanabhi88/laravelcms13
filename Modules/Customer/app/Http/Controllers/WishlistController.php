<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\Customer\Exceptions\WishlistException;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\WishlistService;
use Modules\Storefront\Services\ProductCardPresenter;

/**
 * Wishlist page and the heart's toggle endpoint (FR-CUS-006, plan M08 §5.4).
 * Guests reach the toggle without a session and get the JSON 401 from the auth middleware.
 */
class WishlistController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(private readonly WishlistService $wishlist) {}

    public function index(Request $request): View
    {
        $customer = $this->customer();
        $page = $this->wishlist->paginate($customer, self::PER_PAGE, max(1, (int) $request->query('page', 1)));

        $cards = class_exists(ProductCardPresenter::class) && $page->items() !== []
            ? app(ProductCardPresenter::class)->present($page->items())
            : [];

        return view('customer::frontend.account.wishlist', [
            'page' => $page,
            'cards' => $cards,
            'moveToCartUrl' => Route::has('cart.wishlist.move') ? frontRoute('cart.wishlist.move') : null,
        ]);
    }

    /**
     * {product_id, wishlisted?}: sets the state when "wishlisted" is sent (what the heart
     * shows after its optimistic flip), otherwise toggles. Answers {wishlisted, count}.
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'wishlisted' => ['nullable', 'boolean'],
        ]);

        $customer = $this->customer();
        $productId = (int) $validated['product_id'];

        try {
            $wishlisted = $request->has('wishlisted') && $validated['wishlisted'] !== null
                ? $this->wishlist->set($customer, $productId, (bool) $validated['wishlisted'])
                : $this->wishlist->toggle($customer, $productId);
        } catch (WishlistException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'wishlisted' => $this->wishlist->has($customer, $productId),
                'count' => $this->wishlist->count($customer),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'wishlisted' => $wishlisted,
            'count' => $this->wishlist->count($customer),
            'message' => trans('customer::customer_front.wishlist.'.($wishlisted ? 'added' : 'removed')),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->wishlist->remove($this->customer(), (int) $request->route('product'));

        return redirect()->to(frontRoute('customer.wishlist.index'))->with('success', trans('customer::customer_front.wishlist.removed'));
    }

    protected function customer(): Customer
    {
        /** @var Customer */
        return Auth::guard('customer')->user();
    }
}
