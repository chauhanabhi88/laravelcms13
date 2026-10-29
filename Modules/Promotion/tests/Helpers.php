<?php

use Illuminate\Support\Facades\DB;
use Modules\Cart\DTO\Totals;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartTotals;
use Modules\Customer\Models\Customer;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\DTO\PlaceOrderResult;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Services\PaymentService;
use Modules\Product\Models\ProductVariant;
use Modules\Promotion\DTO\CouponEvaluation;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Services\CouponService;
use Modules\User\Models\User;

require_once __DIR__.'/../../Finance/tests/Helpers.php';

/*
| Shared helpers for the Promotion module tests (require_once'd: Pest test files with same-named
| global functions would collide).
*/

if (! function_exists('promotionSetup')) {
    /**
     * Order setup (fake gateway, COD on, always-free delivery) plus default promotion settings.
     */
    function promotionSetup(): void
    {
        orderSetup();
        promotionSettings([
            'allow_stacking' => 2,
            'max_coupon_discount_percent_of_cart' => 90,
            'first_order_prepaid_only' => 2,
            'referral_enabled' => 2,
            'referral_reward_amount' => 100,
            'referral_reward_min_cart' => 299,
            'referral_reward_validity_days' => 30,
        ]);
    }

    /**
     * Merge values into Settings > Promotions.
     *
     * @param  array<string, mixed>  $values
     */
    function promotionSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'promotion')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'promotion'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * ₹40 delivery below ₹999 (one charge per order).
     */
    function promotionPaidDelivery(string $fee = '40', string $minimum = '999'): void
    {
        cartSettings(['free_delivery' => 'threshold', 'delivery_fee' => $fee, 'free_delivery_min' => $minimum]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    function promotionCoupon(array $attributes = []): Coupon
    {
        return Coupon::factory()->create($attributes);
    }

    function customerCart(Customer $customer, string $type = Cart::TYPE_STANDARD): ?Cart
    {
        return cartService()->resolve(customerOwner($customer), false, $type);
    }

    function applyCoupon(Customer $customer, string $code, ?string $paymentMethod = null): CouponEvaluation
    {
        return app(CouponService::class)->apply(customerCart($customer), $code, $paymentMethod);
    }

    /**
     * The price summary of the customer's cart.
     */
    function promotionTotals(Customer $customer, ?string $paymentMethod = null): Totals
    {
        return app(CartTotals::class)->calculate(cartService()->snapshot(customerCart($customer)), $paymentMethod);
    }

    /**
     * Evaluate a coupon against the customer's cart as it is.
     */
    function evaluateCoupon(Coupon $coupon, ?Customer $customer, ?string $paymentMethod = null): CouponEvaluation
    {
        $cart = $customer !== null ? customerCart($customer) : null;

        return app(CouponService::class)->evaluate($coupon->fresh(), cartService()->snapshot($cart), $paymentMethod);
    }

    /**
     * A customer's order (default address) with the coupon applied to the cart first.
     *
     * @param  list<ProductVariant|array{0: ProductVariant, 1: int}>  $lines
     */
    function placeWithCoupon(Customer $customer, array $lines, ?string $code, string $method = CheckoutSession::METHOD_COD): PlaceOrderResult
    {
        orderCart($customer, $lines);

        if ($code !== null) {
            applyCoupon($customer, $code, $method);
        }

        return placeOrder($customer, orderCheckout($customer, $method));
    }

    /**
     * Place (with $code), accept and deliver a one-seller order; prepaid orders are captured first.
     *
     * @param  list<ProductVariant|array{0: ProductVariant, 1: int}>  $lines
     * @return array{0: SalesOrder, 1: SellerOrder}
     */
    function deliveredWithCoupon(Customer $customer, array $lines, ?string $code, string $method = CheckoutSession::METHOD_COD): array
    {
        $result = placeWithCoupon($customer, $lines, $code, $method);

        if ($method === CheckoutSession::METHOD_PREPAID) {
            $fake = app(FakeGateway::class);
            app(PaymentService::class)->markCaptured($result->payment, $fake->fetch($fake->simulate($result->payment->gateway_order_id)['razorpay_payment_id']), 'webhook');
        }

        $sellerOrder = SellerOrder::query()->where('sales_order_id', $result->order->id)->firstOrFail();
        fulfilment()->accept($sellerOrder);
        fulfilmentScans($sellerOrder, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered']);

        return [$result->order->refresh(), $sellerOrder->refresh()];
    }

    /**
     * An admin whose role holds the given permissions (default: every Promotion permission).
     *
     * @param  list<string>|null  $permissions
     */
    function promotionAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Promotion/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Marketing',
            'slug' => 'promotion_marketing_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Marketing Admin',
            'email' => uniqid('promotion-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
