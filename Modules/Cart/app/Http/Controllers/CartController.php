<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\Cart\DTO\AddResult;
use Modules\Cart\DTO\CartOwner;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\Exceptions\CartException;
use Modules\Cart\Http\Requests\AddToCartRequest;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Services\CartService;
use Modules\Cart\Services\CartTotals;
use Modules\Cart\Support\CartCookie;
use Modules\Cart\Support\CartSettings;
use Modules\Cart\Support\CartUrls;
use Modules\Customer\Models\Customer;
use Modules\Product\Models\Product;
use Modules\Storefront\Support\DeliveryPincode;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * Storefront cart (plan M10 §6-7): the cart page, line actions, Buy Now, the mini-cart
 * drawer and the badge count.
 *
 * Every action answers JSON to fetch() ({success, message, count, html?}; "view" = page or
 * drawer picks which partial comes back as html) and redirects to the cart page otherwise,
 * so the page also works without JavaScript.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CartTotals $totals,
        private readonly DeliveryPincode $pincodes,
    ) {}

    /**
     * The cart page; ?cart=buy_now shows the signed-in customer's Buy Now cart instead.
     */
    public function index(Request $request): View
    {
        $type = $request->query('cart') === Cart::TYPE_BUY_NOW && $request->user('customer') !== null ? Cart::TYPE_BUY_NOW : Cart::TYPE_STANDARD;
        $cart = $this->carts->resolve(CartOwner::fromRequest($request), false, $type);

        return view('cart::frontend.cart', $this->viewData($this->snapshot($request, $cart)));
    }

    public function add(AddToCartRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->carts->add(CartOwner::fromRequest($request), $request->variantId(), $request->quantity());
        } catch (CartException $e) {
            return $this->failed($request, $e);
        }

        CartCookie::remember($result->cart);

        return $this->respond($request, $this->addedMessage($result), $result->cart, ['line_id' => $result->item->id], 'drawer');
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.CartSettings::maxQtyPerLine()]]);
        [$cart, $item] = $this->item($request);

        try {
            $result = $this->carts->update($cart, $item, (int) $validated['quantity']);
        } catch (CartException $e) {
            return $this->failed($request, $e, $cart);
        }

        $message = $result->capped
            ? trans_choice('cart::front.issues.low_stock', $result->item->quantity, ['count' => $result->item->quantity])
            : trans('cart::front.messages.updated');

        return $this->respond($request, $message, $cart);
    }

    public function remove(Request $request): JsonResponse|RedirectResponse
    {
        [$cart, $item] = $this->item($request);
        $this->carts->remove($cart, $item);

        return $this->respond($request, trans('cart::front.messages.removed'), $cart);
    }

    public function saveForLater(Request $request): JsonResponse|RedirectResponse
    {
        [$cart, $item] = $this->item($request);
        $this->carts->saveForLater($cart, $item);

        return $this->respond($request, trans('cart::front.messages.saved_for_later'), $cart);
    }

    public function moveToCart(Request $request): JsonResponse|RedirectResponse
    {
        [$cart, $item] = $this->item($request);

        try {
            $result = $this->carts->moveToCart($cart, $item);
        } catch (CartException $e) {
            return $this->failed($request, $e, $cart);
        }

        return $this->respond($request, $this->addedMessage($result, 'cart::front.messages.moved_to_cart'), $cart);
    }

    /**
     * Guests are asked to log in (the wishlist belongs to an account).
     */
    public function moveToWishlist(Request $request): JsonResponse|RedirectResponse
    {
        $customer = $request->user('customer');

        if (! $customer instanceof Customer) {
            return $this->loginRequired($request, CartUrls::cart());
        }

        [$cart, $item] = $this->item($request);

        try {
            $this->carts->moveToWishlist($cart, $item, $customer);
        } catch (CartException $e) {
            return $this->failed($request, $e, $cart);
        }

        return $this->respond($request, trans('cart::front.messages.moved_to_wishlist'), $cart);
    }

    /**
     * "Move to cart" on the wishlist page (route cart.wishlist.move, customers only). Products
     * with several variants open their product page to choose one.
     */
    public function moveFromWishlist(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['product_id' => ['required', 'integer', 'min:1']]);
        /** @var Customer $customer */
        $customer = $request->user('customer');

        try {
            $result = $this->carts->moveFromWishlist(CartOwner::fromRequest($request), $customer, (int) $validated['product_id']);
        } catch (CartException $e) {
            if ($e->field === 'product_id' && ! $request->expectsJson()) {
                $slug = Product::query()->whereKey((int) $validated['product_id'])->value('slug');

                if (is_string($slug)) {
                    return redirect()->to(StorefrontUrls::product($slug, (int) $validated['product_id']))->with('error', $e->getMessage());
                }
            }

            return $this->failed($request, $e, null, $this->wishlistUrl());
        }

        return $this->respond($request, $this->addedMessage($result, 'cart::front.messages.moved_to_cart'), $result->cart);
    }

    /**
     * Buy Now: a one-line cart for the signed-in customer, then checkout (M12) or, until it
     * exists, the Buy Now cart page. Guests log in first.
     */
    public function buyNow(AddToCartRequest $request): JsonResponse|RedirectResponse
    {
        $customer = $request->user('customer');

        if (! $customer instanceof Customer) {
            return $this->loginRequired($request, url()->previous());
        }

        try {
            $this->carts->buyNow($customer, $request->variantId(), $request->quantity());
        } catch (CartException $e) {
            return $this->failed($request, $e, null, url()->previous());
        }

        $redirect = CartUrls::afterBuyNow();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => $redirect]);
        }

        return redirect()->to($redirect);
    }

    /**
     * Mini-cart drawer content (revalidated).
     */
    public function drawer(Request $request): JsonResponse
    {
        $snapshot = $this->snapshot($request, $this->carts->resolve(CartOwner::fromRequest($request)));

        return response()->json([
            'count' => $snapshot->itemCount(),
            'html' => view('cart::frontend.partials.mini-cart', $this->viewData($snapshot))->render(),
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->carts->count(CartOwner::fromRequest($request))]);
    }

    /**
     * Delivery pincode for the per-seller delivery estimates (also remembered for the PDP).
     */
    public function pincode(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['pincode' => ['required', 'string', 'regex:'.DeliveryPincode::PATTERN]], [
            'pincode.regex' => trans('cart::front.errors.pincode'),
        ]);

        $this->pincodes->remember($request, $validated['pincode']);
        $cart = $this->carts->resolve(CartOwner::fromRequest($request));

        if ($cart !== null) {
            $this->carts->setDeliveryPincode($cart, $validated['pincode']);
        }

        return $this->respond($request, trans('cart::front.messages.pincode_saved', ['pincode' => $validated['pincode']]), $cart);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function respond(Request $request, string $message, ?Cart $cart, array $extra = [], ?string $defaultView = null): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->to(CartUrls::cart())->with('success', $message);
        }

        $snapshot = $this->snapshot($request, $cart?->exists ? $cart->fresh() : null);
        $view = match ($request->input('view', $defaultView)) {
            'page' => 'cart::frontend.partials.cart-body',
            'drawer' => 'cart::frontend.partials.mini-cart',
            default => null,
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'count' => $snapshot->itemCount(),
            'html' => $view !== null ? view($view, $this->viewData($snapshot))->render() : null,
        ] + $extra);
    }

    protected function failed(Request $request, CartException $e, ?Cart $cart = null, ?string $back = null): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->to($back ?? CartUrls::cart())->with('error', $e->getMessage());
        }

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => [$e->field => [$e->getMessage()]],
            'count' => $cart !== null ? (int) $cart->items()->active()->sum('quantity') : $this->carts->count(CartOwner::fromRequest($request)),
        ], 422);
    }

    protected function loginRequired(Request $request, string $returnTo): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'login' => true, 'message' => trans('cart::front.errors.login_required')], 401);
        }

        $login = Route::has('customer.login') ? frontRoute('customer.login').'?'.http_build_query(['redirect' => $returnTo]) : CartUrls::cart();

        return redirect()->to($login)->with('error', trans('cart::front.errors.login_required'));
    }

    /**
     * The visitor's cart and one of its lines, else 404.
     *
     * @return array{0: Cart, 1: CartItem}
     */
    protected function item(Request $request): array
    {
        $cart = $this->carts->resolve(CartOwner::fromRequest($request));
        $item = $this->carts->findItem($cart, (int) $request->route('item'));

        /** @var Cart $cart */
        return [$cart, $item];
    }

    protected function snapshot(Request $request, ?Cart $cart): CartSnapshot
    {
        return $this->carts->snapshot($cart, $this->pincodes->current($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(CartSnapshot $snapshot): array
    {
        return [
            'snapshot' => $snapshot,
            'totals' => $this->totals->calculate($snapshot),
            'isBuyNow' => $snapshot->type === Cart::TYPE_BUY_NOW,
            'checkoutUrl' => CartUrls::checkout($snapshot->type),
            'homeUrl' => Route::has('homepage') ? frontRoute('homepage') : url('/'),
            'canUseWishlist' => auth('customer')->check(),
        ];
    }

    protected function addedMessage(AddResult $result, string $key = 'cart::front.messages.added'): string
    {
        if (! $result->capped) {
            return trans($key);
        }

        return trans('cart::front.messages.added_capped', ['count' => $result->item->quantity]);
    }

    protected function wishlistUrl(): string
    {
        return Route::has('customer.wishlist.index') ? frontRoute('customer.wishlist.index') : CartUrls::cart();
    }
}
