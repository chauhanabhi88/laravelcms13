<?php

use Modules\Seller\Support\Validation\Gstin;
use Modules\Seller\Support\Validation\Ifsc;
use Modules\Seller\Support\Validation\Pan;

/**
 * Run a ValidationRule outside Laravel's validator and return the failure keys.
 *
 * @return list<string>
 */
function sellerRuleFailures(object $rule, mixed $value): array
{
    $failures = [];
    $rule->validate('field', $value, function (string $message) use (&$failures) {
        $failures[] = $message;

        return null;
    });

    return $failures;
}

test('a valid GSTIN passes', function (string $gstin) {
    expect(sellerRuleFailures(new Gstin, $gstin))->toBe([])
        ->and(Gstin::isValid($gstin))->toBeTrue();
})->with([
    'firm in Maharashtra' => '27AAPFU0939F1ZV',
    'company in Maharashtra' => '27AAACR5055K1Z7',
    'person in Karnataka' => '29ABCPE1234F1Z7',
    'lower case is normalised' => '29abcpe1234f1z7',
]);

test('a GSTIN with a wrong check character fails', function () {
    expect(sellerRuleFailures(new Gstin, '27AAPFU0939F1ZW'))->toBe(['seller::validation.gstin.checksum']);
});

test('a malformed GSTIN fails on format', function (string $gstin) {
    expect(sellerRuleFailures(new Gstin, $gstin))->toBe(['seller::validation.gstin.format']);
})->with([
    'too short' => '27AAPFU0939F1Z',
    'no Z in position 14' => '27AAPFU0939F1AV',
    'letters in state code' => 'KAAAPFU0939F1ZV',
    'entity number zero' => '27AAPFU0939F0ZV',
]);

test('an unknown state code fails', function () {
    expect(sellerRuleFailures(new Gstin, Gstin::withChecksum('42ABCPE1234F1Z')))->toBe(['seller::validation.gstin.state']);
});

test('the GSTIN must embed the PAN given', function () {
    expect(sellerRuleFailures(new Gstin('ABCPE1234F'), '29ABCPE1234F1Z7'))->toBe([])
        ->and(sellerRuleFailures(new Gstin('ZZZPZ9999Z'), '29ABCPE1234F1Z7'))->toBe(['seller::validation.gstin.pan_mismatch']);
});

test('the state code maps to the ISO state', function () {
    expect(Gstin::stateCode('29ABCPE1234F1Z7'))->toBe('29')
        ->and(Gstin::isoStateCode('29ABCPE1234F1Z7'))->toBe('KA')
        ->and(Gstin::isoStateCode('27AAPFU0939F1ZV'))->toBe('MH')
        ->and(Gstin::isoStateCode(Gstin::withChecksum('99ABCPE1234F1Z')))->toBeNull()
        ->and(Gstin::embeddedPan('29ABCPE1234F1Z7'))->toBe('ABCPE1234F');
});

test('withChecksum builds valid GSTINs', function () {
    expect(Gstin::withChecksum('27AAPFU0939F1Z'))->toBe('27AAPFU0939F1ZV');
});

test('PAN format', function (string $pan, bool $valid) {
    expect(sellerRuleFailures(new Pan, $pan))->toBe($valid ? [] : ['seller::validation.pan.format']);
})->with([
    'person' => ['ABCPE1234F', true],
    'company' => ['AAACR5055K', true],
    'lower case' => ['abcpe1234f', true],
    'invalid holder type' => ['ABCDE1234F', false],
    'digits misplaced' => ['ABCP12345F', false],
    'too long' => ['ABCPE1234FF', false],
    'empty' => ['', false],
]);

test('IFSC format', function (string $ifsc, bool $valid) {
    expect(sellerRuleFailures(new Ifsc, $ifsc))->toBe($valid ? [] : ['seller::validation.ifsc.format']);
})->with([
    'numeric branch' => ['HDFC0001234', true],
    'alphanumeric branch' => ['SBIN0ABC123', true],
    'fifth character not zero' => ['HDFC1001234', false],
    'too short' => ['HDFC000123', false],
    'digits in bank code' => ['HD1C0001234', false],
]);
