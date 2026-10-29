<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cart\DTO\CartOwner;
use Modules\Cart\Exceptions\CartException;
use Modules\Cart\Http\Requests\AddToCartRequest;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartService;
use Modules\Cart\Services\CartTotals;
use Modules\Cart\Support\CartSettings;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Services\CustomerAuthService;

/**
 * Cart over the API (plan M10 §6). Customers authenticate with a Passport token carrying the
 * "customer" scope; guests send the X-Cart-Token header, which the first add returns in
 * meta.cart_token. Every answer carries the revalidated cart and its totals.
 */
class CartController extends ApiController
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CartTotals $totals,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $type = $request->query('cart') === Cart::TYPE_BUY_NOW ? Cart::TYPE_BUY_NOW : Cart::TYPE_STANDARD;
        $owner = $this->owner($request);

        return $this->cart($owner->isGuest() && $type === Cart::TYPE_BUY_NOW ? null : $this->carts->resolve($owner, false, $type));
    }

    public function store(AddToCartRequest $request): JsonResponse
    {
        try {
            $result = $this->carts->add($this->owner($request), $request->variantId(), $request->quantity());
        } catch (CartException $e) {
            return $this->failed($e);
        }

        return $this->cart($result->cart, $result->capped ? trans('cart::front.messages.added_capped', ['count' => $result->item->quantity]) : trans('cart::front.messages.added'), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.CartSettings::maxQtyPerLine()]]);
        $cart = $this->carts->resolve($this->owner($request));
        $item = $this->carts->findItem($cart, (int) $request->route('id'));

        try {
            $this->carts->update($cart, $item, (int) $validated['quantity']);
        } catch (CartException $e) {
            return $this->failed($e);
        }

        return $this->cart($cart, trans('cart::front.messages.updated'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $cart = $this->carts->resolve($this->owner($request));
        $this->carts->remove($cart, $this->carts->findItem($cart, (int) $request->route('id')));

        return $this->cart($cart, trans('cart::front.messages.removed'));
    }

    public function buyNow(AddToCartRequest $request): JsonResponse
    {
        $owner = $this->owner($request);

        if ($owner->customer === null) {
            return $this->fail(401, trans('cart::front.errors.login_required'));
        }

        try {
            $result = $this->carts->buyNow($owner->customer, $request->variantId(), $request->quantity());
        } catch (CartException $e) {
            return $this->failed($e);
        }

        return $this->cart($result->cart, null, 201);
    }

    protected function cart(?Cart $cart, ?string $message = null, int $status = 200): JsonResponse
    {
        $snapshot = $this->carts->snapshot($cart?->exists ? $cart->fresh() : null);

        return $this->ok(
            [
                'cart' => $snapshot->toArray(),
                'totals' => $this->totals->calculate($snapshot)->toArray(),
            ],
            [
                'cart_token' => $cart !== null && $cart->isGuest() ? $cart->token : null,
                'max_qty_per_line' => CartSettings::maxQtyPerLine(),
            ],
            $message,
            $status,
        );
    }

    protected function failed(CartException $e): JsonResponse
    {
        return $this->failReason($e->reason, $e->getMessage(), $e->apiErrors());
    }

    protected function owner(Request $request): CartOwner
    {
        return CartOwner::fromApiRequest($request, CustomerAuthService::API_SCOPE);
    }
}
