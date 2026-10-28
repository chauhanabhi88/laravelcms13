<?php

use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Payment\Contracts\CodRiskCheck;
use Modules\Payment\DTO\CodEligibility;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\CodEligibilityService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
    paymentSettings(['cod_enabled' => 1, 'cod_min_value' => '0', 'cod_max_value' => '5000', 'cod_daily_limit' => 3, 'cod_otp_threshold' => '2000', 'cod_otp_new_customers' => 2]);
});

function codCheck(array $overrides = []): CodEligibility
{
    return app(CodEligibilityService::class)->check(
        $overrides['total'] ?? Money::of('499'),
        $overrides['customer'] ?? Customer::factory()->withPhone()->create(),
        array_key_exists('pincode', $overrides) ? $overrides['pincode'] : paymentPincode()->pincode,
        $overrides['items'] ?? true,
    );
}

test('an ordinary order is eligible for COD without an OTP', function () {
    expect(codCheck())
        ->allowed->toBeTrue()
        ->otpRequired->toBeFalse()
        ->reason->toBeNull();
});

test('each COD rule blocks on its own with a reason', function (Closure $arrange, string $code) {
    $result = codCheck($arrange());

    expect($result->allowed)->toBeFalse()
        ->and($result->reasonCode)->toBe($code)
        ->and($result->reason)->not->toBeEmpty();
})->with([
    'disabled' => [function () {
        paymentSettings(['cod_enabled' => 2]);

        return [];
    }, CodEligibility::REASON_DISABLED],
    'pincode without COD' => [fn () => ['pincode' => paymentPincode(null, codAllowed: false)->pincode], CodEligibility::REASON_PINCODE],
    'unknown pincode' => [fn () => ['pincode' => '999999'], CodEligibility::REASON_PINCODE],
    'no pincode' => [fn () => ['pincode' => null], CodEligibility::REASON_PINCODE],
    'above max' => [fn () => ['total' => Money::of('5000.01')], CodEligibility::REASON_ABOVE_MAX],
    'below min' => [function () {
        paymentSettings(['cod_min_value' => '200']);

        return ['total' => Money::of('199')];
    }, CodEligibility::REASON_BELOW_MIN],
    'item without COD' => [fn () => ['items' => false], CodEligibility::REASON_ITEMS],
    'blocked customer' => [fn () => ['customer' => Customer::factory()->create(['cod_blocked' => 1])], CodEligibility::REASON_CUSTOMER_BLOCKED],
    'daily limit' => [function () {
        $customer = Customer::factory()->create();
        Payment::factory()->cod()->forCustomer($customer)->count(3)->create();

        return ['customer' => $customer];
    }, CodEligibility::REASON_DAILY_LIMIT],
]);

test('cancelled COD payments and earlier days do not count towards the daily limit', function () {
    $customer = Customer::factory()->create();
    Payment::factory()->cod()->forCustomer($customer)->count(2)->create();
    Payment::factory()->cod()->forCustomer($customer)->create(['status' => Payment::STATUS_CANCELLED]);
    Payment::factory()->cod()->forCustomer($customer)->create(['created_at' => now()->subDay()]);

    expect(codCheck(['customer' => $customer])->allowed)->toBeTrue();
});

test('the order value limits are inclusive', function () {
    expect(codCheck(['total' => Money::of('5000')])->allowed)->toBeTrue();
});

test('an OTP is required from the threshold or for customers without a delivered order', function () {
    expect(codCheck(['total' => Money::of('1999.99')])->otpRequired)->toBeFalse()
        ->and(codCheck(['total' => Money::of('2000')])->otpRequired)->toBeTrue();

    paymentSettings(['cod_otp_new_customers' => 1]);
    $newCustomer = Customer::factory()->create();
    $returning = Customer::factory()->create();
    Payment::factory()->cod()->collected()->forCustomer($returning)->create(['created_at' => now()->subWeek()]);

    expect(codCheck(['customer' => $newCustomer])->otpRequired)->toBeTrue()
        ->and(codCheck(['customer' => $returning])->otpRequired)->toBeFalse();
});

test('tagged risk checks can block COD', function () {
    app()->bind('test.cod_risk', fn () => new class implements CodRiskCheck
    {
        public function blockReason(Customer $customer, Money $orderTotal, ?string $pincode): ?string
        {
            return 'Too many returned COD orders.';
        }
    });
    app()->tag(['test.cod_risk'], CodEligibilityService::RISK_CHECK_TAG);

    expect(codCheck())
        ->allowed->toBeFalse()
        ->reasonCode->toBe(CodEligibility::REASON_RISK)
        ->reason->toBe('Too many returned COD orders.');
});

test('an order is checked with its COD amount', function () {
    $order = paymentOrder(['amount' => Money::of('4990'), 'codFee' => Money::of('20')]);

    expect(app(CodEligibilityService::class)->checkOrder($order))
        ->allowed->toBeFalse()
        ->reasonCode->toBe(CodEligibility::REASON_ABOVE_MAX);
});
