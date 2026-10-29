<?php

use Modules\Catalog\Models\CatalogCategory;
use Modules\Customer\Models\Customer;
use Modules\Order\DTO\CheckoutSession;
use Modules\Product\Models\Product;
use Modules\Promotion\DTO\CouponEvaluation;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Services\CouponService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    promotionSetup();
});

test('a valid flat coupon discounts the cart and spreads the discount over the lines', function () {
    $customer = orderCustomer();
    $a = cartVariant(['price' => '300', 'mrp' => '600']);
    $b = cartVariant(['price' => '100', 'mrp' => '200']);
    orderCart($customer, [$a, $b]);
    $coupon = promotionCoupon(['code' => 'SAVE40', 'value' => '40']);

    $evaluation = evaluateCoupon($coupon, $customer);
    $shares = array_map(fn ($share) => $share->toDecimal(), $evaluation->lineShares);

    expect($evaluation->valid)->toBeTrue()
        ->and($evaluation->discount->toDecimal())->toBe('40.00')
        ->and(array_values($shares))->toEqualCanonicalizing(['30.00', '10.00']);
});

test('each failing rule gives its own reason and message', function (string $case, string $reason, string $message) {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '499', 'mrp' => '999'])]);

    $coupon = match ($case) {
        'inactive' => Coupon::factory()->disabled()->create(['code' => 'OFF10']),
        'not_started' => promotionCoupon(['code' => 'OFF10', 'starts_at' => now()->addDays(2)]),
        'expired' => promotionCoupon(['code' => 'OFF10', 'ends_at' => now()->subMinute()]),
        'channel' => promotionCoupon(['code' => 'OFF10', 'channels' => ['app']]),
        'new_customer' => promotionCoupon(['code' => 'OFF10', 'new_customer_days' => 7]),
        'min_cart' => promotionCoupon(['code' => 'OFF10', 'min_cart_value' => '619']),
        'payment' => promotionCoupon(['code' => 'OFF10', 'payment_methods' => ['prepaid']]),
        'not_yours' => Coupon::factory()->forCustomer(Customer::factory()->create())->create(['code' => 'OFF10']),
    };

    if ($case === 'new_customer') {
        Customer::query()->whereKey($customer->id)->update(['created_at' => now()->subDays(30)]);
        $customer->refresh();
    }

    $evaluation = evaluateCoupon($coupon, $customer, $case === 'payment' ? CheckoutSession::METHOD_COD : null);

    expect($evaluation->valid)->toBeFalse()
        ->and($evaluation->reason)->toBe($reason)
        ->and($evaluation->message)->toBe($message);
})->with([
    ['inactive', CouponEvaluation::INACTIVE, 'OFF10 is not active.'],
    ['not_started', CouponEvaluation::NOT_STARTED, 'OFF10 can be used from '.now()->addDays(2)->format('d M Y').'.'],
    ['expired', CouponEvaluation::EXPIRED, 'OFF10 has expired.'],
    ['channel', CouponEvaluation::CHANNEL, 'OFF10 can be used only on the app.'],
    ['new_customer', CouponEvaluation::NEW_CUSTOMER, 'OFF10 is for customers who joined in the last 7 days.'],
    ['min_cart', CouponEvaluation::MIN_CART, 'Add ₹120 more to use OFF10 (minimum order ₹619).'],
    ['payment', CouponEvaluation::PAYMENT_METHOD, 'OFF10 is valid only with online payment.'],
    ['not_yours', CouponEvaluation::NOT_YOURS, 'OFF10 is not available on your account.'],
]);

test('usage limits count applied usages, in total and per customer', function () {
    $first = orderCustomer();
    $coupon = promotionCoupon(['code' => 'ONCE', 'usage_limit_total' => 1, 'usage_limit_per_customer' => 1]);
    placeWithCoupon($first, [cartVariant()], 'ONCE');

    orderCart($first, [cartVariant()]);
    $again = evaluateCoupon($coupon, $first);

    $second = orderCustomer();
    orderCart($second, [cartVariant()]);
    $exhausted = evaluateCoupon($coupon, $second);

    expect($again->reason)->toBe(CouponEvaluation::EXHAUSTED)
        ->and($exhausted->reason)->toBe(CouponEvaluation::EXHAUSTED)
        ->and($exhausted->message)->toBe('ONCE has been fully redeemed.');

    $coupon->forceFill(['usage_limit_total' => null])->save();

    expect(evaluateCoupon($coupon, $first)->reason)->toBe(CouponEvaluation::ALREADY_USED)
        ->and(evaluateCoupon($coupon, $second)->valid)->toBeTrue();
});

