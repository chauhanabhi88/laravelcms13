<?php

use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Models\SalesOrder;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
});

test('two orders for the last unit: one succeeds, the other fails cleanly', function () {
    $variant = cartVariant(['stock' => 1]);
    $first = orderCustomer();
    $second = orderCustomer();
    orderCart($first, [$variant]);
    orderCart($second, [$variant]);

    // Both carts were revalidated while the unit was free; the transactions run one after the other.
    $won = placeOrder($first);

    expect(fn () => placeOrder($second))->toThrow(CheckoutException::class);

    expect($won->order->fresh()->status)->toBe(SalesOrder::STATUS_CONFIRMED)
        ->and(SalesOrder::query()->where('customer_id', $second->id)->exists())->toBeFalse()
        ->and(orderVariant($variant))->stock->toBe(1)->reserved->toBe(1);
});
