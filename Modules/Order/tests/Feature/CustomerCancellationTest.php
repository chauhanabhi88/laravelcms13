<?php

use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Services\OrderStatusService;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\PaymentService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
});

test('a customer cancels their COD order before shipment and the stock comes back', function () {
    $variant = cartVariant(['stock' => 5]);
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 2]]);
    $order = placeOrder($customer)->order;

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.orders.cancel', ['orderNo' => $order->order_no]), ['reason' => 'changed_mind'])
        ->assertRedirect(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED)
        ->and(SellerOrder::query()->where('sales_order_id', $order->id)->first())
        ->status->toBe(SellerOrder::STATUS_CANCELLED)
        ->cancelled_by->toBe('customer')
        ->cancel_reason->toBe('Changed my mind')
        ->and(orderVariant($variant)->reserved)->toBe(0)
        ->and(Payment::query()->forOrder($order->id)->value('status'))->toBe(Payment::STATUS_CANCELLED);
});

test('cancelling one item of a two-seller order leaves the rest', function () {
    $a = cartVariant(['stock' => 5]);
    $b = cartVariant(['stock' => 5]);
    $customer = orderCustomer();
    orderCart($customer, [$a, $b]);
    $order = placeOrder($customer)->order;
    $item = SalesOrderItem::query()->where('sales_order_id', $order->id)->where('variant_id', $a->id)->firstOrFail();

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.orders.cancel', ['orderNo' => $order->order_no]), ['reason' => 'other', 'items' => [$item->id]])
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_PARTIALLY_CANCELLED)
        ->and($item->fresh()->status)->toBe(SalesOrderItem::STATUS_CANCELLED)
        ->and(orderVariant($a)->reserved)->toBe(0)
        ->and(orderVariant($b)->reserved)->toBe(1);
});

test('a prepaid cancellation refunds the cancelled lines to source', function () {
    $variant = cartVariant(['price' => '650', 'stock' => 5]);
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 2]]);
    $result = placeOrder($customer, orderCheckout($customer, CheckoutSession::METHOD_PREPAID));
    $fake = app(FakeGateway::class);
    app(PaymentService::class)->markCaptured($result->payment, $fake->fetch($fake->simulate($result->payment->gateway_order_id)['razorpay_payment_id']), 'webhook');

    app(OrderCancellationService::class)->cancelForCustomer($result->order->fresh(), null, 'Changed my mind', $customer);

    expect(PaymentRefund::query()->where('payment_id', $result->payment->id)->first())
        ->not->toBeNull()
        ->mode->toBe(PaymentRefund::MODE_SOURCE)
        ->and(PaymentRefund::query()->where('payment_id', $result->payment->id)->firstOrFail()->amount->toDecimal())->toBe('1300.00');
});

test('a shipped seller order cannot be cancelled', function () {
    $variant = cartVariant();
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $order = placeOrder($customer)->order;
    $sellerOrder = $order->sellerOrders()->firstOrFail();
    $statuses = app(OrderStatusService::class);
    foreach ([SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED, SellerOrder::STATUS_SHIPPED] as $status) {
        $statuses->transition($sellerOrder->fresh(), $status);
    }

    expect(fn () => app(OrderCancellationService::class)->cancelForCustomer($order->fresh(), null, 'Too late', $customer))
        ->toThrow(OrderException::class);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.orders.cancel', ['orderNo' => $order->order_no]), ['reason' => 'changed_mind'])
        ->assertSessionHas('error', trans('order::front.errors.not_cancellable'));

    expect(orderVariant($variant)->reserved)->toBe(1);
});

test('packed orders are cancellable by the customer only when the setting allows it', function () {
    $variant = cartVariant();
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $sellerOrder = placeOrder($customer)->order->sellerOrders()->firstOrFail();
    app(OrderStatusService::class)->transition($sellerOrder, SellerOrder::STATUS_READY_TO_SHIP);
    app(OrderStatusService::class)->transition($sellerOrder->fresh(), SellerOrder::STATUS_PACKED);
    $cancellations = app(OrderCancellationService::class);

    expect($cancellations->canCancel($sellerOrder->fresh(), 'customer'))->toBeFalse()
        ->and($cancellations->canCancel($sellerOrder->fresh(), 'admin'))->toBeTrue();

    orderSettings(['allow_customer_cancel_after_packed' => 1]);
    expect($cancellations->canCancel($sellerOrder->fresh(), 'customer'))->toBeTrue();
});

test('the reason must be one of the listed ones', function () {
    $customer = orderCustomer();
    $order = SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create();

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.orders.cancel', ['orderNo' => $order->order_no]), ['reason' => 'because'])
        ->assertSessionHasErrors('reason');
});
