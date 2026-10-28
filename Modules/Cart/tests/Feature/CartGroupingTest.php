<?php

use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\Seller;
use Modules\Storefront\Contracts\DeliveryEstimator;
use Modules\Storefront\DTO\DeliveryEstimate;

require_once __DIR__.'/../Helpers.php';

test('lines are grouped per seller with their own subtotals', function () {
    $first = Seller::factory()->active()->create(['store_name' => 'Asha Textiles']);
    $second = Seller::factory()->active()->create(['store_name' => 'Kiran Crafts']);
    $cart = guestCartWith([
        [cartVariant(['price' => '100.00', 'mrp' => '100.00'], $first), 2],
        cartVariant(['price' => '300.00', 'mrp' => '300.00'], $second),
        cartVariant(['price' => '50.00', 'mrp' => '50.00'], $first),
    ]);

    $groups = cartService()->snapshot($cart->fresh())->groups;

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->sellerId)->toBe($first->id)
        ->and($groups[0]->sellerName)->toBe('Asha Textiles')
        ->and($groups[0]->lines)->toHaveCount(2)
        ->and($groups[0]->subtotal()->toDecimal())->toBe('250.00')
        ->and($groups[0]->quantity())->toBe(3)
        ->and($groups[1]->sellerName)->toBe('Kiran Crafts')
        ->and($groups[1]->subtotal()->toDecimal())->toBe('300.00');
});

test('each group shows its delivery date once a pincode is known', function () {
    app()->instance(DeliveryEstimator::class, new class implements DeliveryEstimator
    {
        public function estimate(Product $product, ?ProductVariant $variant, string $pincode): DeliveryEstimate
        {
            return DeliveryEstimate::serviceable($pincode, true, now()->setDate(2026, 11, 3), now()->setDate(2026, 11, 3));
        }
    });
    $cart = guestCartWith([cartVariant()]);

    expect(cartService()->snapshot($cart->fresh())->groups[0]->eddText)->toBeNull();

    cartService()->setDeliveryPincode($cart, '560001');

    expect(cartService()->snapshot($cart->fresh())->groups[0]->eddText)->toBe('Tue, 3 Nov');
});
