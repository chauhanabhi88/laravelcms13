<?php

use Illuminate\Support\Facades\DB;
use Modules\Cart\DTO\LineIssue;
use Modules\Cart\Services\CartTotals;
use Modules\Product\Enums\ProductStatus;

require_once __DIR__.'/../Helpers.php';

test('a price change is flagged once and price_at_add follows the new price', function () {
    $variant = cartVariant(['price' => '499.00', 'mrp' => '999.00']);
    $result = cartService()->add(guestOwner(), $variant->id, 1);
    cartUpdateVariant($variant, ['price' => '449.00']);

    $line = cartService()->snapshot($result->cart->fresh())->lines()[0];

    expect($line->hasIssue(LineIssue::PRICE_CHANGED))->toBeTrue()
        ->and($line->issues[0]->message)->toContain('₹499')->toContain('₹449')
        ->and($line->isPurchasable())->toBeTrue()
        ->and($line->unitPrice->toDecimal())->toBe('449.00')
        ->and($result->item->fresh()->price_at_add->toDecimal())->toBe('449.00');

    // Shown once: the next view has nothing to report.
    expect(cartService()->snapshot($result->cart->fresh())->lines()[0]->issues)->toBe([]);
});

test('the quantity drops to the units left when stock falls', function () {
    $variant = cartVariant(['stock' => 10]);
    $result = cartService()->add(guestOwner(), $variant->id, 6);
    cartUpdateVariant($variant, ['stock' => 3]);

    $line = cartService()->snapshot($result->cart->fresh())->lines()[0];

    expect($line->hasIssue(LineIssue::LOW_STOCK))->toBeTrue()
        ->and($line->quantity)->toBe(3)
        ->and($line->isPurchasable())->toBeTrue()
        ->and($result->item->fresh()->quantity)->toBe(3);
});

test('out-of-stock lines move to "not available" and leave the totals', function () {
    $inStock = cartVariant(['price' => '200.00', 'mrp' => '400.00']);
    $soldOut = cartVariant(['price' => '300.00', 'mrp' => '300.00']);
    $cart = guestCartWith([$inStock, $soldOut]);
    cartUpdateVariant($soldOut, ['stock' => 0]);

    $snapshot = cartService()->snapshot($cart->fresh());

    expect($snapshot->lines())->toHaveCount(1)
        ->and($snapshot->unavailable)->toHaveCount(1)
        ->and($snapshot->unavailable[0]->hasIssue(LineIssue::OUT_OF_STOCK))->toBeTrue()
        ->and($snapshot->canCheckout())->toBeFalse()
        ->and(app(CartTotals::class)->calculate($snapshot)->subtotal->toDecimal())->toBe('200.00');
});

test('lines whose product or seller stopped selling are unavailable', function (string $case) {
    $variant = cartVariant();
    $cart = guestCartWith([$variant]);

    match ($case) {
        'paused' => DB::table('product')->where('id', $variant->product_id)->update(['status' => ProductStatus::Paused->value]),
        'seller suspended' => DB::table('seller')->where('id', $variant->seller_id)->update(['status' => 'suspended']),
        'variant disabled' => cartUpdateVariant($variant, ['status' => config('core.disabled')]),
        'product deleted' => DB::table('product')->where('id', $variant->product_id)->update(['deleted_at' => now()]),
    };

    $snapshot = cartService()->snapshot($cart->fresh());

    expect($snapshot->groups)->toBe([])
        ->and($snapshot->unavailable[0]->hasIssue(LineIssue::UNAVAILABLE))->toBeTrue()
        ->and($snapshot->hasBlockingIssues())->toBeTrue();
})->with(['paused', 'seller suspended', 'variant disabled', 'product deleted']);

test('a read-only snapshot shows corrections without saving them', function () {
    $variant = cartVariant(['stock' => 10]);
    $result = cartService()->add(guestOwner(), $variant->id, 5);
    cartUpdateVariant($variant, ['stock' => 2, 'price' => '450.00']);

    $line = cartService()->snapshot($result->cart->fresh(), persist: false)->lines()[0];

    expect($line->quantity)->toBe(2)
        ->and($result->item->fresh()->quantity)->toBe(5)
        ->and($result->item->fresh()->price_at_add->toDecimal())->toBe('499.00');
});
