<?php

use Illuminate\Support\Facades\Event;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Payment\DTO\GatewayPayment;
use Modules\Payment\Events\PaymentCaptured;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentAttempt;
use Modules\Payment\Services\PaymentService;
use Modules\Payment\Support\RazorpaySignature;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
});

test('initiating a prepaid payment creates a gateway order and a retry window', function () {
    $order = paymentOrder(['amount' => Money::of('749.50')]);

    $payment = app(PaymentService::class)->initiate($order, PaymentService::METHOD_PREPAID);

    expect($payment->method)->toBe(Payment::METHOD_FAKE)
        ->and($payment->status)->toBe(Payment::STATUS_CREATED)
        ->and($payment->amount->toDecimal())->toBe('749.50')
        ->and($payment->order_no)->toBe($order->orderNo)
        ->and($payment->gateway_order_id)->toStartWith('order_fake_')
        ->and((int) round(now()->diffInMinutes($payment->expires_at)))->toBe(30);

    // Idempotent per order and method.
    expect(app(PaymentService::class)->initiate($order, 'prepaid')->id)->toBe($payment->id);
});

test('the pay page is only for the customer who owns the order', function () {
    $order = paymentOrder();
    app(PaymentService::class)->initiate($order, 'prepaid');

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertSuccessful()
        ->assertSee($order->orderNo)
        ->assertSee('fake-gateway-panel', false);

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertNotFound();

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => 'ORD-NOPE']))
        ->assertNotFound();
});

test('the Razorpay pay page loads Checkout.js with the order options', function () {
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'fake');
    $payment->forceFill(['method' => Payment::METHOD_RAZORPAY])->save();

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertSuccessful()
        ->assertSee('https://checkout.razorpay.com/v1/checkout.js', false)
        ->assertSee($payment->gateway_order_id)
        ->assertSee('rzp_test_key')
        ->assertDontSee('rzp_test_secret');
});

test('a signed callback captures the payment and fires PaymentCaptured once', function () {
    Event::fake([PaymentCaptured::class]);
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');
    $callback = fakeGateway()->simulate($payment->gateway_order_id);

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.callback', ['orderNo' => $order->orderNo]), $callback)
        ->assertRedirect($order->confirmationUrl())
        ->assertSessionHas('success');

    $payment->refresh();
    expect($payment->status)->toBe(Payment::STATUS_CAPTURED)
        ->and($payment->gateway_payment_id)->toBe($callback['razorpay_payment_id'])
        ->and($payment->instrument)->toBe('upi')
        ->and($payment->instrument_meta)->toBe(['vpa' => 'buyer@upi'])
        ->and($payment->captured_at)->not->toBeNull()
        ->and($payment->statusHistories()->pluck('to_status')->all())->toBe([Payment::STATUS_CAPTURED]);

    // The same callback again (double submit) is a no-op.
    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.callback', ['orderNo' => $order->orderNo]), $callback)
        ->assertRedirect();

    Event::assertDispatchedTimes(PaymentCaptured::class, 1);
    expect($payment->attempts()->where('status', PaymentAttempt::STATUS_CAPTURED)->count())->toBe(1);
});

test('a forged callback signature is rejected and logged', function () {
    Event::fake([PaymentCaptured::class]);
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');
    $callback = fakeGateway()->simulate($payment->gateway_order_id);
    $callback['razorpay_signature'] = RazorpaySignature::forCallback($payment->gateway_order_id, $callback['razorpay_payment_id'], 'wrong-secret');

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.callback', ['orderNo' => $order->orderNo]), $callback)
        ->assertRedirect(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertSessionHas('error', trans('payment::front.errors.invalid_signature'));

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CREATED)
        ->and($payment->attempts()->value('status'))->toBe(PaymentAttempt::STATUS_INVALID_SIGNATURE);
    Event::assertNotDispatched(PaymentCaptured::class);
});

test('a callback for another order of the gateway is rejected', function () {
    $order = paymentOrder();
    $other = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');
    $otherPayment = app(PaymentService::class)->initiate($other, 'prepaid');

    expect(fn () => app(PaymentService::class)->confirmFromCallback($payment, fakeGateway()->simulate($otherPayment->gateway_order_id)))
        ->toThrow(PaymentException::class, trans('payment::front.errors.invalid_signature'));

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CREATED);
});

