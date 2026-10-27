<?php

use Modules\Core\Support\Money;

test('it parses rupee strings exactly', function (string|int|float $input, int $paise) {
    expect(Money::of($input)->paise)->toBe($paise);
})->with([
    ['249.50', 24950],
    ['249.5', 24950],
    ['0.1', 10],
    ['1,23,456.75', 12345675],
    ['-12.34', -1234],
    ['10.005', 1001],
    ['10.004', 1000],
    [249, 24900],
    [0.1 + 0.2, 30],
]);

test('it rejects values that are not amounts', function (string $input) {
    Money::of($input);
})->throws(InvalidArgumentException::class)->with(['', 'abc', '1.2.3', '12a']);

test('it adds, subtracts and multiplies', function () {
    $price = Money::of('249.50');

    expect($price->plus(Money::of('0.50'))->toDecimal())->toBe('250.00')
        ->and($price->minus(Money::of('300'))->toDecimal())->toBe('-50.50')
        ->and($price->times(3)->toDecimal())->toBe('748.50')
        ->and($price->times('1.5')->toDecimal())->toBe('374.25');
});

test('percent rounds half away from zero by default', function () {
    expect(Money::of('100')->percent('18')->toDecimal())->toBe('18.00')
        ->and(Money::of('0.25')->percent('10')->paise)->toBe(3)
        ->and(Money::of('0.25')->percent('10', RoundingMode::HalfEven)->paise)->toBe(2)
        ->and(Money::of('0.25')->percent('10', RoundingMode::TowardsZero)->paise)->toBe(2)
        ->and(Money::of('-0.25')->percent('10')->paise)->toBe(-3)
        ->and(Money::of('999.99')->percent('2.5')->toDecimal())->toBe('25.00');
});

test('allocate splits without losing a paisa and gives the remainder to the largest ratio', function () {
    $shares = Money::of('100')->allocate([1, 1, 1]);

    expect(array_map(fn (Money $share) => $share->toDecimal(), $shares))->toBe(['33.34', '33.33', '33.33']);

    $shares = Money::of('10')->allocate(['a' => 1, 'b' => 3]);

    expect($shares['a']->toDecimal())->toBe('2.50')->and($shares['b']->toDecimal())->toBe('7.50');

    $shares = Money::paise(-101)->allocate([1, 2]);

    expect($shares[0]->paise + $shares[1]->paise)->toBe(-101)->and($shares[1]->paise)->toBe(-68);
});

test('allocate refuses unusable ratios', function (array $ratios) {
    Money::of('10')->allocate($ratios);
})->throws(InvalidArgumentException::class)->with([[[]], [[0, 0]], [[1, -1]]]);

test('it formats with Indian digit grouping', function (string $amount, bool $showPaise, string $formatted) {
    expect(Money::of($amount)->format($showPaise))->toBe($formatted);
})->with([
    ['123456.5', true, '₹1,23,456.50'],
    ['123456', false, '₹1,23,456'],
    ['123456.5', false, '₹1,23,456.50'],
    ['999', false, '₹999'],
    ['1000', false, '₹1,000'],
    ['12345678.9', true, '₹1,23,45,678.90'],
    ['-1500', false, '-₹1,500'],
    ['0', true, '₹0.00'],
]);

test('it compares amounts', function () {
    expect(Money::of('5')->compare(Money::of('6')))->toBe(-1)
        ->and(Money::of('6')->compare(Money::of('5')))->toBe(1)
        ->and(Money::of('5')->equals(Money::paise(500)))->toBeTrue()
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::of('-1')->isNegative())->toBeTrue();
});

test('it refuses amounts that overflow', function () {
    Money::paise(PHP_INT_MAX)->plus(Money::paise(1));
})->throws(OverflowException::class);
