<?php

use Modules\Core\Support\Money;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\ShipmentService;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\RefundService;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
});

function returns(): ReturnService
{
    return app(ReturnService::class);
}

test('pickup, receipt and a passed QC restock the item and refund the prepaid payment', function () {
    [$order, $sellerOrder, $variant, $customer] = deliveredOrder(['price' => '650', 'stock' => 4], 1, CheckoutSession::METHOD_PREPAID);
    $stockBefore = orderVariant($variant)->stock;
    $return = raiseReturn($sellerOrder, $customer);

    reverseScans($return, ['picked_up', 'in_transit']);

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_PICKED_UP)
        ->and($return->picked_up_at)->not->toBeNull()
        ->and($return->refund_status)->toBeNull();

    reverseScans($return, ['delivered']);

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_RECEIVED);

    returns()->qcPass($return, 'All good');
    $refund = PaymentRefund::query()->where('return_request_id', $return->id)->firstOrFail();

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_REFUND_INITIATED)
        ->and($refund->mode)->toBe(PaymentRefund::MODE_SOURCE)
        ->and($refund->amount->toDecimal())->toBe('650.00')
        ->and(orderVariant($variant)->stock)->toBe($stockBefore + 1)
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->first())
        ->status->toBe(SalesOrderItem::STATUS_RETURNED)
        ->returned_qty->toBe(1)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_RETURNED);

    app(RefundService::class)->markProcessed($refund, null, null, 'rfnd_test');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_REFUNDED)
        ->and($return->refund_status)->toBe(ReturnRequest::REFUND_REFUNDED)
        ->and(Payment::query()->forOrder($order->id)->value('status'))->toBe(Payment::STATUS_REFUNDED);
});

test('a trusted customer is refunded at pickup', function () {
    $customer = orderCustomer();
    deliveredOrder(customer: $customer, method: CheckoutSession::METHOD_PREPAID);
    deliveredOrder(customer: $customer, method: CheckoutSession::METHOD_PREPAID);
    [, $sellerOrder] = deliveredOrder(customer: $customer, method: CheckoutSession::METHOD_PREPAID);
    app(FakeGateway::class)->refundOutcome = PaymentRefund::STATUS_PROCESSED;
    $return = raiseReturn($sellerOrder, $customer);

    reverseScans($return, ['picked_up']);

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_PICKED_UP)
        ->and($return->refund_status)->toBe(ReturnRequest::REFUND_REFUNDED)
        ->and($return->refunded_at)->not->toBeNull();

    reverseScans($return, ['in_transit', 'delivered']);
    returns()->qcPass($return->refresh());

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_REFUNDED)
        ->and(PaymentRefund::query()->where('return_request_id', $return->id)->count())->toBe(1);
});

test('a COD return pays out to the captured UPI id', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '300']);
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);

    returns()->qcPass($return->refresh());
    $refund = PaymentRefund::query()->where('return_request_id', $return->id)->firstOrFail();

    expect($refund->mode)->toBe(PaymentRefund::MODE_UPI)
        ->and($refund->beneficiary)->toEqual(['vpa' => 'buyer@okbank'])
        ->and($refund->status)->toBe(PaymentRefund::STATUS_PENDING)
        ->and($return->refresh()->status)->toBe(ReturnRequest::STATUS_REFUND_INITIATED);
});

test('the seller may QC a package the courier has not marked delivered yet', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(method: CheckoutSession::METHOD_PREPAID);
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up']);

    returns()->qcPass($return->refresh());

    expect($return->refresh()->received_at)->not->toBeNull()
        ->and($return->status)->toBe(ReturnRequest::STATUS_REFUND_INITIATED);
});

test('a failed QC waits for CX, who refunds part of it or closes it', function () {
    [, $sellerOrder, $variant, $customer] = deliveredOrder(['price' => '800', 'stock' => 3], 1, CheckoutSession::METHOD_PREPAID);
    $stock = orderVariant($variant)->stock;
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);

    returns()->qcFail($return->refresh(), 'Torn at the seam', null, ['reason' => 'damaged_return', 'description' => 'Torn', 'photos' => [returnPhoto()]]);

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_QC_FAILED)
        ->and($return->needsAttention())->toBeTrue()
        ->and(orderVariant($variant)->stock)->toBe($stock)
        ->and(ReturnClaim::query()->where('return_request_id', $return->id)->firstOrFail())
        ->reason->toBe('damaged_return')
        ->images->toHaveCount(1);

    returns()->forceRefund($return, Money::of('400'), returnsAdmin(), null, 'Half refund agreed');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_REFUND_INITIATED)
        ->and(PaymentRefund::query()->where('return_request_id', $return->id)->value('amount'))->toEqual(Money::of('400'))
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->value('status'))->toBe(SalesOrderItem::STATUS_RETURNED);
});

test('closing a failed QC keeps the items with the customer', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(method: CheckoutSession::METHOD_PREPAID);
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    returns()->qcFail($return->refresh(), 'Used item');

    returns()->close($return->refresh(), returnsAdmin(), 'Item was used');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_CLOSED)
        ->and(PaymentRefund::query()->where('return_request_id', $return->id)->exists())->toBeFalse()
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_DELIVERED)
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->value('status'))->toBe(SalesOrderItem::STATUS_ACTIVE);
});

test('a pickup cancelled by the courier goes back to approved for a new pickup', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);

    app(ShipmentService::class)->cancel(Shipment::query()->findOrFail($return->reverse_shipment_id), 'Customer not available');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_APPROVED)
        ->and($return->needsAttention())->toBeTrue();

    returns()->schedulePickup($return);

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_PICKUP_SCHEDULED)
        ->and(Shipment::query()->where('return_request_id', $return->id)->count())->toBe(2);
});

test('a partial return leaves the package delivered with the rest', function () {
    [, $sellerOrder, , $customer] = deliveredOrder([], 2, CheckoutSession::METHOD_PREPAID);
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);

    returns()->qcPass($return->refresh());

    expect($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_DELIVERED)
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->first())
        ->status->toBe(SalesOrderItem::STATUS_ACTIVE)
        ->returned_qty->toBe(1);
});

test('a customer cancels before pickup but not after', function () {
    returnsSettings(['auto_approve_max_return_rate' => 100]);
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up']);

    expect(fn () => returns()->cancel($return->refresh(), $customer))->toThrow(ReturnException::class);

    [, $other] = deliveredOrder(customer: $customer);
    $second = raiseReturn($other, $customer);
    returns()->cancel($second, $customer);

    expect($second->refresh()->status)->toBe(ReturnRequest::STATUS_CANCELLED)
        ->and(Shipment::query()->findOrFail($second->reverse_shipment_id)->status)->toBe(Shipment::STATUS_CANCELLED);
});
