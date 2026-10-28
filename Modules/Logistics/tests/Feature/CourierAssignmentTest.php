<?php

use Illuminate\Support\Facades\Http;
use Modules\Core\Support\Money;
use Modules\Logistics\DTO\AssignmentContext;
use Modules\Logistics\DTO\CarrierQuote;
use Modules\Logistics\DTO\ServiceabilityResult;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\CourierRule;
use Modules\Logistics\Services\CourierAssignmentService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->default = logisticsSetup();
});

function assignmentContext(array $overrides = []): AssignmentContext
{
    $values = array_merge([
        'origin' => '560001', 'destination' => '110001', 'zone' => 'metro', 'state' => 7,
        'weight' => 500, 'payment' => 'cod', 'seller' => 3, 'category' => 9,
    ], $overrides);

    return new AssignmentContext($values['origin'], $values['destination'], $values['zone'], $values['state'], $values['weight'], $values['payment'], $values['seller'], $values['category']);
}

function fakeShiprocketServiceability(): Courier
{
    Http::fake([
        '*/v1/external/auth/login' => Http::response(shiprocketFixture('auth')),
        '*/v1/external/courier/serviceability*' => Http::response(shiprocketFixture('serviceability')),
    ]);

    return Courier::factory()->shiprocket()->create();
}

test('the first matching rule by priority wins', function () {
    $low = CourierRule::factory()->create(['priority' => 20]);
    $high = CourierRule::factory()->create(['priority' => 5]);
    CourierRule::factory()->create(['priority' => 1, 'status' => 2]);

    expect(app(CourierAssignmentService::class)->matchRule(assignmentContext())?->id)->toBe($high->id)
        ->and($low->id)->not->toBe($high->id);
});

test('rule conditions all have to match', function (string $case) {
    [$conditions, $context, $matches] = match ($case) {
        'zone hit' => [['zones' => ['metro']], [], true],
        'zone miss' => [['zones' => ['ne_jk']], [], false],
        'prefix hit' => [['pincode_prefixes' => ['11', '40']], [], true],
        'prefix miss' => [['pincode_prefixes' => ['56']], [], false],
        'weight inside' => [['weight_min_g' => 0, 'weight_max_g' => 500], [], true],
        'weight above' => [['weight_max_g' => 499], [], false],
        'weight below' => [['weight_min_g' => 501], [], false],
        'cod only on prepaid' => [['payment' => ['cod']], ['payment' => 'prepaid'], false],
        'cod only on cod' => [['payment' => ['cod']], [], true],
        'state' => [['state_ids' => [7]], [], true],
        'other seller' => [['seller_ids' => [4]], [], false],
        'category' => [['category_ids' => [9, 10]], [], true],
        'all at once' => [['zones' => ['metro'], 'pincode_prefixes' => ['110'], 'payment' => ['cod'], 'weight_max_g' => 1000, 'seller_ids' => [3]], [], true],
        'one of many fails' => [['zones' => ['metro'], 'pincode_prefixes' => ['110'], 'payment' => ['prepaid']], [], false],
    };

    $rule = CourierRule::factory()->make(['conditions' => $conditions]);

    expect(CourierAssignmentService::matches($rule, assignmentContext($context)))->toBe($matches);
})->with(['zone hit', 'zone miss', 'prefix hit', 'prefix miss', 'weight inside', 'weight above', 'weight below', 'cod only on prepaid', 'cod only on cod', 'state', 'other seller', 'category', 'all at once', 'one of many fails']);

test('the strategy picks the carrier from the serviceability answer', function (string $strategy, string $payment, string $carrier) {
    $courier = fakeShiprocketServiceability();
    CourierRule::factory()->for($courier, 'courier')->strategy($strategy, ['99', '51'])->create();

    $assignment = app(CourierAssignmentService::class)->resolve(assignmentContext(['payment' => $payment]));

    expect($assignment?->courier->id)->toBe($courier->id)
        ->and($assignment->strategy)->toBe($strategy)
        ->and($assignment->quote->id)->toBe($carrier);
})->with([
    'recommended' => ['recommended', 'prepaid', '43'],
    'cheapest' => ['cheapest', 'prepaid', '51'],
    'fastest prepaid' => ['fastest', 'prepaid', '77'],
    'fastest COD skips non-COD carriers' => ['fastest', 'cod', '43'],
    'preferred list' => ['preferred_list', 'prepaid', '51'],
]);

test('no matching rule falls back to the default courier', function () {
    CourierRule::factory()->withConditions(['zones' => ['ne_jk']])->create();

    $assignment = app(CourierAssignmentService::class)->resolve(assignmentContext());

    expect($assignment?->courier->id)->toBe($this->default->id)
        ->and($assignment->rule)->toBeNull()
        ->and($assignment->strategy)->toBe('recommended')
        ->and($assignment->quote->id)->toBe('11');
});

test('a rule courier that cannot serve the route falls back to the default courier', function () {
    // A Shiprocket courier without credentials fails its serviceability call.
    $broken = Courier::factory()->shiprocket(null)->create();
    config(['logistics.shiprocket.email' => null]);
    CourierRule::factory()->for($broken, 'courier')->create();

    $assignment = app(CourierAssignmentService::class)->resolve(assignmentContext());

    expect($assignment?->courier->id)->toBe($this->default->id);
});

test('a COD shipment skips couriers that do not collect cash', function () {
    $prepaidOnly = Courier::factory()->withoutCod()->create();
    CourierRule::factory()->for($prepaidOnly, 'courier')->create();

    expect(app(CourierAssignmentService::class)->resolve(assignmentContext())?->courier->id)->toBe($this->default->id)
        ->and(app(CourierAssignmentService::class)->resolve(assignmentContext(['payment' => 'prepaid']))?->courier->id)->toBe($prepaidOnly->id);
});

test('nothing serviceable resolves to no assignment', function () {
    fakeCourier()->quote = new ServiceabilityResult([new CarrierQuote('5', 'Prepaid only', Money::of('40'), Money::zero(), 3, false)]);

    expect(app(CourierAssignmentService::class)->resolve(assignmentContext()))->toBeNull()
        ->and(app(CourierAssignmentService::class)->resolve(assignmentContext(['payment' => 'prepaid', 'destination' => '110002']))?->quote->id)->toBe('5');
});
