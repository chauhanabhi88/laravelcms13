<?php

use Illuminate\Support\Facades\Notification;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Finance\Services\PayoutHoldService;
use Modules\Finance\Services\PayoutService;
use Modules\Finance\Services\SellerBalanceService;
use Modules\Notification\Models\NotificationLog;
use Modules\Order\Models\SellerOrder;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    $this->payouts = app(PayoutService::class);
});

test('balances follow the entries through pending, eligible, on hold and settled', function () {
    $seller = financeSeller();
    financeDeliveredOrder($seller, '1050');
    $balances = app(SellerBalanceService::class);

    expect($balances->balances($seller->id)['pending']->toDecimal())->toBe('1038.95')
        ->and($balances->balances($seller->id)['eligible']->toDecimal())->toBe('0.00');

    financeReleaseAfter(40);
    expect($balances->balances($seller->id)['eligible']->toDecimal())->toBe('1038.95')
        ->and($balances->balances($seller->id)['pending']->toDecimal())->toBe('0.00');

    $hold = app(PayoutHoldService::class)->place($seller, 'KYC re-check', financeAdmin());
    expect($balances->balances($seller->id)['on_hold']->toDecimal())->toBe('1038.95')
        ->and($balances->balances($seller->id)['eligible']->toDecimal())->toBe('0.00');

    app(PayoutHoldService::class)->release($hold);
    $batch = $this->payouts->build(null, financeAdmin());
    $this->payouts->approve($batch, financeAdmin());
    $payout = $batch->payouts()->firstOrFail();

    expect($balances->balances($seller->id)['in_payout']->toDecimal())->toBe('1038.95');

    $this->payouts->markPaid($payout, 'UTR0000001');
    $after = $balances->balances($seller->id);

    expect($after['settled']->toDecimal())->toBe('1038.95')
        ->and($after['eligible']->toDecimal())->toBe('0.00')
        ->and($after['in_payout']->toDecimal())->toBe('0.00');
});

test('a batch pays each seller the net of their eligible entries', function () {
    [$sellerA] = payableSeller('1050');
    [$sellerB] = payableSeller('2100');

    $batch = $this->payouts->build(null, financeAdmin());
    $amounts = $batch->payouts()->get()->mapWithKeys(fn (SellerPayout $payout) => [$payout->seller_id => $payout->amount->toDecimal()]);

    // 1050 − TCS 10 − TDS 1.05; 2100 − TCS 20 − TDS 2.10.
    expect($batch->status)->toBe(PayoutBatch::STATUS_DRAFT)
        ->and($amounts[$sellerA->id])->toBe('1038.95')
        ->and($amounts[$sellerB->id])->toBe('2077.90')
        ->and($batch->total_amount->toDecimal())->toBe('3116.85')
        ->and($batch->sellers_count)->toBe(2)
        ->and(SellerLedgerEntry::query()->where('seller_id', $sellerA->id)->whereNull('payout_id')->exists())->toBeFalse();
});

test('negative balances carry forward and small ones wait for a later cycle', function () {
    [$negative, $sellerOrder] = payableSeller('1050');
    financeSettings(['seller_cancel_penalty' => 2000]);
    app(LedgerPosterImpl::class)->postPenalty(SellerOrder::query()->findOrFail($sellerOrder->id), 'seller_cancel');

    [$small] = payableSeller('90');
    [$paid] = payableSeller('1050');

    $batch = $this->payouts->build(null, financeAdmin());

    expect($batch->payouts()->pluck('seller_id')->all())->toBe([$paid->id])
        ->and(SellerLedgerEntry::query()->where('seller_id', $negative->id)->whereNotNull('payout_id')->exists())->toBeFalse()
        ->and(SellerLedgerEntry::query()->where('seller_id', $small->id)->whereNotNull('payout_id')->exists())->toBeFalse();
});

test('held sellers and sellers without a verified bank account are skipped', function () {
    [$held] = payableSeller();
    app(PayoutHoldService::class)->place($held, 'Fraud review', financeAdmin());
    [$noBank] = payableSeller();
    $noBank->bankAccounts()->update(['verification_status' => 'pending']);

    expect($this->payouts->build(null, financeAdmin()))->toBeNull()
        ->and(SellerLedgerEntry::query()->where('seller_id', $held->id)->value('status'))->toBe(SellerLedgerEntry::STATUS_ON_HOLD);
});

test('nothing is paid before approval, and the builder cannot approve their own batch', function () {
    payableSeller();
    $maker = financeAdmin();
    $batch = $this->payouts->build(null, $maker);

    expect(fn () => $this->payouts->process($batch))->toThrow(FinanceException::class)
        ->and(fn () => $this->payouts->approve($batch, $maker))->toThrow(FinanceException::class, trans('finance::payout_batch.messages.maker_checker'))
        ->and(fakePayouts()->requests)->toBe([]);

    $checker = financeAdmin();
    $this->payouts->approve($batch, $checker);

    expect($batch->refresh()->status)->toBe(PayoutBatch::STATUS_APPROVED)
        ->and($batch->approved_by)->toBe($checker->id);
});