test('a first-order coupon refuses customers who already ordered, and COD when the setting says so', function () {
    $coupon = Coupon::factory()->firstOrderOnly()->create(['code' => 'FIRST50']);
    $newcomer = orderCustomer();
    orderCart($newcomer, [cartVariant()]);

    $regular = orderCustomer();
    placeWithCoupon($regular, [cartVariant()], null);
    orderCart($regular, [cartVariant()]);

    expect(evaluateCoupon($coupon, $newcomer)->valid)->toBeTrue()
        ->and(evaluateCoupon($coupon, $regular)->message)->toBe('FIRST50 is for your first order only.');

    promotionSettings(['first_order_prepaid_only' => 1]);

    expect(evaluateCoupon($coupon, $newcomer, CheckoutSession::METHOD_COD)->reason)->toBe(CouponEvaluation::PAYMENT_METHOD)
        ->and(evaluateCoupon($coupon, $newcomer, CheckoutSession::METHOD_PREPAID)->valid)->toBeTrue();
});

test('guests skip customer rules but personal coupons ask them to log in', function () {
    $cart = guestCartWith([cartVariant()]);
    $snapshot = cartService()->snapshot($cart);
    $service = app(CouponService::class);

    $firstOrder = Coupon::factory()->firstOrderOnly()->create(['code' => 'WELCOME']);
    $personal = Coupon::factory()->forCustomer(Customer::factory()->create())->create(['code' => 'MINE']);

    expect($service->evaluate($firstOrder, $snapshot)->valid)->toBeTrue()
        ->and($service->evaluate($personal, $snapshot)->reason)->toBe(CouponEvaluation::LOGIN_REQUIRED);
});

test('scopes limit the discount to the lines they cover', function () {
    $customer = orderCustomer();
    $seller = orderSeller();
    $mine = cartVariant(['price' => '400', 'mrp' => '400'], $seller);
    $other = cartVariant(['price' => '600', 'mrp' => '600']);
    orderCart($customer, [$mine, $other]);
    $mineProduct = Product::query()->findOrFail($mine->product_id);
    $parent = CatalogCategory::query()->findOrFail($mineProduct->catalog_category_id)->pathIds()[0] ?? $mineProduct->catalog_category_id;

    $byProduct = Coupon::factory()->percent('10')->scoped(Coupon::SCOPE_PRODUCT, [$mine->product_id])->create();
    $bySeller = Coupon::factory()->percent('10')->scoped(Coupon::SCOPE_SELLER, [$seller->id])->create();
    $byCategory = Coupon::factory()->percent('10')->scoped(Coupon::SCOPE_CATEGORY, [$parent])->create();
    $noMatch = Coupon::factory()->percent('10')->scoped(Coupon::SCOPE_PRODUCT, [999999])->create(['code' => 'NOPE']);
    $sellerCoupon = Coupon::factory()->percent('10')->forSeller($seller)->create();

    foreach ([$byProduct, $bySeller, $byCategory, $sellerCoupon] as $coupon) {
        $evaluation = evaluateCoupon($coupon, $customer);

        expect($evaluation->valid)->toBeTrue()
            ->and($evaluation->eligibleSubtotal->toDecimal())->toBe('400.00')
            ->and($evaluation->discount->toDecimal())->toBe('40.00')
            ->and(count($evaluation->lineShares))->toBe(1);
    }

    expect(evaluateCoupon($noMatch, $customer)->message)->toBe('NOPE doesn\'t apply to the items in your cart.');
});

test('percent coupons respect the cap and flat coupons never exceed the eligible subtotal', function () {
    $customer = orderCustomer();
    orderCart($customer, [[cartVariant(['price' => '500', 'mrp' => '500']), 2]]);

    expect(evaluateCoupon(Coupon::factory()->percent('20', '150')->create(), $customer)->discount->toDecimal())->toBe('150.00')
        ->and(evaluateCoupon(Coupon::factory()->percent('20')->create(), $customer)->discount->toDecimal())->toBe('200.00')
        ->and(evaluateCoupon(Coupon::factory()->flat('5000')->create(), $customer)->discount->toDecimal())->toBe('1000.00');
});

test('a free-delivery coupon waives the delivery charge and needs one to waive', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '300', 'mrp' => '300'])]);
    $coupon = Coupon::factory()->freeDelivery()->create(['code' => 'SHIPFREE']);

    expect(evaluateCoupon($coupon, $customer)->reason)->toBe(CouponEvaluation::DELIVERY_FREE);

    promotionPaidDelivery('40', '999');
    $evaluation = evaluateCoupon($coupon, $customer);

    expect($evaluation->valid)->toBeTrue()
        ->and($evaluation->discount->toDecimal())->toBe('0.00')
        ->and($evaluation->deliveryWaived->toDecimal())->toBe('40.00');
});
