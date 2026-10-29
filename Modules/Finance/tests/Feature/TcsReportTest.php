<?php

use Modules\Finance\Services\TcsTdsCalculator;
use Modules\Seller\Models\Seller;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
});

test('net taxable value = supplies − returns, and the TCS reconciles with the rate', function () {
    $seller = financeSeller();
    [, $first, , $customer] = financeDeliveredOrder($seller, '1050', 2, '5');
    financeDeliveredOrder($seller, '2100', 1, '5', customer: $customer);
    financeRefundedReturn($first, $customer);

    $rows = app(TcsTdsCalculator::class)->monthlyReport(now()->format('Y-m'));
    $row = collect($rows)->firstWhere('seller_id', $seller->id);

    // Supplies 2000 + 2000 taxable, one ₹1000 unit returned.
    expect($row['gross']->toDecimal())->toBe('4000.00')
        ->and($row['returns']->toDecimal())->toBe('1000.00')
        ->and($row['net']->toDecimal())->toBe('3000.00')
        ->and($row['tcs']->toDecimal())->toBe('30.00')
        ->and($row['computed']->toDecimal())->toBe('30.00')
        ->and($row['cgst']->plus($row['sgst'])->plus($row['igst'])->toDecimal())->toBe('30.00')
        ->and($row['gstin'])->toBe($seller->gstin);
});

test('sellers without a GSTIN collect no TCS and are not in the report', function () {
    $seller = financeSeller();
    Seller::query()->whereKey($seller->id)->update(['gstin' => null]);
    [, $sellerOrder] = financeDeliveredOrder($seller->refresh());

    expect(ledgerByType($sellerOrder))->not->toHaveKey('tcs')
        ->and(collect(app(TcsTdsCalculator::class)->monthlyReport(now()->format('Y-m')))->firstWhere('seller_id', $seller->id))->toBeNull();
});

test('TDS uses the higher rate when the seller has no PAN', function () {
    $seller = financeSeller();
    Seller::query()->whereKey($seller->id)->update(['pan' => null, 'pan_last4' => null]);
    [, $sellerOrder] = financeDeliveredOrder($seller->refresh(), '1000');

    expect(ledgerByType($sellerOrder)['tds']->amount->toDecimal())->toBe('50.00');
});

test('the admin report page and CSV export', function () {
    $seller = financeSeller();
    financeDeliveredOrder($seller);
    $admin = financeAdmin();

    $this->actingAs($admin)->get(route('admin.tcs_report.index', ['period' => now()->format('Y-m')]))
        ->assertOk()
        ->assertSee('data-seller="'.$seller->code.'"', false);

    $csv = $this->actingAs($admin)->get(route('admin.tcs_report.export', ['period' => now()->format('Y-m')]))->assertOk()->streamedContent();

    expect($csv)->toContain('tcs_total')->toContain($seller->code);
});
