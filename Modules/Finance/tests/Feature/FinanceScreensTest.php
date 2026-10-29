<?php

use Illuminate\Support\ViewErrorBag;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Models\LedgerAdjustment;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Models\PayoutHold;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Models\TaxDocument;
use Modules\Finance\Services\PayoutService;
use Modules\Seller\Models\SellerUser;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
});

test('every finance console screen renders for a finance admin', function () {
    [$seller, $sellerOrder] = payableSeller();
    $batch = app(PayoutService::class)->build();
    $admin = financeAdmin();

    foreach (['commission_rule', 'fee_rule', 'ledger', 'payout_batch', 'payout_hold', 'tax_document', 'cod_remittance', 'reseller_withdrawal'] as $grid) {
        $this->actingAs($admin)->get(route('admin.'.$grid.'.index'))->assertOk();
        $this->actingAs($admin)->post(route('admin.'.$grid.'.filters'))->assertOk()->assertJson(['type' => 'success']);
    }

    $this->actingAs($admin)->get(route('admin.tcs_report.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.commission_rule.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.fee_rule.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.payout_batch.show', [$batch->id]))->assertOk()->assertSee($batch->batch_no);
    $this->actingAs($admin)->get(route('admin.ledger.index'))->assertOk()->assertSee($sellerOrder->sub_order_no);

    expect($this->actingAs($admin)->get(route('admin.ledger.export'))->streamedContent())->toContain($seller->code);
});

test('finance screens need their permission', function () {
    $admin = financeAdmin(['admin.ledger.index']);

    $this->actingAs($admin)->get(route('admin.ledger.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.payout_batch.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.payout_batch.build'))->assertForbidden();
});

test('commission rules are created, edited and deleted from the console', function () {
    $seller = financeSeller();
    $admin = financeAdmin();

    $this->actingAs($admin)->post(route('admin.commission_rule.store'), [
        'scope' => 'seller', 'seller_id' => $seller->id, 'type' => 'percent', 'value' => '150',
        'applies_to' => 'item_price', 'effective_from' => now()->toDateString(), 'status' => 1,
    ])->assertRedirect();
    $errors = session('errors');
    $messages = $errors instanceof ViewErrorBag ? $errors->getBag('default')->toArray() : (array) ($errors['default']['messages'] ?? []);
    expect($messages)->toHaveKey('value')
        ->and(CommissionRule::query()->where('seller_id', $seller->id)->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.commission_rule.store'), [
        'scope' => 'seller', 'seller_id' => $seller->id, 'type' => 'percent', 'value' => '12.5',
        'applies_to' => 'item_price', 'effective_from' => now()->toDateString(), 'status' => 1,
    ])->assertRedirect(route('admin.commission_rule.index'));

    $rule = CommissionRule::query()->where('seller_id', $seller->id)->firstOrFail();
    expect($rule->value)->toBe('12.50')->and($rule->catalog_category_id)->toBeNull()->and($rule->created_by)->toBe($admin->id);

    $this->actingAs($admin)->get(route('admin.commission_rule.edit', [$rule->id]))->assertOk();
    $this->actingAs($admin)->post(route('admin.commission_rule.update', [$rule->id]), [
        'scope' => 'global', 'type' => 'flat_per_item', 'value' => '20', 'applies_to' => 'item_price',
        'effective_from' => now()->toDateString(), 'status' => 1,
    ])->assertRedirect();
    expect($rule->refresh()->scope)->toBe('global')->and($rule->seller_id)->toBeNull();

    $this->actingAs($admin)->post(route('admin.commission_rule.delete', [$rule->id]))->assertRedirect(route('admin.commission_rule.index'));
    expect(CommissionRule::query()->whereKey($rule->id)->exists())->toBeFalse();
});

test('manual adjustments need a second admin', function () {
    $seller = financeSeller();
    $maker = financeAdmin();

    $this->actingAs($maker)->post(route('admin.ledger.adjust'), ['seller_code' => $seller->code, 'direction' => 'credit', 'amount' => '250', 'reason' => 'Goodwill credit for a courier delay'])
        ->assertRedirect(route('admin.ledger.index'));
    $adjustment = LedgerAdjustment::query()->where('seller_id', $seller->id)->firstOrFail();

    $this->actingAs($maker)->post(route('admin.ledger.approve_adjustment', [$adjustment->id]))->assertSessionHas('error');
    expect(SellerLedgerEntry::query()->where('seller_id', $seller->id)->exists())->toBeFalse();

    $this->actingAs(financeAdmin())->post(route('admin.ledger.approve_adjustment', [$adjustment->id]))->assertSessionHas('success');
    $entry = SellerLedgerEntry::query()->where('seller_id', $seller->id)->firstOrFail();

    expect($entry->type)->toBe('adjustment')
        ->and($entry->amount->toDecimal())->toBe('250.00')
        ->and($entry->status)->toBe(SellerLedgerEntry::STATUS_ELIGIBLE)
        ->and($adjustment->refresh()->status)->toBe(LedgerAdjustment::STATUS_APPROVED);
});

