<?php

use Illuminate\Support\Facades\Event;
use Modules\Payment\Events\PaymentCaptured;
use Modules\Payment\Events\PaymentFailed;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
});

test('a prepaid payment past its window fails and fires PaymentFailed', function () {
    Event::fake([PaymentFailed::class]);
    $stale = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');
    $fresh = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');
    $stale->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('payment:expire-unpaid')->assertSuccessful();

    expect($stale->fresh())
        ->status->toBe(Payment::STATUS_FAILED)
        ->failure_reason->toBe(PaymentFailed::REASON_WINDOW_EXPIRED)
        ->and($fresh->fresh()->status)->toBe(Payment::STATUS_CREATED);

    Event::assertDispatched(PaymentFailed::class, fn (PaymentFailed $event): bool => $event->payment->id === $stale->id
        && $event->reason === PaymentFailed::REASON_WINDOW_EXPIRED);
    Event::assertDispatchedTimes(PaymentFailed::class, 1);
});

test('a late capture found on the final gateway check wins', function () {
    Event::fake([PaymentFailed::class, PaymentCaptured::class]);
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');
    fakeGateway()->simulate($payment->gateway_order_id, 'failed');
    fakeGateway()->simulate($payment->gateway_order_id);
    $payment->forceFill(['expires_at' => now()->subMinute()])->save();

    $result = app(PaymentService::class)->expireStale();

    expect($result)->toMatchArray(['captured' => 1, 'failed' => 0])
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
    Event::assertDispatched(PaymentCaptured::class);
    Event::assertNotDispatched(PaymentFailed::class);
});

test('a gateway outage skips the payment instead of failing a possibly paid order', function () {
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');
    $payment->forceFill(['method' => Payment::METHOD_RAZORPAY, 'expires_at' => now()->subMinute()])->save();
    config(['payment.razorpay.key_id' => null]);

    $result = app(PaymentService::class)->expireStale();

    expect($result['skipped'])->toBe(1)
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_CREATED);
});

test('a COD payment whose OTP was never verified is cancelled', function () {
    Event::fake([PaymentFailed::class]);
    $payment = Payment::factory()->cod(otpRequired: true)->create(['expires_at' => now()->subMinute()]);
    $verified = Payment::factory()->cod(otpRequired: true)->create(['expires_at' => now()->subMinute(), 'cod_otp_verified_at' => now()->subMinutes(5)]);
    $plainCod = Payment::factory()->cod()->create();

    $result = app(PaymentService::class)->expireStale();

    expect($result['cod_cancelled'])->toBe(1)
        ->and($payment->fresh())->status->toBe(Payment::STATUS_CANCELLED)->failure_reason->toBe(PaymentFailed::REASON_COD_OTP_EXPIRED)
        ->and($verified->fresh()->status)->toBe(Payment::STATUS_PENDING)
        ->and($plainCod->fresh()->status)->toBe(Payment::STATUS_PENDING);
    Event::assertDispatched(PaymentFailed::class, fn (PaymentFailed $event): bool => $event->reason === PaymentFailed::REASON_COD_OTP_EXPIRED);
});
