<?php

use Modules\Seller\Support\NameMatcher;

test('identical names after normalisation score 100', function () {
    expect(NameMatcher::score('M/s. Shree Traders Pvt Ltd', 'SHREE TRADERS PRIVATE LIMITED'))->toBe(100)
        ->and(NameMatcher::score('Mr. Arjun Mehta', 'MEHTA ARJUN'))->toBe(100);
});

test('a small typo still scores high', function () {
    expect(NameMatcher::score('Arjun Mehta', 'ARJUN MEHTAA'))->toBeGreaterThanOrEqual(80);
});

test('different names score low', function () {
    expect(NameMatcher::score('Arjun Mehta', 'Kavya Iyer'))->toBeLessThan(50)
        ->and(NameMatcher::score('', 'Kavya Iyer'))->toBe(0);
});

test('the best candidate wins', function () {
    expect(NameMatcher::bestScore('SHREE TRADERS', ['Arjun Mehta', null, 'Shree Traders']))->toBe(100);
});
