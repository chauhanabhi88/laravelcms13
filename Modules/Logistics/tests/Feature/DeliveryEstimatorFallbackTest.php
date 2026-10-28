<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Support\LogisticsDeliveryEstimator;
use Modules\Product\Models\Product;
use Modules\Storefront\Contracts\DeliveryEstimator;
use Modules\Storefront\Support\PincodeMatrixDeliveryEstimator;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    logisticsSetup();
    config(['core.integrations.retries' => 0]);

    $this->variant = cartVariant(['weight_grams' => 300], orderSeller());
    $this->product = Product::query()->findOrFail($this->variant->product_id);
    $this->pincode = orderPincode()->pincode;
});

function useShiprocketAsDefault(): Courier
{
    $courier = Courier::factory()->shiprocket()->create(['code' => 'sr_live']);
    logisticsSettings(['default_courier' => 'sr_live']);

    return $courier;
}

function matrixEstimate(Product $product, $variant, string $pincode): array
{
    return app(PincodeMatrixDeliveryEstimator::class)->estimate($product, $variant, $pincode)->toArray();
}

test('the storefront estimator is the logistics one', function () {
    expect(app(DeliveryEstimator::class))->toBeInstanceOf(LogisticsDeliveryEstimator::class);
});

test('an API timeout falls back to the pincode matrix', function () {
    useShiprocketAsDefault();
    Http::swap(new Factory);
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out after 1500 milliseconds');
    });

    $estimate = app(DeliveryEstimator::class)->estimate($this->product, $this->variant, $this->pincode);

    expect($estimate->toArray())->toEqual(matrixEstimate($this->product, $this->variant, $this->pincode));
});

test('live serviceability gives the EDD of the recommended carrier and is cached', function () {
    useShiprocketAsDefault();
    Http::swap(new Factory);
    Http::fake([
        '*/v1/external/auth/login' => Http::response(shiprocketFixture('auth')),
        '*/v1/external/courier/serviceability*' => Http::response(shiprocketFixture('serviceability')),
    ]);

    $estimator = app(DeliveryEstimator::class);
    $estimate = $estimator->estimate($this->product, $this->variant, $this->pincode);
    $estimator->estimate($this->product, $this->variant, $this->pincode);

    $dispatchDays = (int) ceil(app(CategoryDefaults::class)->for($this->product->catalog_category_id)['dispatch_sla_hours'] / 24);
    $expected = PincodeMatrixDeliveryEstimator::addDeliveryDays(today(), $dispatchDays + 4);

    expect($estimate->serviceable)->toBeTrue()
        ->and($estimate->codAvailable)->toBeTrue()
        ->and($estimate->eddFrom?->toDateString())->toBe($expected->toDateString())
        ->and(collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'serviceability')))->toHaveCount(1);
});

test('an unserviceable pincode fails fast without calling the courier', function () {
    useShiprocketAsDefault();
    Http::swap(new Factory);
    Http::fake();
    $pincode = orderPincode(serviceable: false)->pincode;

    $estimate = app(DeliveryEstimator::class)->estimate($this->product, $this->variant, $pincode);

    expect($estimate->serviceable)->toBeFalse();
    Http::assertNothingSent();
});

test('without API credentials or with live checks off the matrix is used', function (string $case) {
    if ($case === 'no credentials') {
        logisticsSettings(['default_courier' => 'shiprocket']);
    } else {
        useShiprocketAsDefault();
        logisticsSettings(['live_serviceability' => 2]);
    }

    Http::swap(new Factory);
    Http::fake();

    $estimate = app(DeliveryEstimator::class)->estimate($this->product, $this->variant, $this->pincode);

    expect($estimate->toArray())->toEqual(matrixEstimate($this->product, $this->variant, $this->pincode));
    Http::assertNothingSent();
})->with(['no credentials', 'live off']);
