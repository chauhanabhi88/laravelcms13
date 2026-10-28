<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Core\Support\Money;
use Modules\Payment\Drivers\RazorpayGateway;
use Modules\Payment\Exceptions\GatewayException;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\PaymentService;
use Modules\Payment\Support\RazorpaySignature;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
    config(['payment.default_gateway' => 'razorpay']);
});

test('initiating a Razorpay payment creates an order in paise with the order number as receipt', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_RZP1', 'amount' => 74950, 'currency' => 'INR', 'status' => 'created'])]);
    $order = paymentOrder(['amount' => Money::of('749.50')]);

    $payment = app(PaymentService::class)->initiate($order, 'prepaid');

    expect($payment->method)->toBe(Payment::METHOD_RAZORPAY)
        ->and($payment->gateway_order_id)->toBe('order_RZP1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request['amount'] === 74950
        && $request['currency'] === 'INR'
        && $request['receipt'] === $order->orderNo
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_key:rzp_test_secret')));
});

test('a gateway outage at placement leaves the payment open and the pay page retries the order', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::sequence()
        ->push(['error' => ['description' => 'Server error']], 500)
        ->push(['error' => ['description' => 'Server error']], 500)
        ->push(['error' => ['description' => 'Server error']], 500)
        ->push(['error' => ['description' => 'Server error']], 500)
        ->push(['id' => 'order_LATER', 'amount' => 49900, 'currency' => 'INR'])]);
    config(['core.integrations.retry_delay_ms' => 0]);
    $order = paymentOrder();

    expect(fn () => app(PaymentService::class)->initiate($order, 'prepaid'))->toThrow(PaymentException::class);
    $payment = app(PaymentService::class)->currentPayment($order->id);
    expect($payment)->status->toBe(Payment::STATUS_CREATED)->gateway_order_id->toBeNull();

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertSuccessful()
        ->assertSee('order_LATER');
});

test('the callback is verified against the key secret and the payment fetched from Razorpay', function () {
    Http::fake([
        'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_RZP2', 'amount' => 49900, 'currency' => 'INR']),
        'api.razorpay.com/v1/payments/pay_RZP2' => Http::response(razorpayPaymentEntity('order_RZP2', 49900, ['id' => 'pay_RZP2'])),
    ]);
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');

    $payment = app(PaymentService::class)->confirmFromCallback($payment, [
        'razorpay_order_id' => 'order_RZP2',
        'razorpay_payment_id' => 'pay_RZP2',
        'razorpay_signature' => RazorpaySignature::forCallback('order_RZP2', 'pay_RZP2', 'rzp_test_secret'),
    ]);

    expect($payment)
        ->status->toBe(Payment::STATUS_CAPTURED)
        ->instrument->toBe('card')
        ->instrument_meta->toEqual(['network' => 'Visa', 'last4' => '1111', 'card_type' => 'credit']);
});

test('an authorised but not yet captured payment stays open for the webhook', function () {
    Http::fake([
        'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_RZP3', 'amount' => 49900, 'currency' => 'INR']),
        'api.razorpay.com/v1/payments/pay_RZP3' => Http::response(razorpayPaymentEntity('order_RZP3', 49900, ['id' => 'pay_RZP3', 'status' => 'authorized'])),
    ]);
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');

    $payment = app(PaymentService::class)->confirmFromCallback($payment, [
        'razorpay_order_id' => 'order_RZP3',
        'razorpay_payment_id' => 'pay_RZP3',
        'razorpay_signature' => RazorpaySignature::forCallback('order_RZP3', 'pay_RZP3', 'rzp_test_secret'),
    ]);

    expect($payment->status)->toBe(Payment::STATUS_ATTEMPTED)
        ->and($payment->attempts()->value('status'))->toBe('authorized');
});

test('the last check before expiry reads the order payments', function () {
    Http::fake([
        'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_RZP4', 'amount' => 49900, 'currency' => 'INR']),
        'api.razorpay.com/v1/orders/order_RZP4/payments' => Http::response(['entity' => 'collection', 'count' => 2, 'items' => [
            razorpayPaymentEntity('order_RZP4', 49900, ['status' => 'failed']),
            razorpayPaymentEntity('order_RZP4', 49900, ['id' => 'pay_LATE']),
        ]]),
    ]);
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');
    $payment->forceFill(['expires_at' => now()->subMinute()])->save();

    app(PaymentService::class)->expireStale();

    expect($payment->fresh())->status->toBe(Payment::STATUS_CAPTURED)->gateway_payment_id->toBe('pay_LATE');
});

test('refunds are sent in paise with the idempotency key as receipt', function () {
    Http::fake(['api.razorpay.com/v1/payments/pay_REF/refund' => Http::response(['id' => 'rfnd_1', 'amount' => 25000, 'status' => 'pending'])]);
    $payment = Payment::factory()->razorpay()->captured()->create(['amount' => '500.00', 'gateway_payment_id' => 'pay_REF']);
    $refund = PaymentRefund::factory()->pending()->create(['payment_id' => $payment->id, 'amount' => '250.00']);

    $result = app(RazorpayGateway::class)->refund($refund);

    expect($result)->status->toBe(PaymentRefund::STATUS_PROCESSING)->gatewayRefundId->toBe('rfnd_1');
    Http::assertSent(fn (Request $request): bool => $request['amount'] === 25000 && $request['receipt'] === $refund->idempotency_key && $request['speed'] === 'normal');
});

test('a rejected refund is a failure, an unreachable gateway is an exception', function () {
    Http::fake(['api.razorpay.com/v1/payments/pay_REF2/refund' => Http::response(['error' => ['description' => 'The amount is more than the payment.']], 400)]);
    $payment = Payment::factory()->razorpay()->captured()->create(['gateway_payment_id' => 'pay_REF2']);
    $refund = PaymentRefund::factory()->pending()->create(['payment_id' => $payment->id]);

    expect(app(RazorpayGateway::class)->refund($refund))
        ->status->toBe(PaymentRefund::STATUS_FAILED)
        ->error->toBe('The amount is more than the payment.');

    config(['payment.razorpay.key_secret' => null]);
    expect(fn () => app(RazorpayGateway::class)->fetch('pay_x'))->toThrow(GatewayException::class);
});

test('the settlement report is read page by page', function () {
    Http::fake(['api.razorpay.com/v1/settlements/recon/combined*' => Http::response(['entity' => 'collection', 'count' => 1, 'items' => [
        ['entity_id' => 'pay_S1', 'type' => 'payment', 'amount' => 49900, 'settlement_id' => 'setl_1'],
    ]])]);

    $items = app(RazorpayGateway::class)->settledOn(Carbon::parse('2026-11-05'));

    expect($items)->toBe([['type' => 'payment', 'entity_id' => 'pay_S1', 'amount_paise' => 49900, 'settlement_id' => 'setl_1']]);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'year=2026') && str_contains($request->url(), 'month=11') && str_contains($request->url(), 'day=5'));
});
