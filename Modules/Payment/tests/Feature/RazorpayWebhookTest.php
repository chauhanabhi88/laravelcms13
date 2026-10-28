<?php

use Illuminate\Support\Facades\Event;
use Modules\Payment\Events\PaymentCaptured;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentAttempt;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Models\WebhookEvent;
use Modules\Payment\Services\PaymentService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
});

function postRazorpayWebhook(array $payload, ?string $eventId = null, ?string $secret = null)
{
    [$body, $headers] = razorpayWebhook($payload, $eventId, $secret);

    return test()->call('POST', route('webhook.razorpay'), [], [], [], array_combine(
        array_map(fn (string $name): string => 'HTTP_'.strtoupper(str_replace('-', '_', $name)), array_keys($headers)),
        array_values($headers),
    ), $body);
}

/**
 * @return array<string, mixed>
 */
function capturedWebhook(Payment $payment, string $event = 'payment.captured', array $entity = []): array
{
    return [
        'entity' => 'event',
        'event' => $event,
        'payload' => ['payment' => ['entity' => razorpayPaymentEntity($payment->gateway_order_id, $payment->amountInPaise(), $entity)]],
    ];
}

test('a webhook with a bad signature is rejected and logged without its payload', function () {
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');

    postRazorpayWebhook(capturedWebhook($payment), 'evt_forged', 'not-the-secret')->assertStatus(400);

    $row = WebhookEvent::query()->latest('id')->first();
    expect($row->signature_valid)->toBe(2)
        ->and($row->status)->toBe(WebhookEvent::STATUS_IGNORED)
        ->and($row->payload)->toBeNull()
        ->and($row->event_id)->toStartWith('invalid:')
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_CREATED);

    // The forged request did not reserve the real event id.
    postRazorpayWebhook(capturedWebhook($payment), 'evt_forged')->assertSuccessful();
    expect($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
});

test('a captured webhook before the callback captures the payment; the callback then is a no-op', function () {
    Event::fake([PaymentCaptured::class]);
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');
    $callback = fakeGateway()->simulate($payment->gateway_order_id);

    postRazorpayWebhook(capturedWebhook($payment, 'payment.captured', ['id' => $callback['razorpay_payment_id']]), 'evt_1')
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $payment->refresh();
    expect($payment->status)->toBe(Payment::STATUS_CAPTURED)
        ->and($payment->instrument)->toBe('card')
        // MySQL JSON does not keep key order.
        ->and($payment->instrument_meta)->toEqual(['network' => 'Visa', 'last4' => '1111', 'card_type' => 'credit'])
        ->and(WebhookEvent::query()->where('event_id', 'evt_1')->value('status'))->toBe(WebhookEvent::STATUS_PROCESSED);

    $this->actingAs($order->customer, 'customer')
        ->post(frontRoute('payment.callback', ['orderNo' => $order->orderNo]), $callback)
        ->assertRedirect($order->confirmationUrl());

    Event::assertDispatchedTimes(PaymentCaptured::class, 1);
});

test('order.paid works like payment.captured', function () {
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');

    postRazorpayWebhook(capturedWebhook($payment, 'order.paid'))->assertSuccessful();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
});

test('a redelivered event is acknowledged without being processed again', function () {
    Event::fake([PaymentCaptured::class]);
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');
    $payload = capturedWebhook($payment);

    postRazorpayWebhook($payload, 'evt_dup')->assertSuccessful();
    postRazorpayWebhook($payload, 'evt_dup')->assertSuccessful()->assertJsonPath('message', 'Duplicate event.');

    expect(WebhookEvent::query()->where('event_id', 'evt_dup')->count())->toBe(1)
        ->and(WebhookEvent::query()->where('event_id', 'evt_dup')->value('attempts'))->toBe(1);
    Event::assertDispatchedTimes(PaymentCaptured::class, 1);
});

test('payment.failed logs an attempt but never undoes a capture', function () {
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');

    postRazorpayWebhook(capturedWebhook($payment, 'payment.failed', ['status' => 'failed', 'error_code' => 'BAD_REQUEST_ERROR', 'error_description' => 'Card declined']))
        ->assertSuccessful();

    expect($payment->fresh())->status->toBe(Payment::STATUS_ATTEMPTED)->failure_reason->toBe('Card declined');

    postRazorpayWebhook(capturedWebhook($payment))->assertSuccessful();
    postRazorpayWebhook(capturedWebhook($payment, 'payment.failed', ['status' => 'failed', 'error_description' => 'Late failure']))->assertSuccessful();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED)
        ->and($payment->attempts()->where('source', PaymentAttempt::SOURCE_WEBHOOK)->count())->toBe(3);
});

test('a capture after the window failed is still honoured', function () {
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');
    app(PaymentService::class)->markFailed($payment, 'payment_window_expired');

    postRazorpayWebhook(capturedWebhook($payment))->assertSuccessful();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
});

test('events for unknown orders and unhandled types are ignored', function () {
    postRazorpayWebhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => razorpayPaymentEntity('order_unknown', 100)]]], 'evt_unknown')->assertSuccessful();
    postRazorpayWebhook(['event' => 'settlement.processed', 'payload' => []], 'evt_other')->assertSuccessful();

    expect(WebhookEvent::query()->whereIn('event_id', ['evt_unknown', 'evt_other'])->pluck('status')->unique()->all())->toBe([WebhookEvent::STATUS_IGNORED]);
});

test('refund webhooks finalise the refund', function () {
    $refund = PaymentRefund::factory()->create(['amount' => '100.00', 'gateway_refund_id' => 'rfnd_123']);

    postRazorpayWebhook([
        'event' => 'refund.processed',
        'payload' => ['refund' => ['entity' => ['id' => 'rfnd_123', 'amount' => 10000, 'status' => 'processed']]],
    ])->assertSuccessful();

    expect($refund->fresh()->status)->toBe(PaymentRefund::STATUS_PROCESSED)
        ->and($refund->payment->fresh())
        ->status->toBe(Payment::STATUS_PARTIALLY_REFUNDED)
        ->amount_refunded->toDecimal()->toBe('100.00');
});

test('webhooks need no CSRF token or session', function () {
    $payment = app(PaymentService::class)->initiate(paymentOrder(), 'prepaid');

    // call() without the CSRF-exempting test middleware bypass still succeeds.
    postRazorpayWebhook(capturedWebhook($payment))->assertSuccessful();
    expect($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
});
