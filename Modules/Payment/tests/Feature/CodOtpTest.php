<?php

use Illuminate\Support\Facades\Event;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Payment\Events\CodPaymentConfirmed;
use Modules\Payment\Events\PaymentFailed;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
    paymentSettings(['cod_enabled' => 1, 'cod_max_value' => '5000', 'cod_otp_threshold' => '2000', 'cod_otp_new_customers' => 2, 'cod_otp_window_minutes' => 15]);
    $this->sms = paymentFakeSms();
});

test('a COD order below the threshold is confirmed without an OTP', function () {
    $payment = app(PaymentService::class)->initiate(paymentOrder(['amount' => Money::of('499')]), Payment::METHOD_COD);

    expect($payment)
        ->status->toBe(Payment::STATUS_PENDING)
        ->cod_otp_required->toBe(2)
        ->expires_at->toBeNull()
        ->gateway_order_id->toBeNull();
    $this->sms->assertNothingSent();
});

test('a COD order that needs an OTP sends one and opens the confirmation window', function () {
    $order = paymentOrder(['amount' => Money::of('2500')]);

    $payment = app(PaymentService::class)->initiate($order, Payment::METHOD_COD);

    expect($payment->awaitingCodOtp())->toBeTrue()
        ->and((int) round(now()->diffInMinutes($payment->expires_at)))->toBe(15);
    $this->sms->assertSentTo($order->customer->phone);
});

test('the buyer confirms the COD order with the code', function () {
    Event::fake([CodPaymentConfirmed::class]);
    $order = paymentOrder(['amount' => Money::of('2500')]);
    $payment = app(PaymentService::class)->initiate($order, Payment::METHOD_COD);
    $code = $this->sms->lastOtpFor($order->customer->phone);

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.cod_otp.show', ['orderNo' => $order->orderNo]))
        ->assertSuccessful()
        ->assertSee($order->orderNo);

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.cod_otp.verify', ['orderNo' => $order->orderNo]), ['code' => str_split($code)])
        ->assertRedirect($order->confirmationUrl())
        ->assertSessionHas('success', trans('payment::front.messages.cod_confirmed'));

    expect($payment->fresh()->cod_otp_verified_at)->not->toBeNull()
        ->and($payment->fresh()->awaitingCodOtp())->toBeFalse();
    Event::assertDispatchedTimes(CodPaymentConfirmed::class, 1);

    // Done: the OTP page now forwards to the order.
    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.cod_otp.show', ['orderNo' => $order->orderNo]))
        ->assertRedirect($order->confirmationUrl());
});

test('a wrong code does not confirm the order', function () {
    Event::fake([CodPaymentConfirmed::class]);
    $order = paymentOrder(['amount' => Money::of('2500')]);
    $payment = app(PaymentService::class)->initiate($order, Payment::METHOD_COD);
    $wrong = $this->sms->lastOtpFor($order->customer->phone) === '000000' ? '111111' : '000000';

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.cod_otp.verify', ['orderNo' => $order->orderNo]), ['code' => $wrong])
        ->assertRedirect(frontRoute('payment.cod_otp.show', ['orderNo' => $order->orderNo]))
        ->assertSessionHasErrors('code');

    expect($payment->fresh()->awaitingCodOtp())->toBeTrue();
    Event::assertNotDispatched(CodPaymentConfirmed::class);
});

test('one payment\'s code never confirms another payment', function () {
    $customer = Customer::factory()->withPhone()->create();
    $first = app(PaymentService::class)->initiate(paymentOrder(['customer' => $customer, 'amount' => Money::of('2500')]), Payment::METHOD_COD);
    $firstCode = $this->sms->lastOtpFor($customer->phone);
    $this->travel(31)->seconds();
    $second = app(PaymentService::class)->initiate(paymentOrder(['customer' => $customer, 'amount' => Money::of('3000')]), Payment::METHOD_COD);

    expect(app(PaymentService::class)->verifyCodOtp($second, $firstCode))->toBeFalse()
        ->and(app(PaymentService::class)->verifyCodOtp($first, $firstCode))->toBeTrue();
});

test('resending is throttled by the OTP cooldown', function () {
    $order = paymentOrder(['amount' => Money::of('2500')]);
    app(PaymentService::class)->initiate($order, Payment::METHOD_COD);

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.cod_otp.resend', ['orderNo' => $order->orderNo]))
        ->assertSessionHas('error');

    $this->travel(31)->seconds();

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.cod_otp.resend', ['orderNo' => $order->orderNo]))
        ->assertSessionHas('success', trans('payment::front.messages.cod_otp_sent'));
    $this->sms->assertSentTo($order->customer->phone, 2);
});

test('an expired confirmation cannot be verified and is cancelled by the sweep', function () {
    Event::fake([PaymentFailed::class, CodPaymentConfirmed::class]);
    $order = paymentOrder(['amount' => Money::of('2500')]);
    $payment = app(PaymentService::class)->initiate($order, Payment::METHOD_COD);
    $code = $this->sms->lastOtpFor($order->customer->phone);
    $this->travel(16)->minutes();

    expect(fn () => app(PaymentService::class)->verifyCodOtp($payment->fresh(), $code))
        ->toThrow(PaymentException::class, trans('payment::front.errors.cod_otp_expired'));

    $this->actingAs($order->customer, 'customer')
        ->get(frontRoute('payment.cod_otp.show', ['orderNo' => $order->orderNo]))
        ->assertRedirect(frontRoute('payment.failed', ['orderNo' => $order->orderNo]));

    app(PaymentService::class)->expireStale();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CANCELLED);
    Event::assertDispatched(PaymentFailed::class, fn (PaymentFailed $event): bool => $event->reason === PaymentFailed::REASON_COD_OTP_EXPIRED);
    Event::assertNotDispatched(CodPaymentConfirmed::class);
});

test('a customer without a phone gets the code by email', function () {
    $customer = Customer::factory()->create(['email' => 'buyer@example.test']);
    $order = paymentOrder(['customer' => $customer, 'amount' => Money::of('2500')]);

    $payment = app(PaymentService::class)->initiate($order, Payment::METHOD_COD);

    expect(app(PaymentService::class)->codOtpMaskedDestination($payment))->toContain('@example.test');
    $this->sms->assertNothingSent();
});

test('only the order owner sees the COD OTP page', function () {
    $order = paymentOrder(['amount' => Money::of('2500')]);
    app(PaymentService::class)->initiate($order, Payment::METHOD_COD);

    // Guests are sent to sign in (before any actingAs, which sticks for the rest of the test).
    $this->get(frontRoute('payment.cod_otp.show', ['orderNo' => $order->orderNo]))
        ->assertRedirect();

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(frontRoute('payment.cod_otp.show', ['orderNo' => $order->orderNo]))
        ->assertNotFound();
});
