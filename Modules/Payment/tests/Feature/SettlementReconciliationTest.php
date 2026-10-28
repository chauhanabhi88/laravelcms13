<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Notification\Services\NotificationHub;
use Modules\Payment\Jobs\ReconcileRazorpaySettlements;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentReconItem;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\SettlementReconciliationService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
});

test('settled entries that do not match our records are reported once', function () {
    Payment::factory()->razorpay()->captured()->create(['gateway_payment_id' => 'pay_OK', 'amount' => '499.00']);
    Payment::factory()->razorpay()->captured()->create(['gateway_payment_id' => 'pay_AMOUNT', 'amount' => '499.00']);
    Payment::factory()->razorpay()->create(['gateway_payment_id' => 'pay_STATUS', 'amount' => '100.00', 'status' => Payment::STATUS_FAILED]);
    PaymentRefund::factory()->create(['gateway_refund_id' => 'rfnd_OK', 'amount' => '50.00', 'status' => PaymentRefund::STATUS_PROCESSED]);

    Http::fake(['api.razorpay.com/v1/settlements/recon/combined*' => Http::response(['entity' => 'collection', 'count' => 6, 'items' => [
        ['entity_id' => 'pay_OK', 'type' => 'payment', 'amount' => 49900, 'settlement_id' => 'setl_1'],
        ['entity_id' => 'pay_AMOUNT', 'type' => 'payment', 'amount' => 39900, 'settlement_id' => 'setl_1'],
        ['entity_id' => 'pay_STATUS', 'type' => 'payment', 'amount' => 10000, 'settlement_id' => 'setl_1'],
        ['entity_id' => 'pay_GHOST', 'type' => 'payment', 'amount' => 20000, 'settlement_id' => 'setl_1'],
        ['entity_id' => 'rfnd_OK', 'type' => 'refund', 'amount' => 5000, 'settlement_id' => 'setl_1'],
        ['entity_id' => 'adj_1', 'type' => 'adjustment', 'amount' => 100, 'settlement_id' => 'setl_1'],
    ]])]);
    $admin = paymentAdmin();

    $found = (new ReconcileRazorpaySettlements('2026-11-05'))->handle(app(SettlementReconciliationService::class), app(NotificationHub::class));

    expect($found)->toBe(3);
    expect(PaymentReconItem::query()->orderBy('entity_id')->get(['entity_id', 'issue'])->map->only(['entity_id', 'issue'])->all())->toBe([
        ['entity_id' => 'pay_AMOUNT', 'issue' => PaymentReconItem::ISSUE_AMOUNT_MISMATCH],
        ['entity_id' => 'pay_GHOST', 'issue' => PaymentReconItem::ISSUE_MISSING_LOCAL],
        ['entity_id' => 'pay_STATUS', 'issue' => PaymentReconItem::ISSUE_STATUS_MISMATCH],
    ]);
    expect(DB::table('notification_log')->where('event_key', 'admin.payment.recon_mismatch')->where('notifiable_id', $admin->id)->exists())->toBeTrue();

    // Re-running the same day adds nothing.
    expect((new ReconcileRazorpaySettlements('2026-11-05'))->handle(app(SettlementReconciliationService::class), app(NotificationHub::class)))->toBe(0)
        ->and(PaymentReconItem::query()->count())->toBe(3);
});

test('reconciliation is skipped without Razorpay keys', function () {
    config(['payment.razorpay.key_id' => null]);
    Http::fake();

    $found = (new ReconcileRazorpaySettlements)->handle(app(SettlementReconciliationService::class), app(NotificationHub::class));

    expect($found)->toBe(0);
    Http::assertNothingSent();
});
