<?php

use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Cart\Services\CartTotals;
use Modules\Core\Support\Money;
use Modules\Seller\Models\Seller;

require_once __DIR__.'/../Helpers.php';

/**
 * Two lines from one seller: 2 × ₹499 (MRP ₹999) + 1 × ₹300 (MRP ₹300).
 */
function cartTotalsFixture(?Seller $seller = null): array
{
    $seller ??= Seller::factory()->active()->create();
    $cart = guestCartWith([
        [cartVariant(['price' => '499.00', 'mrp' => '999.00'], $seller), 2],
        cartVariant(['price' => '300.00', 'mrp' => '300.00'], $seller),
    ]);

    return [$cart, cartService()->snapshot($cart->fresh())];
}

test('MRP, discount and subtotal come from the purchasable lines', function () {
    [, $snapshot] = cartTotalsFixture();

    $totals = app(CartTotals::class)->calculate($snapshot);

    expect($totals->itemCount)->toBe(3)
        ->and($totals->totalMrp->toDecimal())->toBe('2298.00')
        ->and($totals->discount->toDecimal())->toBe('1000.00')
        ->and($totals->subtotal->toDecimal())->toBe('1298.00')
        ->and($totals->grandTotal()->toDecimal())->toBe('1298.00')
        ->and($totals->youSave()->toDecimal())->toBe('1000.00');
});

test('delivery is free by default and always listed', function () {
    [, $snapshot] = cartTotalsFixture();

    $delivery = app(CartTotals::class)->calculate($snapshot)->adjustment(TotalsAdjustment::DELIVERY);

    expect($delivery)->not->toBeNull()
        ->and($delivery->amount->isZero())->toBeTrue()
        ->and($delivery->displayAmount())->toBe('FREE');
});

test('threshold mode charges the fee below the free-delivery minimum only', function (string $minimum, string $expected) {
    cartSettings(['free_delivery' => 'threshold', 'free_delivery_min' => $minimum, 'delivery_fee' => '49']);
    [, $snapshot] = cartTotalsFixture();

    $totals = app(CartTotals::class)->calculate($snapshot);

    expect($totals->deliveryCharge()->toDecimal())->toBe($expected)
        ->and($totals->grandTotal()->toDecimal())->toBe(number_format(1298 + (float) $expected, 2, '.', ''));
})->with([
    'below the minimum' => ['1500', '49.00'],
    'at the minimum' => ['1298', '0.00'],
]);

test('per-seller-group mode charges each package below the minimum', function () {
    cartSettings(['free_delivery' => 'per_seller_group', 'free_delivery_min' => '500', 'delivery_fee' => '40']);
    $cart = guestCartWith([
        cartVariant(['price' => '600.00', 'mrp' => '600.00']),
        cartVariant(['price' => '200.00', 'mrp' => '200.00']),
        cartVariant(['price' => '100.00', 'mrp' => '100.00']),
    ]);

    $totals = app(CartTotals::class)->calculate(cartService()->snapshot($cart->fresh()));

    expect($totals->deliveryCharge()->toDecimal())->toBe('80.00');
});

test('the COD fee applies only when paying cash on delivery', function () {
    cartSettings(['cod_fee' => '25']);
    [, $snapshot] = cartTotalsFixture();
    $totals = app(CartTotals::class);

    expect($totals->calculate($snapshot)->codFee()->isZero())->toBeTrue()
        ->and($totals->calculate($snapshot, 'prepaid')->codFee()->isZero())->toBeTrue()
        ->and($totals->calculate($snapshot, CartTotals::PAYMENT_COD)->codFee()->toDecimal())->toBe('25.00')
        ->and($totals->calculate($snapshot, CartTotals::PAYMENT_COD)->grandTotal()->toDecimal())->toBe('1323.00');
});

test('a discount adjustment counts towards "you save"', function () {
    [, $snapshot] = cartTotalsFixture();
    $totals = app(CartTotals::class)->calculate($snapshot)
        ->withAdjustment(new TotalsAdjustment(TotalsAdjustment::COUPON, 'Coupon', Money::of('-100')));

    expect($totals->grandTotal()->toDecimal())->toBe('1198.00')
        ->and($totals->youSave()->toDecimal())->toBe('1100.00');
});

test('an empty cart totals to zero without adjustments', function () {
    $totals = app(CartTotals::class)->calculate(cartService()->snapshot(null));

    expect($totals->itemCount)->toBe(0)
        ->and($totals->adjustments)->toBe([])
        ->and($totals->grandTotal()->isZero())->toBeTrue();
});