test('a capture for the wrong amount is rejected', function () {
    Event::fake([PaymentCaptured::class]);
    $order = paymentOrder(['amount' => Money::of('499')]);
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');
    $callback = fakeGateway()->simulate($payment->gateway_order_id, GatewayPayment::STATUS_CAPTURED, 100);

    expect(fn () => app(PaymentService::class)->confirmFromCallback($payment, $callback))
        ->toThrow(PaymentException::class, trans('payment::front.errors.amount_mismatch'));

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CREATED)
        ->and($payment->attempts()->value('error_code'))->toBe('amount_mismatch');
    Event::assertNotDispatched(PaymentCaptured::class);
});

test('a failed or dismissed attempt is logged and the buyer can retry', function () {
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.callback', ['orderNo' => $order->orderNo]), [
            'error' => ['code' => 'BAD_REQUEST_ERROR', 'description' => 'Your bank declined the payment', 'metadata' => ['payment_id' => 'pay_declined']],
        ])
        ->assertRedirect(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertSessionHas('error');

    $payment->refresh();
    expect($payment->status)->toBe(Payment::STATUS_ATTEMPTED)
        ->and($payment->failure_reason)->toBe('Your bank declined the payment')
        ->and($payment->attempts()->first())
        ->status->toBe(PaymentAttempt::STATUS_FAILED)
        ->gateway_payment_id->toBe('pay_declined');

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertSuccessful()
        ->assertSee('Your bank declined the payment');

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.retry', ['orderNo' => $order->orderNo]))
        ->assertRedirect(frontRoute('payment.pay', ['orderNo' => $order->orderNo]));

    // The retry succeeds on the same gateway order.
    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.simulate', ['orderNo' => $order->orderNo]), ['outcome' => 'captured'])
        ->assertRedirect($order->confirmationUrl());

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
});

test('after the window closes the pay page sends the buyer to the failure page', function () {
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');
    $payment->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertRedirect(frontRoute('payment.failed', ['orderNo' => $order->orderNo]));

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.failed', ['orderNo' => $order->orderNo]))
        ->assertSuccessful()
        ->assertSee('payment-failed-body', false);

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.retry', ['orderNo' => $order->orderNo]))
        ->assertRedirect(frontRoute('payment.failed', ['orderNo' => $order->orderNo]));
});

test('a paid order goes straight to its confirmation page', function () {
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');
    app(PaymentService::class)->confirmFromCallback($payment, fakeGateway()->simulate($payment->gateway_order_id));

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertRedirect($order->confirmationUrl());

    expect(fn () => app(PaymentService::class)->initiate($order, Payment::METHOD_COD))
        ->toThrow(PaymentException::class, trans('payment::front.errors.already_paid'));
});

test('the buyer can switch to COD from the pay page when eligible', function () {
    paymentSettings(['cod_otp_threshold' => '100000', 'cod_otp_new_customers' => 2]);
    $order = paymentOrder();
    $prepaid = app(PaymentService::class)->initiate($order, 'prepaid');

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.switch_cod', ['orderNo' => $order->orderNo]))
        ->assertRedirect($order->confirmationUrl())
        ->assertSessionHas('success', trans('payment::front.messages.cod_confirmed'));

    expect($prepaid->fresh()->status)->toBe(Payment::STATUS_CANCELLED)
        ->and(app(PaymentService::class)->currentPayment($order->id))
        ->method->toBe(Payment::METHOD_COD)
        ->status->toBe(Payment::STATUS_PENDING);
});

test('switching to COD is refused when the order is not eligible', function () {
    $order = paymentOrder(['itemsAllowCod' => false]);
    $prepaid = app(PaymentService::class)->initiate($order, 'prepaid');

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.switch_cod', ['orderNo' => $order->orderNo]))
        ->assertRedirect(frontRoute('payment.pay', ['orderNo' => $order->orderNo]))
        ->assertSessionHas('error', trans('payment::front.cod.reasons.items'));

    expect($prepaid->fresh()->status)->toBe(Payment::STATUS_CREATED);
});

test('a capture that lands while switching to COD wins over the switch', function () {
    $order = paymentOrder();
    $prepaid = app(PaymentService::class)->initiate($order, 'prepaid');
    fakeGateway()->simulate($prepaid->gateway_order_id);

    expect(fn () => app(PaymentService::class)->initiate($order, Payment::METHOD_COD))
        ->toThrow(PaymentException::class, trans('payment::front.errors.already_paid'));

    expect($prepaid->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
});
