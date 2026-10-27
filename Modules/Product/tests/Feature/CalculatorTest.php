<?php

use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Product\Services\EarningsCalculator;
use Modules\Seller\Models\Seller;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    $this->catalog = productCatalog(['gst_percent' => '5.00']);
    [$this->seller, $this->owner] = sellerWithOwner();
});

test('with the default fees: net = price − shipping − TCS on taxable − TDS on the sale', function () {
    $estimate = app(EarningsCalculator::class)->estimate(Money::of('1050'), $this->catalog['category'], $this->seller, 400);

    // Taxable 1000.00 at 5 % GST; shipping slab ≤ 500 g = 45; TCS 1 % of 1000 = 10; TDS 0.1 % of 1050 = 1.05.
    expect(EarningsCalculator::toArray($estimate))->toMatchArray([
        'price' => '1050.00',
        'gst_percent' => '5.00',
        'taxable' => '1000.00',
        'gst' => '50.00',
        'commission' => '0.00',
        'shipping' => '45.00',
        'tcs' => '10.00',
        'tds' => '1.05',
        'total_deductions' => '56.05',
        'net' => '993.95',
    ]);
});

test('fees from the FeeCalculator are deducted and take precedence over the estimates', function () {
    app()->instance(FeeCalculator::class, new class implements FeeCalculator
    {
        public function fees(Money $price, CatalogCategory $category, Seller $seller): array
        {
            return [
                'commission' => $price->percent('10'),
                'platform_fee' => Money::of('5'),
                'logistics' => Money::of('60'),
                'tcs' => Money::of('9'),
                'tds' => Money::zero(),
            ];
        }
    });

    $estimate = app(EarningsCalculator::class)->estimate(Money::of('1050'), $this->catalog['category'], $this->seller, 400);

    // 1050 − 105 commission − 5 platform − 60 logistics − 9 TCS − 1.05 TDS (estimated: the fake reports none).
    expect($estimate['net']->toDecimal())->toBe('869.95')
        ->and($estimate['total_deductions']->toDecimal())->toBe('180.05');
});

test('heavier parcels step up the shipping estimate', function () {
    $calculator = app(EarningsCalculator::class);

    expect($calculator->shippingEstimate(500)->toDecimal())->toBe('45.00')
        ->and($calculator->shippingEstimate(501)->toDecimal())->toBe('65.00')
        ->and($calculator->shippingEstimate(2000)->toDecimal())->toBe('95.00')
        ->and($calculator->shippingEstimate(3500)->toDecimal())->toBe('175.00');
});

test('the calculator page and JSON endpoint', function () {
    $this->actingAs($this->owner, 'seller');

    $this->get(route('seller.calculator'))->assertSuccessful()->assertSee(trans('product::seller.calculator.title'));

    $this->postJson(route('seller.calculator.calculate'), [
        'catalog_category_id' => $this->catalog['category']->id,
        'price' => '1050',
        'weight_grams' => 400,
        'gst_percent' => '12',
    ])->assertSuccessful()
        ->assertJsonPath('data.gst_percent', '12.00')
        ->assertJsonPath('data.taxable', '937.50');

    $this->postJson(route('seller.calculator.calculate'), ['catalog_category_id' => $this->catalog['root']->id, 'price' => '100', 'weight_grams' => 100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('catalog_category_id');

    $this->postJson(route('seller.calculator.calculate'), ['catalog_category_id' => $this->catalog['category']->id, 'price' => 'abc', 'weight_grams' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price', 'weight_grams']);
});
