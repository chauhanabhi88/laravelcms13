<?php

use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\TaxDocument;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
});

test('finance:backfill-ledger posts what the listeners missed and is safe to repeat', function () {
    [, $sellerOrder] = financeDeliveredOrder();
    // Simulate an order delivered before the Finance module existed.
    DB::table('seller_ledger_entry')->where('seller_order_id', $sellerOrder->id)->delete();
    DB::table('tax_document')->where('seller_order_id', $sellerOrder->id)->delete();

    $this->artisan('finance:backfill-ledger', ['--dry-run' => true])->expectsOutputToContain('1 delivered seller order(s)')->assertSuccessful();
    expect(SellerLedgerEntry::query()->where('seller_order_id', $sellerOrder->id)->count())->toBe(0);

    $this->artisan('finance:backfill-ledger')->assertSuccessful();
    $this->artisan('finance:backfill-ledger')->assertSuccessful();

    expect(SellerLedgerEntry::query()->where('seller_order_id', $sellerOrder->id)->count())->toBe(3)
        ->and(TaxDocument::query()->where('seller_order_id', $sellerOrder->id)->count())->toBe(1);
});

test('finance:release-eligible and finance:build-payouts --force', function () {
    $seller = financeSeller();
    financeDeliveredOrder($seller);
    SellerLedgerEntry::query()->where('seller_id', $seller->id)->toBase()->update(['eligible_on' => now()->subDay()->toDateString()]);

    $this->artisan('finance:release-eligible')->expectsOutputToContain('Released 3')->assertSuccessful();
    $this->artisan('finance:build-payouts', ['--force' => true])->expectsOutputToContain('1 payout(s)')->assertSuccessful();

    expect(PayoutBatch::query()->count())->toBe(1);
});

test('finance:fee-invoices validates the period', function () {
    $this->artisan('finance:fee-invoices', ['period' => '2026-13'])->assertFailed();
    $this->artisan('finance:fee-invoices', ['period' => now()->format('Y-m')])->expectsOutputToContain('Issued 0')->assertSuccessful();
});

test('the finance crons and menu are registered', function () {
    expect(DB::table('cron')->where('command', 'like', 'finance:%')->count())->toBe(4)
        ->and(DB::table('menu')->where('link', 'admin.payout_batch.index')->exists())->toBeTrue()
        ->and(DB::table('commission_rule')->where('scope', 'global')->value('value'))->toBe('0.00');
});
