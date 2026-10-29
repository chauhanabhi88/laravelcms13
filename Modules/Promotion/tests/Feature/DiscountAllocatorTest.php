<?php

use Modules\Core\Support\Money;
use Modules\Promotion\Services\DiscountAllocator;

test('a discount is spread pro-rata by line value', function () {
    $shares = app(DiscountAllocator::class)->allocate(Money::of('100'), [1 => Money::of('300'), 2 => Money::of('100')]);

    expect($shares[1]->toDecimal())->toBe('75.00')
        ->and($shares[2]->toDecimal())->toBe('25.00');
});

test('the rounding remainder goes to the largest line and the shares sum to the discount exactly', function () {
    $shares = app(DiscountAllocator::class)->allocate(Money::of('100'), [7 => Money::of('100'), 8 => Money::of('100'), 9 => Money::of('101')]);
    $sum = array_reduce($shares, fn (Money $total, Money $share) => $total->plus($share), Money::zero());

    expect($sum->toDecimal())->toBe('100.00')
        ->and($shares[9]->paise)->toBeGreaterThanOrEqual($shares[7]->paise)
        ->and(array_keys($shares))->toBe([7, 8, 9]);
});

test('odd paise amounts over many lines still sum exactly', function () {
    $lines = [];
    foreach (range(1, 7) as $id) {
        $lines[$id] = Money::of((string) (97 + $id * 13));
    }

    $shares = app(DiscountAllocator::class)->allocate(Money::of('33.33'), $lines);

    expect(array_sum(array_map(fn (Money $share) => $share->paise, $shares)))->toBe(3333);
});

test('nothing to allocate gives no shares', function () {
    $allocator = app(DiscountAllocator::class);

    expect($allocator->allocate(Money::zero(), [1 => Money::of('10')]))->toBe([])
        ->and($allocator->allocate(Money::of('10'), []))->toBe([])
        ->and($allocator->allocate(Money::of('10'), [1 => Money::zero()]))->toBe([]);
});
