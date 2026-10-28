<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Core\Services\SequenceService;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderStatusService;

require_once __DIR__.'/../Helpers.php';

test('the customer-level status is derived from the seller orders', function (array $statuses, string $current, string $expected, bool $cancelledItems = false) {
    expect(OrderStatusService::derive($statuses, $current, $cancelledItems))->toBe($expected);
})->with([
    'all waiting for payment' => [['pending_payment', 'pending_payment'], 'pending_payment', 'pending_payment'],
    'waiting for the COD OTP' => [['pending_payment'], 'pending_cod_verification', 'pending_cod_verification'],
    'all accepted' => [['pending_acceptance', 'ready_to_ship'], 'pending_payment', 'confirmed'],
    'one cancelled, one waiting' => [['cancelled', 'pending_acceptance'], 'confirmed', 'partially_cancelled'],
    'an item cancelled' => [['pending_acceptance'], 'confirmed', 'partially_cancelled', true],
    'all cancelled' => [['cancelled', 'cancelled'], 'confirmed', 'cancelled'],
    'one shipped, one packed' => [['shipped', 'packed'], 'confirmed', 'partially_shipped'],
    'all on the way' => [['shipped', 'out_for_delivery'], 'partially_shipped', 'shipped'],
    'one delivered, one shipped' => [['delivered', 'shipped'], 'shipped', 'shipped'],
    'all delivered' => [['delivered', 'return_requested'], 'shipped', 'delivered'],
    'delivered plus a cancelled one' => [['delivered', 'cancelled'], 'shipped', 'delivered'],
    'all completed' => [['completed', 'completed'], 'delivered', 'completed'],
    'returned to origin' => [['rto_delivered'], 'shipped', 'cancelled'],
]);

test('a seller order moves through the SRS graph and refreshes its order', function () {
    $order = SalesOrder::factory()->withSellerOrders(2)->create();
    [$first, $second] = $order->sellerOrders()->get()->all();
    $statuses = app(OrderStatusService::class);

    $statuses->transition($first, SellerOrder::STATUS_READY_TO_SHIP);
    $statuses->transition($first->fresh(), SellerOrder::STATUS_PACKED);
    $statuses->transition($first->fresh(), SellerOrder::STATUS_SHIPPED);

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_PARTIALLY_SHIPPED)
        ->and($first->fresh())
        ->accepted_at->not->toBeNull()
        ->packed_at->not->toBeNull()
        ->shipped_at->not->toBeNull();

    $statuses->transition($second, SellerOrder::STATUS_CANCELLED);
    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_SHIPPED);
});

test('transitions outside the graph are refused', function () {
    $sellerOrder = SalesOrder::factory()->withSellerOrders(1)->create()->sellerOrders()->firstOrFail();

    expect(fn () => app(OrderStatusService::class)->transition($sellerOrder, SellerOrder::STATUS_DELIVERED))->toThrow(InvalidStateTransition::class);
});

test('ready to ship assigns the seller invoice number once', function () {
    $sellerOrder = SalesOrder::factory()->withSellerOrders(1)->create()->sellerOrders()->with('seller')->firstOrFail();
    $statuses = app(OrderStatusService::class);

    $statuses->transition($sellerOrder, SellerOrder::STATUS_READY_TO_SHIP);
    $invoice = $sellerOrder->fresh()->invoice_no;

    expect($invoice)->toStartWith($sellerOrder->seller->code.'/'.app(SequenceService::class)->financialYear().'/')
        ->and($statuses->assignInvoiceNumber($sellerOrder->fresh()))->toBe($invoice)
        ->and($sellerOrder->fresh()->invoice_date)->not->toBeNull();
});

test('delivery adds the units to the products sold count', function () {
    $variant = cartVariant();
    orderSetup();
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 3]]);
    $sellerOrder = placeOrder($customer)->order->sellerOrders()->firstOrFail();
    $statuses = app(OrderStatusService::class);

    foreach ([SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED, SellerOrder::STATUS_SHIPPED, SellerOrder::STATUS_OUT_FOR_DELIVERY, SellerOrder::STATUS_DELIVERED] as $status) {
        $statuses->transition($sellerOrder->fresh(), $status);
    }

    expect((int) DB::table('product')->where('id', $variant->product_id)->value('sold_count'))->toBe(3)
        ->and($sellerOrder->salesOrder()->first()->status)->toBe(SalesOrder::STATUS_DELIVERED);
});
