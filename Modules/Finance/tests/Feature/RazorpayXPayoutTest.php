<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Services\PayoutService;

require_once __DIR__.'/../Helpers.php';

const FINANCE_RAZORPAYX_SECRET = 'whsec_finance_test';

beforeEach(function () {
    financeSetup();
    financeSettings(['payout_gateway' => 'razorpayx']);
    config([
        'seller.razorpayx.key_id' => 'rzp_test_key',
        'seller.razorpayx.key_secret' => 'rzp_test_secret',
        'seller.razorpayx.account_number' => '7878780080316316',
        'seller.razorpayx.webhook_secret' => FINANCE_RAZORPAYX_SECRET,
    ]);

    [$this->seller] = payableSeller();
    $this->seller->bankAccounts()->update(['razorpayx_fund_account_id' => 'fa_seller_0001']);
    $this->batch = app(PayoutService::class)->build();
    app(PayoutService::class)->approve($this->batch, financeAdmin());
    $this->payout = $this->batch->payouts()->firstOrFail();
});

/**
 * POST a signed RazorpayX webhook to the shared /webhooks/razorpayx endpoint.
 *
 * @param  array<string, mixed>  $payout
 */
function razorpayxPayoutWebhook(string $event, array $payout): TestResponse
{
    $body = json_encode(['event' => $event, 'payload' => ['payout' => ['entity' => $payout]]]);

    return test()->call('POST', route('webhook.razorpayx'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, FINANCE_RAZORPAYX_SECRET),
        'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_'.uniqid(),
    ], $body);
}

test('payouts are sent to RazorpayX with the idempotency header and the seller\'s fund account', function () {
    Http::fake(['*/v1/payouts' => Http::response(['id' => 'pout_000000000001', 'status' => 'processing'])]);

    app(PayoutService::class)->process($this->batch);

    Http::assertSent(function (Request $request) {
        return str_ends_with($request->url(), '/v1/payouts')
            && $request->header('X-Payout-Idempotency') === [$this->payout->idempotency_key]
            && $request['fund_account_id'] === 'fa_seller_0001'
            && $request['amount'] === $this->payout->amount->paise
            && $request['mode'] === 'IMPS'
            && $request['purpose'] === 'payout'
            && $request['reference_id'] === $this->payout->payout_no;
    });

    expect($this->payout->refresh()->status)->toBe(SellerPayout::STATUS_PROCESSING)
        ->and($this->payout->gateway_payout_id)->toBe('pout_000000000001');
});

test('payout.processed marks the payout paid with its UTR and settles the entries', function () {
    Http::fake(['*/v1/payouts' => Http::response(['id' => 'pout_000000000002', 'status' => 'queued'])]);
    app(PayoutService::class)->process($this->batch);

    razorpayxPayoutWebhook('payout.processed', ['id' => 'pout_000000000002', 'status' => 'processed', 'utr' => 'IMPS6000012345'])->assertOk();
    razorpayxPayoutWebhook('payout.processed', ['id' => 'pout_000000000002', 'status' => 'processed', 'utr' => 'IMPS6000012345'])->assertOk();

    expect($this->payout->refresh()->status)->toBe(SellerPayout::STATUS_PAID)
        ->and($this->payout->utr)->toBe('IMPS6000012345')
        ->and(SellerLedgerEntry::query()->where('seller_id', $this->seller->id)->pluck('status')->unique()->all())->toBe([SellerLedgerEntry::STATUS_SETTLED])
        ->and(SellerLedgerEntry::query()->where('seller_id', $this->seller->id)->where('type', 'payout')->count())->toBe(1)
        ->and($this->batch->refresh()->status)->toBe(PayoutBatch::STATUS_COMPLETED);
});

test('payout.failed sends the entries back to the eligible pool', function () {
    Http::fake(['*/v1/payouts' => Http::response(['id' => 'pout_000000000003', 'status' => 'processing'])]);
    app(PayoutService::class)->process($this->batch);

    razorpayxPayoutWebhook('payout.failed', ['id' => 'pout_000000000003', 'status' => 'failed', 'status_details' => ['description' => 'Invalid beneficiary']])->assertOk();

    expect($this->payout->refresh()->status)->toBe(SellerPayout::STATUS_FAILED)
        ->and($this->payout->failure_reason)->toBe('Invalid beneficiary')
        ->and(SellerLedgerEntry::query()->where('seller_id', $this->seller->id)->whereNull('payout_id')->where('status', SellerLedgerEntry::STATUS_ELIGIBLE)->count())->toBe(3);
});

test('a bounced paid payout (payout.reversed) is owed to the seller again', function () {
    Http::fake(['*/v1/payouts' => Http::response(['id' => 'pout_000000000004', 'status' => 'processed', 'utr' => 'IMPS1'])]);
    app(PayoutService::class)->process($this->batch);

    expect($this->payout->refresh()->status)->toBe(SellerPayout::STATUS_PAID);

    razorpayxPayoutWebhook('payout.reversed', ['id' => 'pout_000000000004', 'status' => 'reversed'])->assertOk();

    $credit = SellerLedgerEntry::query()->where('idempotency_key', 'payout_reversal:'.$this->payout->id)->firstOrFail();

    expect($this->payout->refresh()->status)->toBe(SellerPayout::STATUS_REVERSED)
        ->and($credit->direction)->toBe('credit')
        ->and($credit->amount->toDecimal())->toBe($this->payout->amount->toDecimal())
        ->and($credit->status)->toBe(SellerLedgerEntry::STATUS_ELIGIBLE);
});

test('a RazorpayX outage keeps the payout scheduled for a retry with the same key', function () {
    Http::fake(['*/v1/payouts' => Http::response(['error' => ['description' => 'down']], 503)]);

    app(PayoutService::class)->process($this->batch);

    expect($this->payout->refresh()->status)->toBe(SellerPayout::STATUS_SCHEDULED)
        ->and($this->payout->failure_reason)->toStartWith('Gateway unavailable');
});

test('webhooks with a bad signature are rejected', function () {
    $body = json_encode(['event' => 'payout.processed', 'payload' => ['payout' => ['entity' => ['id' => 'pout_x']]]]);

    $this->call('POST', route('webhook.razorpayx'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'bad'], $body)
        ->assertStatus(401);
});
