<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Services\SequenceService;

beforeEach(function () {
    $this->sequences = app(SequenceService::class);
});

test('numbers increase without duplicates and keep their prefix and padding', function () {
    $numbers = collect(range(1, 5))->map(fn () => $this->sequences->next('test:alpha', 'TST-', 4));

    expect($numbers->all())->toBe(['TST-0001', 'TST-0002', 'TST-0003', 'TST-0004', 'TST-0005'])
        ->and($numbers->unique())->toHaveCount(5);
});

test('each key is its own series', function () {
    $this->sequences->next('test:alpha', 'A-');
    $this->sequences->next('test:alpha', 'A-');

    expect($this->sequences->next('test:beta', 'B-'))->toBe('B-000001');
});

test('separate transactions continue the same series', function () {
    $first = DB::transaction(fn () => $this->sequences->next('test:tx', 'T-'));
    $second = DB::transaction(fn () => $this->sequences->next('test:tx', 'T-'));

    expect([$first, $second])->toBe(['T-000001', 'T-000002'])
        ->and(DB::table('number_sequence')->where('key', 'test:tx')->value('next_value'))->toBe(3);
});

test('order, return and payout numbers restart every month', function () {
    $october = Carbon::create(2026, 10, 15);
    $november = Carbon::create(2026, 11, 1);

    expect($this->sequences->orderNumber($october))->toBe('ORD-2610-000001')
        ->and($this->sequences->orderNumber($october))->toBe('ORD-2610-000002')
        ->and($this->sequences->orderNumber($november))->toBe('ORD-2611-000001')
        ->and($this->sequences->returnNumber($october))->toBe('RET-2610-000001')
        ->and($this->sequences->payoutNumber($october))->toBe('PAY-2610-000001');
});

test('the financial year rolls over on the first of April', function () {
    expect($this->sequences->financialYear(Carbon::create(2027, 3, 31)))->toBe('2026-27')
        ->and($this->sequences->financialYear(Carbon::create(2027, 4, 1)))->toBe('2027-28')
        ->and($this->sequences->financialYear(Carbon::create(2099, 6, 1)))->toBe('2099-00');
});

test('invoice numbers run per seller per financial year', function () {
    expect($this->sequences->invoiceNumber('sel123', '2026-27'))->toBe('SEL123/2026-27/000001')
        ->and($this->sequences->invoiceNumber('SEL123', '2026-27'))->toBe('SEL123/2026-27/000002')
        ->and($this->sequences->invoiceNumber('SEL123', '2027-28'))->toBe('SEL123/2027-28/000001')
        ->and($this->sequences->invoiceNumber('SEL999', '2026-27'))->toBe('SEL999/2026-27/000001');
});

test('invalid arguments are refused', function (string $key, string $prefix, int $padding) {
    $this->sequences->next($key, $prefix, $padding);
})->throws(InvalidArgumentException::class)->with([
    ['', 'X-', 6],
    ['test:pad', 'X-', 0],
    ['test:prefix', str_repeat('X', 41), 6],
]);
