<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cart\Http\Controllers\Api\V1\CartController;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartService;
use Modules\Cart\Services\CartTotals;
use Modules\Promotion\Exceptions\CouponException;
use Modules\Promotion\Services\CouponService;

/**
 * Coupons over the API (plan M18 §6): POST/DELETE api/v1/cart/coupon and GET api/v1/cart/offers,
 * for the same owners as the cart API (customer token or X-Cart-Token). Apply and remove answer
 * with the cart and its totals; errors carry a stable "reason".
 */
class CouponController extends CartController
{
    public function __construct(
        private readonly CartService $cartService,
        CartTotals $totals,
        private readonly CouponService $coupons,
    ) {
        parent::__construct($cartService, $totals);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:30']]);
        $cart = $this->ownerCart($request);

        if ($cart === null) {
            return $this->rejected(new CouponException(trans('promotion::front.errors.empty_cart', ['code' => $validated['code']]), 'empty_cart'));
        }

        try {
            $evaluation = $this->coupons->apply($cart, $validated['code'], $this->paymentMethod($request));
        } catch (CouponException $e) {
            return $this->rejected($e);
        }

        return $this->cart($cart, trans('promotion::front.messages.applied', ['code' => $evaluation->coupon->code, 'amount' => $evaluation->benefit()->format()]));
    }

    public function remove(Request $request): JsonResponse
    {
        $cart = $this->ownerCart($request);

        if ($cart !== null) {
            $this->coupons->remove($cart);
        }

        return $this->cart($cart, trans('promotion::front.messages.removed'));
    }

    public function offers(Request $request): JsonResponse
    {
        $snapshot = $this->cartService->snapshot($this->ownerCart($request));

        $offers = array_map(fn (array $row): array => [
            'code' => $row['coupon']->code,
            'name' => $row['coupon']->name,
            'description' => $row['coupon']->description,
            'benefit' => $row['coupon']->benefitText(),
            'min_cart_value' => $row['coupon']->min_cart_value->toDecimal(),
            'ends_at' => $row['coupon']->ends_at?->toIso8601String(),
            'applicable' => $row['evaluation']->valid,
            'saving' => $row['evaluation']->benefit()->toDecimal(),
            'reason' => $row['evaluation']->reason,
            'message' => $row['evaluation']->message,
            'applied' => $snapshot->couponCode === $row['coupon']->code,
        ], $this->coupons->offers($snapshot, $this->paymentMethod($request)));

        return $this->ok($offers);
    }

    protected function ownerCart(Request $request): ?Cart
    {
        $owner = $this->owner($request);
        $type = $request->input('cart', $request->query('cart')) === Cart::TYPE_BUY_NOW && $owner->customer !== null ? Cart::TYPE_BUY_NOW : Cart::TYPE_STANDARD;

        return $this->cartService->resolve($owner, false, $type);
    }

    protected function paymentMethod(Request $request): ?string
    {
        $method = $request->input('payment_method');

        return in_array($method, (array) config('promotion.payment_methods'), true) ? $method : null;
    }

    protected function rejected(CouponException $e): JsonResponse
    {
        return $this->fail(422, $e->getMessage(), $e->apiErrors(), extra: ['reason' => $e->reason]);
    }
}