test('holds are placed and released from the console', function () {
    [$seller] = payableSeller();
    $admin = financeAdmin();

    $this->actingAs($admin)->post(route('admin.payout_hold.store'), ['seller_code' => $seller->code, 'reason' => 'Chargeback investigation'])->assertSessionHas('success');
    expect(SellerLedgerEntry::query()->where('seller_id', $seller->id)->pluck('status')->unique()->all())->toBe([SellerLedgerEntry::STATUS_ON_HOLD]);

    $hold = PayoutHold::query()->where('seller_id', $seller->id)->firstOrFail();
    $this->actingAs($admin)->post(route('admin.payout_hold.release', [$hold->id]))->assertSessionHas('success');
    expect(SellerLedgerEntry::query()->where('seller_id', $seller->id)->pluck('status')->unique()->all())->toBe([SellerLedgerEntry::STATUS_ELIGIBLE]);
});

test('a batch is built, approved by another admin and marked paid from the console', function () {
    financeSettings(['payout_gateway' => 'manual']);
    payableSeller();
    $maker = financeAdmin();

    $this->actingAs($maker)->post(route('admin.payout_batch.build'))->assertRedirect();
    $batch = PayoutBatch::query()->latest('id')->firstOrFail();

    $this->actingAs($maker)->post(route('admin.payout_batch.approve', [$batch->id]))->assertSessionHas('error');
    $this->actingAs(financeAdmin())->post(route('admin.payout_batch.approve', [$batch->id]))->assertSessionHas('success');

    $bankFile = $this->actingAs($maker)->get(route('admin.payout_batch.export_bank_file', [$batch->id]))->streamedContent();
    $payout = SellerPayout::query()->where('payout_batch_id', $batch->id)->firstOrFail();
    expect($bankFile)->toContain($payout->payout_no);

    $this->actingAs($maker)->post(route('admin.seller_payout.mark_paid', [$payout->id]), ['utr' => 'NEFT2026110001'])->assertSessionHas('success');
    expect($payout->refresh()->status)->toBe(SellerPayout::STATUS_PAID)->and($batch->refresh()->status)->toBe(PayoutBatch::STATUS_COMPLETED);
});

test('the supplier payments pages render for finance staff with the settlement breakdown', function () {
    [$seller, $sellerOrder] = payableSeller();
    $finance = SellerUser::factory()->for($seller)->role('finance')->create();
    $batch = app(PayoutService::class)->build();
    app(PayoutService::class)->approve($batch, financeAdmin());
    $payout = $batch->payouts()->firstOrFail();
    app(PayoutService::class)->markPaid($payout, 'UTR55555555');

    $this->actingAs($finance, 'seller');

    $this->get(frontRoute('seller.payments.overview'))->assertOk()->assertSee('data-card="next"', false);
    $this->get(frontRoute('seller.payments.statement'))->assertOk()->assertSee($sellerOrder->sub_order_no);
    $this->get(frontRoute('seller.payments.payouts'))->assertOk()->assertSee($payout->payout_no);
    $this->get(frontRoute('seller.payments.payouts.show', ['payoutNo' => $payout->payout_no]))->assertOk()->assertSee('data-order="'.$sellerOrder->sub_order_no.'"', false);
    $this->get(frontRoute('seller.payments.order_settlement', ['subOrderNo' => $sellerOrder->sub_order_no]))->assertOk()->assertSee('data-line="net"', false)->assertSee('1,038.95');
    $this->get(frontRoute('seller.payments.invoices'))->assertOk();

    expect($this->get(frontRoute('seller.payments.statement.export'))->streamedContent())->toContain($sellerOrder->sub_order_no);

    $invoice = TaxDocument::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();
    $this->get(frontRoute('seller.payments.invoices.download', [$invoice->id]))->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('sellers never see another seller\'s payouts, settlements or invoices', function () {
    [$sellerA, $sellerOrder] = payableSeller();
    $batch = app(PayoutService::class)->build();
    $payout = $batch->payouts()->firstOrFail();
    $invoice = TaxDocument::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    $otherOwner = fulfilmentOwner(financeSeller());
    $this->actingAs($otherOwner, 'seller');

    $this->get(frontRoute('seller.payments.payouts.show', ['payoutNo' => $payout->payout_no]))->assertNotFound();
    $this->get(frontRoute('seller.payments.order_settlement', ['subOrderNo' => $sellerOrder->sub_order_no]))->assertNotFound();
    $this->get(frontRoute('seller.payments.invoices.download', [$invoice->id]))->assertNotFound();
    $this->get(frontRoute('seller.payments.statement'))->assertOk()->assertDontSee($sellerOrder->sub_order_no);
    expect($sellerA->id)->not->toBe($otherOwner->seller_id);
});

test('order staff cannot open the payments area', function () {
    [$seller] = payableSeller();
    $orders = SellerUser::factory()->for($seller)->role('orders')->create();

    $this->actingAs($orders, 'seller')->get(frontRoute('seller.payments.overview'))->assertForbidden();
});

test('the supplier dashboard shows the next payout', function () {
    [$seller] = payableSeller();

    $this->actingAs(fulfilmentOwner($seller), 'seller')->get(frontRoute('seller.dashboard'))
        ->assertOk()
        ->assertSee('1,038.95');
});
