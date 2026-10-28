<?php

use Illuminate\Support\Facades\Event;
use Modules\Notification\Services\NotificationHub;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Events\OrderConfirmed;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Events\PaymentFailed;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\PaymentService;
use Modules\Product\Models\ProductVariant;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
});

function prepaidOrder(?ProductVariant $variant = null): array
{
    $variant ??= cartVariant(['price' => '799', 'stock' => 5]);
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 2]]);
    $result = placeOrder($customer, orderCheckout($customer, CheckoutSession::METHOD_PREPAID));

    return [$result->order, $result->payment, $variant, $customer];
}

test('a captured payment releases the seller orders and confirms the order', function () {
    [$order, $payment] = prepaidOrder();
    $fake = app(FakeGateway::class);
    $callback = $fake->simulate($payment->gateway_order_id);

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.callback', ['orderNo' => $order->order_no]), $callback)
        ->assertRedirect($order->confirmationUrl());

    expect($order->fresh())
        ->status->toBe(SalesOrder::STATUS_CONFIRMED)
        ->payment_status->toBe(Payment::STATUS_CAPTURED)
        ->and(SellerOrder::query()->where('sales_order_id', $order->id)->value('status'))->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE);
});

test('the capture fires OrderConfirmed and OrderPaid once', function () {
    [$order, $payment] = prepaidOrder();
    Event::fake([OrderConfirmed::class, OrderPaid::class]);
    $gatewayPayment = app(FakeGateway::class)->fetch(app(FakeGateway::class)->simulate($payment->gateway_order_id)['razorpay_payment_id']);

    app(PaymentService::class)->markCaptured($payment, $gatewayPayment, 'webhook');
    app(PaymentService::class)->markCaptured($payment->fresh(), $gatewayPayment, 'webhook');

    Event::assertDispatchedTimes(OrderConfirmed::class, 1);
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

test('confirmation notifies the customer and the seller', function () {
    [$order, $payment] = prepaidOrder();
    $gatewayPayment = app(FakeGateway::class)->fetch(app(FakeGateway::class)->simulate($payment->gateway_order_id)['razorpay_payment_id']);

    $hub = $this->mock(NotificationHub::class);
    $hub->shouldReceive('event')->once()->withArgs(fn (string $key, $customer, array $data): bool => $key === 'order.placed'
        && $customer->id === $order->customer_id
        && $data['order_no'] === $order->order_no
        && $data['amount'] === '₹1,598')->andReturn([]);
    $hub->shouldReceive('eventForSeller')->once()->withArgs(fn (string $key, $seller, array $data): bool => $key === 'seller.order.new'
        && $data['order_no'] === $order->order_no.'-1'
        && $data['item_count'] === 2)->andReturn(1);

    app(PaymentService::class)->markCaptured($payment, $gatewayPayment, 'webhook');
});

test('a payment that expires cancels the order and releases the stock', function () {
    [$order, $payment, $variant] = prepaidOrder();
    expect(orderVariant($variant)->reserved)->toBe(2);

    $payment->forceFill(['expires_at' => now()->subMinute()])->save();
    app(PaymentService::class)->expireStale();

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED)
        ->and(SellerOrder::query()->where('sales_order_id', $order->id)->first())
        ->status->toBe(SellerOrder::STATUS_CANCELLED)
        ->cancelled_by->toBe('system')
        ->and(orderVariant($variant)->reserved)->toBe(0);
});

test('a failure while another payment is open (switched to COD) leaves the order alone', function () {
    [$order, $payment] = prepaidOrder();
    $cod = Payment::factory()->create(['sales_order_id' => $order->id, 'customer_id' => $order->customer_id, 'method' => Payment::METHOD_COD, 'status' => Payment::STATUS_PENDING]);

    event(new PaymentFailed($payment, PaymentFailed::REASON_WINDOW_EXPIRED));

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_PENDING_PAYMENT)
        ->and($cod->fresh()->status)->toBe(Payment::STATUS_PENDING);
});

test('switching to COD on the pay page makes it a confirmed COD order', function () {
    [$order] = prepaidOrder();

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.switch_cod', ['orderNo' => $order->order_no]))
        ->assertRedirect($order->confirmationUrl());

    expect($order->fresh())
        ->payment_method->toBe(Payment::METHOD_COD)
        ->status->toBe(SalesOrder::STATUS_CONFIRMED)
        ->and($order->fresh()->grand_total->toDecimal())->toBe('1598.00');
});

test('a capture arriving after the order was cancelled is refunded in full', function () {
    [$order, $payment] = prepaidOrder();
    $fake = app(FakeGateway::class);
    $paymentId = $fake->simulate($payment->gateway_order_id)['razorpay_payment_id'];
    $payment->forceFill(['expires_at' => now()->subMinute()])->save();
    // The sweep cannot see the capture (gateway down), so the order is cancelled first.
    app(OrderCancellationService::class)->cancelOrder($order, 'system', 'Payment was not completed in time');
    $payment->forceFill(['status' => Payment::STATUS_ATTEMPTED])->save();

    app(PaymentService::class)->markCaptured($payment->fresh(), $fake->fetch($paymentId), 'webhook');

    expect(PaymentRefund::query()->where('payment_id', $payment->id)->first())
        ->not->toBeNull()
        ->amount->toDecimal()->toBe('1598.00');
});