test('a scheduled batch (no maker) can be approved by any finance admin', function () {
    payableSeller();
    $batch = $this->payouts->build();

    expect($batch->created_by)->toBeNull()
        ->and($this->payouts->approve($batch, financeAdmin())->status)->toBe(PayoutBatch::STATUS_APPROVED);
});

test('processing sends each payout once; payment settles the entries and notifies the seller', function () {
    Notification::fake();
    [$seller] = payableSeller();
    fulfilmentOwner($seller);
    $batch = $this->payouts->build();
    $this->payouts->approve($batch, financeAdmin());

    $this->payouts->process($batch);
    $this->payouts->process($batch->refresh());
    $payout = $batch->payouts()->firstOrFail();

    expect(fakePayouts()->requests)->toHaveCount(1)
        ->and(fakePayouts()->lastRequest()->idempotencyKey)->toBe($payout->idempotency_key)
        ->and($payout->status)->toBe(SellerPayout::STATUS_PROCESSING)
        ->and($payout->gateway_payout_id)->not->toBeNull()
        ->and($batch->refresh()->status)->toBe(PayoutBatch::STATUS_PROCESSING);

    $this->payouts->markPaid($payout, 'UTR7788990011');

    $entries = SellerLedgerEntry::query()->where('seller_id', $seller->id)->get();
    $payoutEntry = $entries->firstWhere('type', SellerLedgerEntry::TYPE_PAYOUT);

    expect($payout->refresh()->status)->toBe(SellerPayout::STATUS_PAID)
        ->and($payout->utr)->toBe('UTR7788990011')
        ->and($entries->pluck('status')->unique()->values()->all())->toBe([SellerLedgerEntry::STATUS_SETTLED])
        ->and($payoutEntry->amount->toDecimal())->toBe($payout->amount->toDecimal())
        ->and($payoutEntry->direction)->toBe('debit')
        ->and($batch->refresh()->status)->toBe(PayoutBatch::STATUS_COMPLETED)
        ->and(NotificationLog::query()->where('event_key', 'seller.payout.processed')->exists())->toBeTrue();

    // Paying twice changes nothing.
    $this->payouts->markPaid($payout, 'UTR-other');
    expect(SellerLedgerEntry::query()->where('type', 'payout')->where('seller_id', $seller->id)->count())->toBe(1);
});

test('a failed payout returns its entries to the next batch; retry relinks them', function () {
    [$seller] = payableSeller();
    $batch = $this->payouts->build();
    $this->payouts->approve($batch, financeAdmin());
    fakePayouts()->mode = 'failed';
    $this->payouts->process($batch);
    $payout = $batch->payouts()->firstOrFail();

    expect($payout->status)->toBe(SellerPayout::STATUS_FAILED)
        ->and($payout->failure_reason)->toBe('Beneficiary account is closed')
        ->and(SellerLedgerEntry::query()->where('seller_id', $seller->id)->whereNotNull('payout_id')->exists())->toBeFalse()
        ->and($batch->refresh()->status)->toBe(PayoutBatch::STATUS_PARTIALLY_FAILED);

    fakePayouts()->mode = 'processing';
    $key = $payout->idempotency_key;
    $this->payouts->retry($payout);

    expect($payout->refresh()->status)->toBe(SellerPayout::STATUS_PROCESSING)
        ->and($payout->idempotency_key)->not->toBe($key)
        ->and(SellerLedgerEntry::query()->where('seller_id', $seller->id)->where('payout_id', $payout->id)->count())->toBe(3);
});

test('the manual gateway: bank file rows and UTR upload mark payouts paid', function () {
    financeSettings(['payout_gateway' => 'manual']);
    payableSeller();
    $batch = $this->payouts->build();
    $this->payouts->approve($batch, financeAdmin());
    $payout = $batch->payouts()->with('bankAccount')->firstOrFail();

    $rows = $this->payouts->bankFileRows($batch);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['payout_no'])->toBe($payout->payout_no)
        ->and($rows[0]['ifsc'])->toBe($payout->bankAccount->ifsc)
        ->and($rows[0]['amount'])->toBe($payout->amount->toDecimal());

    $result = $this->payouts->uploadUtrs($batch, [$payout->payout_no => 'HDFCN52026110012', 'PAY-0000-999999' => 'X']);

    expect($result['paid'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1)
        ->and($payout->refresh()->status)->toBe(SellerPayout::STATUS_PAID)
        ->and($batch->refresh()->status)->toBe(PayoutBatch::STATUS_COMPLETED);
});
