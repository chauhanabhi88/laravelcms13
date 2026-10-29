<?php

use Modules\Finance\Models\ResellerWalletEntry;
use Modules\Finance\Models\SellerLedgerEntry;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    [, $this->sellerOrder] = financeDeliveredOrder();
    $this->sale = ledgerByType($this->sellerOrder)['sale'];
});

test('updating an entry\'s amount throws', function () {
    $this->sale->amount = '1.00';

    expect(fn () => $this->sale->save())->toThrow(LogicException::class);
    expect(SellerLedgerEntry::query()->findOrFail($this->sale->id)->amount->toDecimal())->toBe('1050.00');
});

test('type, direction and the idempotency key are immutable too', function (string $column, string $value) {
    $this->sale->{$column} = $value;

    expect(fn () => $this->sale->save())->toThrow(LogicException::class);
})->with([
    'type' => ['type', 'adjustment'],
    'direction' => ['direction', 'debit'],
    'key' => ['idempotency_key', 'sale:item:0'],
]);

test('entries cannot be deleted', function () {
    expect(fn () => $this->sale->delete())->toThrow(LogicException::class)
        ->and(SellerLedgerEntry::query()->whereKey($this->sale->id)->exists())->toBeTrue();
});

test('only the status and payout link move', function () {
    $this->sale->forceFill(['status' => SellerLedgerEntry::STATUS_ELIGIBLE])->save();

    expect($this->sale->refresh()->status)->toBe(SellerLedgerEntry::STATUS_ELIGIBLE);
});

test('reseller wallet entries are append-only as well', function () {
    $entry = ResellerWalletEntry::query()->create([
        'customer_id' => 1,
        'type' => ResellerWalletEntry::TYPE_ADJUSTMENT,
        'direction' => ResellerWalletEntry::CREDIT,
        'amount' => '10.00',
        'status' => ResellerWalletEntry::STATUS_AVAILABLE,
        'idempotency_key' => 'test:'.uniqid(),
    ]);
    $entry->amount = '20.00';

    expect(fn () => $entry->save())->toThrow(LogicException::class);
});
