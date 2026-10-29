<?php

use Modules\Finance\Drivers\FakePayoutGateway;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Services\PayoutWebhookHandler;
use Modules\Payment\Models\PaymentRefund;
use Modules\Returns\Models\ReturnRequest;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    [, $this->sellerOrder, , $this->customer] = financeDeliveredOrder();
});

test('with the manual gateway a COD refund waits for Finance, as before', function () {
    financeSettings(['payout_gateway' => 'manual']);
    $return = codReturnAfterQc($this->sellerOrder, $this->customer);
    $refund = PaymentRefund::query()->where('return_request_id', $return->id)->firstOrFail();

    expect($refund->status)->toBe(PaymentRefund::STATUS_PENDING)
        ->and($refund->payout_id)->toBeNull()
        ->and(fakePayouts()->requests)->toBe([]);
});

test('an automatic gateway pays the COD refund to the buyer\'s UPI id; the payout webhook completes it', function () {
    $return = codReturnAfterQc($this->sellerOrder, $this->customer);
    $refund = PaymentRefund::query()->where('return_request_id', $return->id)->firstOrFail();
    $request = fakePayouts()->lastRequest();

    expect($refund->status)->toBe(PaymentRefund::STATUS_PROCESSING)
        ->and($refund->payout_id)->toBe(FakePayoutGateway::payoutIdFor($refund->idempotency_key))
        ->and($request->purpose)->toBe('refund')
        ->and($request->beneficiary['vpa'])->toBe('buyer@okbank')
        ->and($request->amount->toDecimal())->toBe($refund->amount->toDecimal());

    expect(app(PayoutWebhookHandler::class)->handle('payout.processed', ['id' => $refund->payout_id, 'status' => 'processed', 'utr' => 'UPIREF0001']))->toBe('payment_refund');

    expect($refund->refresh()->status)->toBe(PaymentRefund::STATUS_PROCESSED)
        ->and($refund->utr)->toBe('UPIREF0001')
        ->and($return->refresh()->refund_status)->toBe(ReturnRequest::REFUND_REFUNDED)
        ->and(SellerLedgerEntry::query()->where('return_request_id', $return->id)->where('type', 'return_reversal')->exists())->toBeTrue();
});

test('a failed COD refund payout is marked failed for Finance to retry', function () {
    fakePayouts()->mode = 'failed';
    $return = codReturnAfterQc($this->sellerOrder, $this->customer);

    expect(PaymentRefund::query()->where('return_request_id', $return->id)->value('status'))->toBe(PaymentRefund::STATUS_FAILED);
});
