<?php

use Modules\Core\Support\Money;
use Modules\Order\Services\TaxCalculator;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;

require_once __DIR__.'/../Helpers.php';

test('an intra-state line splits tax into CGST and SGST', function () {
    $tax = app(TaxCalculator::class)->forLine(Money::of('1050'), '5', false);

    expect($tax['taxable']->toDecimal())->toBe('1000.00')
        ->and($tax['cgst']->toDecimal())->toBe('25.00')
        ->and($tax['sgst']->toDecimal())->toBe('25.00')
        ->and($tax['igst']->isZero())->toBeTrue();
});

test('an inter-state line carries IGST only', function () {
    $tax = app(TaxCalculator::class)->forLine(Money::of('1180'), '18.00', true);

    expect($tax['taxable']->toDecimal())->toBe('1000.00')
        ->and($tax['igst']->toDecimal())->toBe('180.00')
        ->and($tax['cgst']->isZero())->toBeTrue()
        ->and($tax['sgst']->isZero())->toBeTrue();
});

test('taxable value plus tax always equals the line amount, odd paisa to CGST', function (string $amount, string $rate) {
    $line = Money::of($amount);

    foreach ([false, true] as $interState) {
        $tax = app(TaxCalculator::class)->forLine($line, $rate, $interState);

        expect($tax['taxable']->plus($tax['cgst'])->plus($tax['sgst'])->plus($tax['igst'])->equals($line))->toBeTrue()
            ->and($tax['cgst']->paise - $tax['sgst']->paise)->toBeIn([0, 1]);
    }
})->with([
    ['499', '5'],
    ['999.99', '12'],
    ['1', '18'],
    ['247.33', '28'],
    ['333.33', '3'],
]);

test('the place of supply compares warehouse and shipping states', function () {
    $calculator = app(TaxCalculator::class);
    $warehouse = new SellerWarehouse(['state_id' => 10, 'state_name' => 'Karnataka']);

    expect($calculator->isInterState($warehouse, null, 10, null))->toBeFalse()
        ->and($calculator->isInterState($warehouse, null, 11, null))->toBeTrue()
        ->and($calculator->isInterState(new SellerWarehouse(['state_name' => 'Karnataka']), null, null, ' karnataka '))->toBeFalse()
        ->and($calculator->isInterState(null, new Seller(['state_id' => 7]), 7, null))->toBeFalse()
        ->and($calculator->isInterState(null, null, 7, 'Kerala'))->toBeTrue();
});
