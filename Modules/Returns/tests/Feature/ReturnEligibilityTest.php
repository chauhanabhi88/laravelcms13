<?php

use Illuminate\Support\Carbon;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\ProductVariant;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnEligibilityService;
use Modules\Returns\Services\ReturnService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
});

function eligibility(): ReturnEligibilityService
{
    return app(ReturnEligibilityService::class);
}

test('a delivered item is returnable until the end of its window day', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();
    $item->forceFill(['return_window_days' => 7])->save();
    $lastDay = $sellerOrder->delivered_at->copy()->addDays(7);

    expect(eligibility()->eligibleItems($sellerOrder->fresh(), $customer, $lastDay->copy()->setTime(23, 59, 0)))->toHaveCount(1)
        ->and(eligibility()->eligibleItems($sellerOrder->fresh(), $customer, $lastDay->copy()->addDay()->startOfDay()->addMinute()))->toBeEmpty();
});

test('nothing is returnable before delivery or for another customer', function () {
    [$order, $sellerOrder] = pendingFulfilmentOrder();
    $order->load('customer');

    expect(eligibility()->eligibleItems($sellerOrder, $order->customer))->toBeEmpty();

    [, $delivered] = deliveredOrder();

    expect(eligibility()->eligibleItems($delivered, orderCustomer()))->toBeEmpty();
});

test('return types follow the item policy', function (string $returnType, array $expected) {
    [, $sellerOrder, $variant, $customer] = deliveredOrder();
    SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->update(['return_type' => $returnType]);
    // A second in-stock variant of the same product makes an exchange possible.
    ProductVariant::factory()->create([
        'product_id' => $variant->product_id,
        'seller_id' => $variant->seller_id,
        'combination_key' => 'factory:2',
        'stock' => 5,
    ]);

    $items = eligibility()->eligibleItems($sellerOrder->fresh(), $customer);

    expect($items->isEmpty() ? [] : $items->first()->allowedTypes)->toBe($expected);
})->with([
    'return and exchange' => ['return_exchange', ['return', 'exchange']],
    'exchange only' => ['exchange_only', ['exchange']],
    'none' => ['none', []],
]);

test('exchange is offered only when another variant is in stock', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();

    expect(eligibility()->eligibleItems($sellerOrder, $customer)->first()->allowedTypes)->toBe(['return']);

    SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->update(['return_type' => 'exchange_only']);

    expect(eligibility()->eligibleItems($sellerOrder->fresh(), $customer))->toBeEmpty();
});

test('the remaining quantity excludes cancelled and returned units', function () {
    [, $sellerOrder, , $customer] = deliveredOrder([], 3);
    SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->update(['returned_qty' => 1, 'cancelled_qty' => 1]);

    expect(eligibility()->eligibleItems($sellerOrder->fresh(), $customer)->first()->maxQty)->toBe(1);

    SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->update(['returned_qty' => 2]);

    expect(eligibility()->eligibleItems($sellerOrder->fresh(), $customer))->toBeEmpty();
});

test('an open return blocks a second return of the same item', function () {
    [, $sellerOrder, , $customer] = deliveredOrder([], 2);
    $return = raiseReturn($sellerOrder, $customer);

    expect($return->status)->not->toBe(ReturnRequest::STATUS_CANCELLED)
        ->and(eligibility()->eligibleItems($sellerOrder->fresh(), $customer))->toBeEmpty()
        ->and(fn () => raiseReturn($sellerOrder->fresh(), $customer))->toThrow(ReturnException::class);
});

test('a cancelled return frees the item again', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    returnsSettings(['auto_approve' => 2]);
    $return = raiseReturn($sellerOrder, $customer);

    app(ReturnService::class)->cancel($return, $customer);

    expect(eligibility()->eligibleItems($sellerOrder->fresh(), $customer))->toHaveCount(1)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_DELIVERED)
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->value('status'))->toBe(SalesOrderItem::STATUS_ACTIVE);
});

test('the window end follows the delivery time', function () {
    Carbon::setTestNow('2026-11-10 10:00:00');
    [, $sellerOrder] = deliveredOrder();
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    expect(eligibility()->windowEndsAt($sellerOrder, $item)?->toDateTimeString())
        ->toBe($sellerOrder->delivered_at->copy()->addDays($item->return_window_days)->endOfDay()->toDateTimeString());

    Carbon::setTestNow();
});
