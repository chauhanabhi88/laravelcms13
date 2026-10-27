<?php

use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Product\Exceptions\InvalidPricing;
use Modules\Product\Services\PricingService;
use Modules\Seller\Models\Seller;

beforeEach(function () {
    $this->pricing = app(PricingService::class);
    $this->category = CatalogCategory::factory()->create();
});

function pricingError(Closure $callback): ?InvalidPricing
{
    try {
        $callback();
    } catch (InvalidPricing $e) {
        return $e;
    }

    return null;
}

test('the selling price must be positive and within the MRP', function () {
    expect(pricingError(fn () => $this->pricing->validatePrices(Money::of('0'), Money::of('100'), $this->category)))->field->toBe('price')
        ->and(pricingError(fn () => $this->pricing->validatePrices(Money::of('10'), Money::of('0'), $this->category)))->field->toBe('mrp')
        ->and(pricingError(fn () => $this->pricing->validatePrices(Money::of('100.01'), Money::of('100'), $this->category, 'variants.3.')))->field->toBe('variants.3.price')
        ->and(pricingError(fn () => $this->pricing->validatePrices(Money::of('100'), Money::of('100'), $this->category)))->toBeNull();
});

test('the inherited category minimum selling price is enforced', function () {
    $root = CatalogCategory::factory()->create(['min_selling_price' => '99.00']);
    $leaf = CatalogCategory::factory()->child($root)->create();

    expect(pricingError(fn () => $this->pricing->validatePrices(Money::of('98.99'), Money::of('199'), $leaf->fresh())))
        ->field->toBe('price')
        ->getMessage()->toContain('₹99');

    expect(pricingError(fn () => $this->pricing->validatePrices(Money::of('99'), Money::of('199'), $leaf->fresh())))->toBeNull();
});

test('the MRP may be at most the configured multiple of the price', function () {
    expect(pricingError(fn () => $this->pricing->validatePrices(Money::of('100'), Money::of('1000'), $this->category)))->toBeNull()
        ->and(pricingError(fn () => $this->pricing->validatePrices(Money::of('100'), Money::of('1000.01'), $this->category)))->field->toBe('mrp');

    config(['product.mrp_multiplier_guard' => 3]);

    expect(pricingError(fn () => $this->pricing->validatePrices(Money::of('100'), Money::of('301'), $this->category)))->field->toBe('mrp');
});

test('the discount is a whole percent rounded down', function (string $price, string $mrp, int $expected) {
    expect($this->pricing->discountPercent(Money::of($price), Money::of($mrp)))->toBe($expected);
})->with([
    'card example' => ['249', '999', 75],
    'exact half' => ['500', '1000', 50],
    'just under a percent' => ['990.01', '1000', 0],
    'no discount' => ['999', '999', 0],
    'price above MRP' => ['1000', '999', 0],
    'zero MRP' => ['10', '0', 0],
]);

test('the tax breakup adds up exactly to the inclusive amount', function (string $inclusive, string $gst, string $taxable, string $cgst, string $sgst) {
    $intra = $this->pricing->taxBreakup(Money::of($inclusive), $gst, false);

    expect($intra['taxable']->toDecimal())->toBe($taxable)
        ->and($intra['cgst']->toDecimal())->toBe($cgst)
        ->and($intra['sgst']->toDecimal())->toBe($sgst)
        ->and($intra['igst']->isZero())->toBeTrue()
        ->and($intra['taxable']->plus($intra['cgst'])->plus($intra['sgst'])->toDecimal())->toBe(Money::of($inclusive)->toDecimal());

    $inter = $this->pricing->taxBreakup(Money::of($inclusive), $gst, true);

    expect($inter['taxable']->toDecimal())->toBe($taxable)
        ->and($inter['cgst']->isZero())->toBeTrue()
        ->and($inter['sgst']->isZero())->toBeTrue()
        ->and($inter['igst']->equals($intra['tax']))->toBeTrue()
        ->and($inter['taxable']->plus($inter['igst'])->toDecimal())->toBe(Money::of($inclusive)->toDecimal());
})->with([
    'odd paisa goes to CGST' => ['100', '18', '84.75', '7.63', '7.62'],
    'round taxable' => ['105', '5', '100.00', '2.50', '2.50'],
    'half up' => ['499', '12', '445.54', '26.73', '26.73'],
    'decimal rate' => ['1000', '0.25', '997.51', '1.25', '1.24'],
    'zero rate' => ['250', '0', '250.00', '0.00', '0.00'],
    'one paisa' => ['0.01', '5', '0.01', '0.00', '0.00'],
]);

test('the settlement estimate deducts the fee calculator fees', function () {
    $seller = Seller::factory()->active()->create();

    $estimate = $this->pricing->estimateSellerSettlement(Money::of('499'), $this->category, $seller);
    expect($estimate->net->toDecimal())->toBe('499.00')
        ->and($estimate->totalFees()->isZero())->toBeTrue();

    app()->instance(FeeCalculator::class, new class implements FeeCalculator
    {
        public function fees(Money $price, CatalogCategory $category, Seller $seller): array
        {
            return ['commission' => $price->percent('10'), 'tcs' => Money::of('2.50')];
        }
    });

    $estimate = app(PricingService::class)->estimateSellerSettlement(Money::of('499'), $this->category, $seller);
    expect($estimate->toArray())->toMatchArray(['total_fees' => '52.40', 'net' => '446.60']);
});
