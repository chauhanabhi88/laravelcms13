<?php

use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Promotion\Exceptions\CouponException;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Services\CouponService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    promotionSetup();
});

test('an applied coupon becomes a discount row of the price summary', function () {
    $customer = orderCustomer();
    orderCart($customer, [[cartVariant(['price' => '250', 'mrp' => '500']), 2]]);
    promotionCoupon(['code' => 'SAVE50', 'value' => '50', 'min_cart_value' => '199']);

    $evaluation = applyCoupon($customer, 'save50');
    $totals = promotionTotals($customer);
    $row = $totals->adjustment(TotalsAdjustment::COUPON);

    expect($evaluation->coupon->code)->toBe('SAVE50')
        ->and(customerCart($customer)->coupon_code)->toBe('SAVE50')
        ->and($row->amount->toDecimal())->toBe('-50.00')
        ->and($row->label)->toBe('Coupon (SAVE50)')
        ->and($row->meta['funded_by'])->toBe('platform')
        ->and($totals->grandTotal()->toDecimal())->toBe('450.00')
        ->and($totals->youSave()->toDecimal())->toBe('550.00');
});

test('applying an invalid code throws with the reason and keeps the cart unchanged', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '100', 'mrp' => '100'])]);
    promotionCoupon(['code' => 'BIG', 'min_cart_value' => '500']);

    expect(fn () => applyCoupon($customer, 'NOSUCH'))->toThrow(CouponException::class, 'NOSUCH is not a valid coupon code.')
        ->and(fn () => applyCoupon($customer, 'BIG'))->toThrow(CouponException::class, 'Add ₹400 more to use BIG (minimum order ₹500).')
        ->and(customerCart($customer)->coupon_code)->toBeNull();
});

test('a coupon that stops applying adds nothing and the box explains why', function () {
    $customer = orderCustomer();
    $variant = cartVariant(['price' => '300', 'mrp' => '300']);
    orderCart($customer, [[$variant, 2]]);
    promotionCoupon(['code' => 'MIN500', 'value' => '50', 'min_cart_value' => '500']);
    applyCoupon($customer, 'MIN500');

    $cart = customerCart($customer);
    $item = $cart->items()->firstOrFail();
    cartService()->update($cart, $item, 1);

    $snapshot = cartService()->snapshot(customerCart($customer));
    $totals = promotionTotals($customer);
    $status = app(CouponService::class)->status($snapshot, $totals);

    expect($totals->adjustment(TotalsAdjustment::COUPON))->toBeNull()
        ->and($status['applied'])->toBeFalse()
        ->and($status['code'])->toBe('MIN500')
        ->and($status['message'])->toBe('Add ₹200 more to use MIN500 (minimum order ₹500).');
});

test('the best automatic offer applies without a code', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '1000', 'mrp' => '1000'])]);
    Coupon::factory()->automatic()->flat('50')->create(['name' => 'Flat 50']);
    Coupon::factory()->automatic()->percent('10')->create(['name' => 'Ten percent']);
    Coupon::factory()->automatic()->flat('500')->create(['name' => 'Too picky', 'min_cart_value' => '5000']);

    $row = promotionTotals($customer)->adjustment(TotalsAdjustment::OFFER);

    expect($row->amount->toDecimal())->toBe('-100.00')
        ->and($row->label)->toBe('Offer: Ten percent');
});

test('a code coupon replaces the automatic offer unless stacking is allowed and both are stackable', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '1000', 'mrp' => '1000'])]);
    Coupon::factory()->automatic()->stackable()->flat('30')->create(['name' => 'Auto 30']);
    promotionCoupon(['code' => 'CODE70', 'value' => '70', 'stackable' => config('core.yes')]);
    applyCoupon($customer, 'CODE70');

    $alone = promotionTotals($customer);

    expect($alone->adjustment(TotalsAdjustment::COUPON)->amount->toDecimal())->toBe('-70.00')
        ->and($alone->adjustment(TotalsAdjustment::OFFER))->toBeNull();

    promotionSettings(['allow_stacking' => 1]);
    $stacked = promotionTotals($customer);

    expect($stacked->adjustment(TotalsAdjustment::OFFER)->amount->toDecimal())->toBe('-30.00')
        ->and($stacked->grandTotal()->toDecimal())->toBe('900.00');
});

test('all promotions together never take more than the configured share of the cart', function () {
    promotionSettings(['max_coupon_discount_percent_of_cart' => 50]);
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '200', 'mrp' => '200']), cartVariant(['price' => '100', 'mrp' => '100'])]);
    promotionCoupon(['code' => 'HUGE', 'value' => '250']);
    applyCoupon($customer, 'HUGE');

    $row = promotionTotals($customer)->adjustment(TotalsAdjustment::COUPON);
    $shareSum = array_sum(array_map(fn ($share) => $share->paise, $row->lineShares));

    expect($row->amount->toDecimal())->toBe('-150.00')
        ->and($shareSum)->toBe(15000);
});

test('a free-delivery coupon zeroes the delivery row', function () {
    promotionPaidDelivery('40', '999');
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '300', 'mrp' => '300'])]);
    Coupon::factory()->freeDelivery()->create(['code' => 'SHIPFREE']);

    expect(promotionTotals($customer)->deliveryCharge()->toDecimal())->toBe('40.00');

    applyCoupon($customer, 'SHIPFREE');
    $totals = promotionTotals($customer);

    expect($totals->deliveryCharge()->toDecimal())->toBe('0.00')
        ->and($totals->adjustment(TotalsAdjustment::COUPON)->amount->toDecimal())->toBe('0.00')
        ->and($totals->adjustment(TotalsAdjustment::COUPON)->meta['delivery_waived'])->toBe('40.00')
        ->and($totals->grandTotal()->toDecimal())->toBe('300.00');
});

test('available offers list usable coupons first, with the reason for the others', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '400', 'mrp' => '400'])]);
    promotionCoupon(['code' => 'SMALL', 'value' => '20']);
    promotionCoupon(['code' => 'LARGE', 'value' => '60']);
    promotionCoupon(['code' => 'LATER', 'value' => '99', 'min_cart_value' => '1000']);
    promotionCoupon(['code' => 'HIDDEN', 'is_visible' => config('core.no')]);
    Coupon::factory()->automatic()->create(['code' => 'AUTOONE']);

    $offers = app(CouponService::class)->offers(cartService()->snapshot(customerCart($customer)));
    $codes = array_map(fn (array $row) => $row['coupon']->code, $offers);

    expect($codes)->toBe(['LARGE', 'SMALL', 'LATER'])
        ->and($offers[2]['evaluation']->message)->toBe('Add ₹600 more to use LATER (minimum order ₹1,000).');
});
