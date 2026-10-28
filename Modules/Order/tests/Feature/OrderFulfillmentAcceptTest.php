<?php

use Illuminate\Support\Facades\Queue;
use Modules\Logistics\Models\Shipment;
use Modules\Order\DTO\FulfilmentResult;
use Modules\Order\Jobs\RetryShipmentBooking;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    $this->courier = fulfilmentSetup();
});

test('accepting books the shipment with an AWB and assigns the invoice number', function () {
    [, $sellerOrder, $shipment] = pendingFulfilmentOrder();

    $result = fulfilment()->accept($sellerOrder);

    $sellerOrder->refresh();
    $shipment->refresh();

    expect($result->outcome($sellerOrder->id))->toBe(FulfilmentResult::OK)
        ->and($sellerOrder->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP)
        ->and($sellerOrder->accepted_at)->not->toBeNull()
        ->and($sellerOrder->invoice_no)->toStartWith($sellerOrder->seller()->value('code').'/')
        ->and($shipment->status)->toBe(Shipment::STATUS_AWB_ASSIGNED)
        ->and($shipment->awb)->toStartWith('FAKE')
        ->and(fakeCourier()->called('assignAwb'))->toBe(1);
});

test('a courier failure keeps the order accepted with the AWB pending and queues a retry', function () {
    Queue::fake();
    [, $sellerOrder] = pendingFulfilmentOrder();
    fakeCourier()->failWith = 'Courier API down';

    $result = fulfilment()->accept($sellerOrder);
    $sellerOrder->refresh();

    expect($result->outcome($sellerOrder->id))->toBe(FulfilmentResult::AWB_PENDING)
        ->and($sellerOrder->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE)
        ->and($sellerOrder->accepted_at)->not->toBeNull()
        ->and($sellerOrder->isAwaitingAwb())->toBeTrue()
        ->and($sellerOrder->awb_failures)->toBe(1)
        ->and($sellerOrder->invoice_no)->toBeNull();

    Queue::assertPushed(RetryShipmentBooking::class, fn (RetryShipmentBooking $job): bool => $job->sellerOrderId === $sellerOrder->id);

    // The queued retry books it.
    fulfilment()->retryBooking($sellerOrder->id);

    expect($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP)
        ->and($sellerOrder->fresh()->invoice_no)->not->toBeNull();
});

test('retries stop after the configured attempts', function () {
    Queue::fake();
    config(['order.fulfilment.booking_backoff' => [60]]);
    [, $sellerOrder] = pendingFulfilmentOrder();

    fakeCourier()->failWith = 'down';
    fulfilment()->accept($sellerOrder);
    fakeCourier()->failWith = 'still down';
    fulfilment()->retryBooking($sellerOrder->id);

    expect($sellerOrder->fresh()->awb_failures)->toBe(2)
        ->and($sellerOrder->fresh()->isAwaitingAwb())->toBeTrue();
    Queue::assertPushed(RetryShipmentBooking::class, 1);
});

test('bulk accept handles each order on its own', function () {
    $seller = orderSeller();
    [, $first] = pendingFulfilmentOrder($seller);
    [$secondOrder, $second] = pendingFulfilmentOrder($seller);
    app(OrderCancellationService::class)->cancelOrder($secondOrder, OrderCancellationService::BY_CUSTOMER, 'Changed my mind');

    $result = fulfilment()->accept([$first, $second->fresh()]);

    expect($result->outcome($first->id))->toBe(FulfilmentResult::OK)
        ->and($result->outcome($second->id))->toBe(FulfilmentResult::FAILED)
        ->and($result->count(FulfilmentResult::OK))->toBe(1)
        ->and($first->fresh()->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP)
        ->and($second->fresh()->status)->toBe(SellerOrder::STATUS_CANCELLED);
});

test('the seller accepts selected orders from the desk', function () {
    $seller = orderSeller();
    [, $first] = pendingFulfilmentOrder($seller);
    [, $second] = pendingFulfilmentOrder($seller);

    $this->actingAs(fulfilmentOwner($seller), 'seller')
        ->from(route('seller.order.index'))
        ->post(route('seller.order.accept'), ['ids' => [$first->id, $second->id]])
        ->assertRedirect(route('seller.order.index'))
        ->assertSessionHas('success');

    expect($first->fresh()->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP)
        ->and($second->fresh()->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP);
});

test('auto-accept only takes orders of sellers who opted in', function () {
    $optedIn = orderSeller();
    $optedIn->forceFill(['auto_accept_orders' => (int) config('core.yes')])->save();
    $manual = orderSeller();

    [, $auto] = pendingFulfilmentOrder($optedIn);
    [, $notAuto] = pendingFulfilmentOrder($manual);

    $this->artisan('order:auto-accept')->assertSuccessful();

    expect($auto->fresh()->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP)
        ->and($notAuto->fresh()->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE)
        ->and($notAuto->fresh()->accepted_at)->toBeNull();
});
