<?php

use Modules\Customer\Models\Customer;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentReconItem;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Models\WebhookEvent;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
});

test('the payment screens need their permissions', function (string $route) {
    $this->actingAs(paymentAdmin([]))
        ->get(route($route, updateUrlParams()))
        ->assertForbidden();
})->with(['admin.payment.index', 'admin.payment_refund.index', 'admin.webhook_event.index', 'admin.payment_recon.index']);

test('finance lists transactions and opens one with its attempts and refunds', function () {
    $customer = Customer::factory()->create(['first_name' => 'Meera', 'last_name' => 'Iyer']);
    $payment = Payment::factory()->captured()->forCustomer($customer)->create(['order_no' => 'ORD-2611-004242', 'amount' => '1299.00']);
    PaymentRefund::factory()->create(['payment_id' => $payment->id, 'amount' => '299.00', 'gateway_refund_id' => 'rfnd_SHOWN']);
    $admin = paymentAdmin();

    $this->actingAs($admin)
        ->get(route('admin.payment.index', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee('ORD-2611-004242')
        ->assertSee('Meera Iyer');

    $this->actingAs($admin)
        ->post(route('admin.payment.filters', updateUrlParams()), ['status' => Payment::STATUS_FAILED])
        ->assertSuccessful()
        ->assertJsonPath('type', 'success')
        ->assertDontSee('ORD-2611-004242');

    $this->actingAs($admin)
        ->get(route('admin.payment.show', updateUrlParams([$payment->id])))
        ->assertSuccessful()
        ->assertSee('ORD-2611-004242')
        ->assertSee('rfnd_SHOWN')
        ->assertSee('₹1,000.00');
});

test('finance marks a COD refund paid with the UTR', function () {
    $payment = Payment::factory()->cod()->collected()->create(['amount' => '500.00']);
    $refund = PaymentRefund::factory()->pending()->create([
        'payment_id' => $payment->id,
        'amount' => '500.00',
        'mode' => PaymentRefund::MODE_BANK,
        'beneficiary' => ['account_number' => '123456789012', 'ifsc' => 'HDFC0001234', 'name' => 'Meera'],
    ]);
    $admin = paymentAdmin();

    $this->actingAs($admin)
        ->get(route('admin.payment_refund.index', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee('XXXXXXXX9012 / HDFC0001234')
        ->assertDontSee('123456789012');

    $this->actingAs($admin)
        ->post(route('admin.payment_refund.mark_processed', updateUrlParams([$refund->id])), ['utr' => ''])
        ->assertSessionHas('error', trans('payment::payment_refund.messages.utr_required'));

    $this->actingAs($admin)
        ->post(route('admin.payment_refund.mark_processed', updateUrlParams([$refund->id])), ['utr' => 'N123456789'])
        ->assertSessionHas('success');

    expect($refund->fresh())->status->toBe(PaymentRefund::STATUS_PROCESSED)->utr->toBe('N123456789')
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_REFUNDED);
});

test('finance retries a failed refund', function () {
    $refund = PaymentRefund::factory()->failed()->create(['amount' => '100.00']);

    $this->actingAs(paymentAdmin())
        ->post(route('admin.payment_refund.retry', updateUrlParams([$refund->id])))
        ->assertSessionHas('success');

    expect($refund->fresh()->status)->toBe(PaymentRefund::STATUS_PROCESSING);
});

test('a webhook can be inspected and replayed', function () {
    $payment = Payment::factory()->create(['amount' => '499.00']);
    $event = WebhookEvent::factory()->failed('Database was down')->create([
        'event_type' => 'payment.captured',
        'payload' => ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => razorpayPaymentEntity($payment->gateway_order_id, 49900)]]],
    ]);
    $admin = paymentAdmin();

    $this->actingAs($admin)
        ->get(route('admin.webhook_event.index', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee('payment.captured');

    $this->actingAs($admin)
        ->get(route('admin.webhook_event.show', updateUrlParams([$event->id])))
        ->assertSuccessful()
        ->assertSee('Database was down')
        ->assertSee($payment->gateway_order_id);

    $this->actingAs($admin)
        ->post(route('admin.webhook_event.replay', updateUrlParams([$event->id])))
        ->assertSessionHas('success');

    expect($event->fresh())->status->toBe(WebhookEvent::STATUS_PROCESSED)->attempts->toBe(2)->error->toBeNull()
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
});

test('a webhook with an invalid signature cannot be replayed', function () {
    $event = WebhookEvent::factory()->create(['signature_valid' => 2, 'status' => WebhookEvent::STATUS_IGNORED, 'payload' => null]);

    $this->actingAs(paymentAdmin())
        ->post(route('admin.webhook_event.replay', updateUrlParams([$event->id])))
        ->assertSessionHas('error', trans('payment::webhook_event.messages.not_replayable'));
});

test('finance resolves a reconciliation mismatch', function () {
    $item = PaymentReconItem::factory()->create(['entity_id' => 'pay_MISSING']);
    $admin = paymentAdmin();

    $this->actingAs($admin)
        ->get(route('admin.payment_recon.index', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee('pay_MISSING');

    $this->actingAs($admin)
        ->post(route('admin.payment_recon.resolve', updateUrlParams([$item->id])), ['note' => 'Test payment on another site'])
        ->assertSessionHas('success');

    expect($item->fresh())
        ->resolved_by->toBe($admin->id)
        ->note->toBe('Test payment on another site')
        ->and($item->fresh()->isResolved())->toBeTrue();
});
