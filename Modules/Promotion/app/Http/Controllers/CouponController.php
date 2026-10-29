<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Cart\DTO\CartOwner;
use Modules\Cart\Http\Controllers\CartController;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartService;
use Modules\Cart\Services\CartTotals;
use Modules\Promotion\Exceptions\CouponException;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Services\CouponService;
use Modules\Storefront\Support\DeliveryPincode;

/**
 * Coupon box actions on the cart page and in checkout (plan M18 §6): apply a code, remove it,
 * and the "Available offers" list. Answers like the cart actions (JSON with the re-rendered
 * cart body for the cart page script, else a redirect back), so the box also works without
 * JavaScript. The Buy Now cart is used when the form says cart_type=buy_now.
 */
class CouponController extends CartController
{
    public function __construct(
        private readonly CartService $cartService,
        CartTotals $totals,
        DeliveryPincode $pincodes,
        private readonly CouponService $coupons,
    ) {
        parent::__construct($cartService, $totals, $pincodes);
    }

    public function apply(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:30']], [], ['code' => trans('promotion::front.box.label')]);
        $cart = $this->visitorCart($request);

        if ($cart === null) {
            return $this->couponFailed($request, new CouponException(trans('promotion::front.errors.empty_cart', ['code' => Coupon::normaliseCode($validated['code'])]), 'empty_cart'));
        }

        try {
            $evaluation = $this->coupons->apply($cart, $validated['code'], $this->paymentMethod($request));
        } catch (CouponException $e) {
            return $this->couponFailed($request, $e, $cart);
        }

        $message = trans('promotion::front.messages.applied', ['code' => $evaluation->coupon->code, 'amount' => $evaluation->benefit()->format()]);

        return $this->couponRespond($request, $message, $cart);
    }

    public function remove(Request $request): JsonResponse|RedirectResponse
    {
        $cart = $this->visitorCart($request);

        if ($cart !== null) {
            $this->coupons->remove($cart);
        }

        return $this->couponRespond($request, trans('promotion::front.messages.removed'), $cart);
    }

    /**
     * "Available offers" as JSON (the cart page renders them inline; this serves other clients).
     */
    public function offers(Request $request): JsonResponse
    {
        $cart = $this->visitorCart($request);
        $snapshot = $this->cartService->snapshot($cart);

        return response()->json([
            'success' => true,
            'data' => array_map(fn (array $row): array => [
                'code' => $row['coupon']->code,
                'name' => $row['coupon']->name,
                'description' => $row['coupon']->description,
                'benefit' => $row['coupon']->benefitText(),
                'applicable' => $row['evaluation']->valid,
                'saving' => $row['evaluation']->benefit()->toDecimal(),
                'message' => $row['evaluation']->message,
                'applied' => $snapshot->couponCode === $row['coupon']->code,
            ], $this->coupons->offers($snapshot, $this->paymentMethod($request))),
        ]);
    }

    protected function visitorCart(Request $request): ?Cart
    {
        $type = $request->input('cart_type', $request->query('cart')) === Cart::TYPE_BUY_NOW && $request->user('customer') !== null
            ? Cart::TYPE_BUY_NOW
            : Cart::TYPE_STANDARD;

        return $this->cartService->resolve(CartOwner::fromRequest($request), false, $type);
    }

    protected function paymentMethod(Request $request): ?string
    {
        $method = $request->input('payment_method');

        return in_array($method, (array) config('promotion.payment_methods'), true) ? $method : null;
    }

    protected function couponRespond(Request $request, string $message, ?Cart $cart): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->back()->with('coupon_success', $message);
        }

        return $this->respond($request, $message, $cart, [], 'page');
    }

    protected function couponFailed(Request $request, CouponException $e, ?Cart $cart = null): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->back()->withInput($request->only('code'))->with('coupon_error', $e->getMessage());
        }

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'reason' => $e->reason,
            'errors' => ['code' => [$e->getMessage()]],
            'count' => $cart !== null ? (int) $cart->items()->active()->sum('quantity') : 0,
        ], 422);
    }
}
