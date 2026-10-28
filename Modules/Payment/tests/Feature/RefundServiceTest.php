<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Payment\Events\RefundFailed;
use Modules\Payment\Events\RefundProcessed;
use Modules\Payment\Exceptions\RefundException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\RefundService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
});

function refundService(): RefundService
{
    return app(RefundService::class);
}

function capturedPayment(string $amount = '1000.00'): Payment
{
    return Payment::factory()->captured()->create(['amount' => $amount]);
}

test('partial refunds go to source and the payment follows once processed', function () {
    Event::fake([RefundProcessed::class]);
    $payment = capturedPayment('1000.00');

    $first = refundService()->refund($payment, Money::of('300'), PaymentRefund::MODE_SOURCE, null, 'Item cancelled', RefundService::newIdempotencyKey());

    expect($first)
        ->status->toBe(PaymentRefund::STATUS_PROCESSING)
        ->gateway_refund_id->toStartWith('rfnd_fake_')
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);

    refundService()->markProcessed($first);
    expect($payment->fresh())
        ->status->toBe(Payment::STATUS_PARTIALLY_REFUNDED)
        ->amount_refunded->toDecimal()->toBe('300.00');

    fakeGateway()->refundOutcome = PaymentRefund::STATUS_PROCESSED;
    $second = refundService()->refund($payment, Money::of('700'), PaymentRefund::MODE_SOURCE, null, 'Rest of the order', RefundService::newIdempotencyKey());

    expect($second->status)->toBe(PaymentRefund::STATUS_PROCESSED)
        ->and($payment->fresh())
        ->status->toBe(Payment::STATUS_REFUNDED)
        ->amount_refunded->toDecimal()->toBe('1000.00');
    Event::assertDispatchedTimes(RefundProcessed::class, 2);
});

test('refunds can never exceed the captured amount', function () {
    $payment = capturedPayment('1000.00');
    refundService()->refund($payment, Money::of('600'), PaymentRefund::MODE_SOURCE, null, 'Return', RefundService::newIdempotencyKey());

    // Pending / processing refunds already hold their share.
    expect(fn () => refundService()->refund($payment, Money::of('400.01'), PaymentRefund::MODE_SOURCE, null, 'Return', RefundService::newIdempotencyKey()))
        ->toThrow(RefundException::class, trans('payment::payment_refund.messages.over_refund', ['available' => '₹400.00']));

    expect(refundService()->refundableAmount($payment->fresh())->toDecimal())->toBe('400.00')
        ->and(PaymentRefund::query()->where('payment_id', $payment->id)->count())->toBe(1);
});

test('the same idempotency key returns the existing refund instead of refunding twice', function () {
    $payment = capturedPayment();
    $key = RefundService::newIdempotencyKey();

    $first = refundService()->refund($payment, Money::of('250'), PaymentRefund::MODE_SOURCE, null, 'Cancel', $key);
    $again = refundService()->refund($payment, Money::of('250'), PaymentRefund::MODE_SOURCE, null, 'Cancel', $key);

    expect($again->id)->toBe($first->id)
        ->and(PaymentRefund::query()->where('payment_id', $payment->id)->count())->toBe(1);

    expect(fn () => refundService()->refund(capturedPayment(), Money::of('10'), PaymentRefund::MODE_SOURCE, null, 'Other', $key))
        ->toThrow(RefundException::class, trans('payment::payment_refund.messages.key_reused'));
});

test('unpaid payments and invalid requests cannot be refunded', function (Closure $call, string $message) {
    expect($call)->toThrow(RefundException::class, trans($message));
})->with([
    // Closure-typed dataset values reach the test unresolved, so each runs inside expect().
    'not captured' => [fn () => refundService()->refund(Payment::factory()->create(), Money::of('10'), 'source', null, 'x', RefundService::newIdempotencyKey()), 'payment::payment_refund.messages.not_refundable'],
    'zero amount' => [fn () => refundService()->refund(capturedPayment(), Money::zero(), 'source', null, 'x', RefundService::newIdempotencyKey()), 'payment::payment_refund.messages.bad_amount'],
    'prepaid to bank' => [fn () => refundService()->refund(capturedPayment(), Money::of('10'), 'bank', ['account_number' => '1234567890', 'ifsc' => 'HDFC0001234'], 'x', RefundService::newIdempotencyKey()), 'payment::payment_refund.messages.prepaid_to_source'],
    'bad key' => [fn () => refundService()->refund(capturedPayment(), Money::of('10'), 'source', null, 'x', 'not-a-uuid'), 'payment::payment_refund.messages.bad_key'],
    'COD without beneficiary' => [fn () => refundService()->refund(Payment::factory()->cod()->collected()->create(), Money::of('10'), 'upi', ['vpa' => 'not a vpa'], 'x', RefundService::newIdempotencyKey()), 'payment::payment_refund.messages.cod_beneficiary'],
]);

test('a COD refund waits for a payout and Finance marks it paid with the UTR', function () {
    $payment = Payment::factory()->cod()->collected()->create(['amount' => '799.00']);

    $refund = refundService()->refund($payment, Money::of('799'), PaymentRefund::MODE_UPI, ['vpa' => 'buyer@okbank'], 'Return', RefundService::newIdempotencyKey());

    expect($refund->status)->toBe(PaymentRefund::STATUS_PENDING)
        ->and($refund->beneficiary)->toBe(['vpa' => 'buyer@okbank'])
        ->and($refund->toArray())->not->toHaveKey('beneficiary')
        ->and(DB::table('payment_refund')->where('id', $refund->id)->value('beneficiary'))->not->toContain('buyer@okbank');

    $admin = paymentAdmin();
    refundService()->markProcessed($refund, 'UTR12345', $admin);

    expect($refund->fresh())->status->toBe(PaymentRefund::STATUS_PROCESSED)->utr->toBe('UTR12345')
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_REFUNDED)
        ->and($refund->statusHistories()->latest('id')->value('actor_type'))->toBe('admin');
});

test('a failed refund releases its share and can be retried', function () {
    Event::fake([RefundFailed::class]);
    $payment = capturedPayment('500.00');
    fakeGateway()->refundOutcome = PaymentRefund::STATUS_FAILED;

    $refund = refundService()->refund($payment, Money::of('500'), PaymentRefund::MODE_SOURCE, null, 'Cancel', RefundService::newIdempotencyKey());

    expect($refund->status)->toBe(PaymentRefund::STATUS_FAILED)
        ->and(refundService()->refundableAmount($payment)->toDecimal())->toBe('500.00');
    Event::assertDispatched(RefundFailed::class);

    fakeGateway()->refundOutcome = PaymentRefund::STATUS_PROCESSING;
    $retried = refundService()->retry($refund);

    expect($retried->status)->toBe(PaymentRefund::STATUS_PROCESSING)
        ->and($retried->failure_reason)->toBeNull();
});

test('a processed refund notifies the customer', function () {
    Notification::fake();
    $customer = Customer::factory()->withPhone()->create();
    $payment = Payment::factory()->captured()->forCustomer($customer)->create(['amount' => '400.00', 'order_no' => 'ORD-2611-000777']);
    fakeGateway()->refundOutcome = PaymentRefund::STATUS_PROCESSED;

    refundService()->refund($payment, Money::of('400'), PaymentRefund::MODE_SOURCE, null, 'Cancel', RefundService::newIdempotencyKey());

    expect(DB::table('notification_log')->where('event_key', 'refund.processed')->where('notifiable_id', $customer->id)->exists())->toBeTrue();
});
