<?php

use Modules\Customer\Support\PhoneNumber;

test('common ways of writing an Indian mobile normalise to E.164', function (string $input) {
    expect(PhoneNumber::normalise($input))->toBe('+919876543210');
})->with(['9876543210', '+91 98765 43210', '09876543210', '919876543210', '+91-98765-43210', ' 98765 43210 ']);

test('numbers that are not Indian mobiles are rejected', function (string $input) {
    expect(PhoneNumber::normalise($input))->toBeNull()
        ->and(PhoneNumber::isValid($input))->toBeFalse();
})->with(['1234567890', '5876543210', '98765', '98765432101', '+1 202 555 0101', '+44 7700 900123', 'abcdefghij', '']);

test('non-string values are rejected', function () {
    expect(PhoneNumber::normalise(null))->toBeNull()
        ->and(PhoneNumber::normalise(['9876543210']))->toBeNull()
        ->and(PhoneNumber::normalise(9876543210))->toBe('+919876543210');
});

test('numbers are formatted for display and masked for code screens', function () {
    expect(PhoneNumber::display('+919876543210'))->toBe('+91 98765 43210')
        ->and(PhoneNumber::national('+919876543210'))->toBe('9876543210')
        ->and(PhoneNumber::mask('+919876543210'))->toBe('+91 98XXX XX210')
        ->and(PhoneNumber::maskEmail('asha.rao@example.com'))->toBe('as***@example.com')
        ->and(PhoneNumber::display(null))->toBe('');
});
