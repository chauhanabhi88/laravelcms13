<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Support\CatalogCache;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Storefront\Contracts\DeliveryEstimator;
use Modules\Storefront\Support\PincodeMatrixDeliveryEstimator;
use Modules\Storefront\Support\StorefrontSettings;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    // Monday 3 Nov 2025; the default category dispatch SLA is rounded up to whole days.
    $this->travelTo(Carbon::parse('2025-11-03 10:00:00'));
    config(['storefront.delivery.range_days' => []]);

    $this->ka = sellerTestState('KA', 'Karnataka');
    $this->mh = sellerTestState('MH', 'Maharashtra');
    $this->as = sellerTestState('AS', 'Assam');

    $this->origin = DirectoryPincode::factory()->create(['pincode' => '560034', 'district' => 'Bengaluru Urban', 'state_id' => $this->ka, 'zone' => 'metro']);

    ['product' => $product] = pdpProduct();
    $this->product = $product;
    SellerWarehouse::factory()->create(['seller_id' => $product->seller_id, 'pincode' => '560034']);
    DB::table('catalog_category')->where('id', $product->catalog_category_id)->update(['dispatch_sla_hours' => 30]);
    app(CatalogCache::class)->flush();

    $this->estimate = fn (string $pincode, ?ProductVariant $variant = null) => app(DeliveryEstimator::class)->estimate(Product::query()->find($this->product->id), $variant, $pincode);
});

test('the relationship between warehouse and destination picks the transit bucket', function (string $pincode, string $district, string $state, string $zone, string $expected) {
    $states = ['KA' => $this->ka, 'MH' => $this->mh, 'AS' => $this->as];
    $destination = DirectoryPincode::factory()->create(['pincode' => $pincode, 'district' => $district, 'state_id' => $states[$state], 'zone' => $zone]);

    expect(app(PincodeMatrixDeliveryEstimator::class)->relationship($this->origin, $destination))->toBe($expected);
})->with([
    'same district' => ['560001', 'Bengaluru Urban', 'KA', 'metro', 'same_city'],
    'same state' => ['570001', 'Mysuru', 'KA', 'roi', 'same_state'],
    'metro to metro' => ['400001', 'Mumbai', 'MH', 'metro', 'metro_metro'],
    'special zone' => ['781001', 'Kamrup', 'AS', 'ne_jk', 'special'],
    'rest of India' => ['411001', 'Pune', 'MH', 'roi', 'roi'],
]);

test('special zone states can also come from the settings list', function () {
    config(['storefront.defaults.special_zone_state_ids' => (string) $this->as]);
    $pincode = DirectoryPincode::factory()->create(['pincode' => '781005', 'district' => 'Kamrup', 'state_id' => $this->as, 'zone' => 'roi']);

    expect(app(PincodeMatrixDeliveryEstimator::class)->relationship($this->origin, $pincode))->toBe('special');
});

test('the delivery date adds dispatch and transit days and skips Sundays', function () {
    DirectoryPincode::factory()->create(['pincode' => '570001', 'district' => 'Mysuru', 'state_id' => $this->ka]);

    // 30 h SLA → 2 days, same state → 3 days: 5 delivery days after Mon 3 Nov.
    $estimate = ($this->estimate)('570001');

    expect($estimate->serviceable)->toBeTrue()
        ->and($estimate->eddFrom->toDateString())->toBe('2025-11-08')
        ->and($estimate->eddTo->toDateString())->toBe('2025-11-08')
        ->and($estimate->message)->toBe('Estimated delivery by Sat, 8 Nov')
        ->and($estimate->toArray()['return_text'])->toContain('-day');

    // From Wednesday the same 5 days cross Sunday 9 Nov: Wed 5 → Tue 11.
    $this->travelTo(Carbon::parse('2025-11-05 09:00:00'));
    expect(($this->estimate)('570001')->eddFrom->toDateString())->toBe('2025-11-11');
});

test('special zones get a longer window shown as a range', function () {
    config(['storefront.delivery.range_days' => ['special' => 2]]);
    DirectoryPincode::factory()->create(['pincode' => '781001', 'district' => 'Kamrup', 'state_id' => $this->as, 'zone' => 'ne_jk']);

    // 2 dispatch + 8 transit = 10 delivery days → Fri 14 Nov; +2 → Mon 17 Nov (Sunday skipped).
    $estimate = ($this->estimate)('781001');

    expect($estimate->eddFrom->toDateString())->toBe('2025-11-14')
        ->and($estimate->eddTo->toDateString())->toBe('2025-11-17')
        ->and($estimate->eddText())->toBe('Fri, 14 Nov – Mon, 17 Nov');
});

test('transit days come from the settings JSON, falling back per bucket', function () {
    DB::table('settings')->where('name', 'storefront')->delete();
    DB::table('settings')->insert(['name' => 'storefront', 'value' => json_encode(['transit_days' => '{"same_state": 1, "roi": "x"}']), 'created_at' => now(), 'updated_at' => now()]);

    expect(StorefrontSettings::transitDays())->toBe(['same_city' => 2, 'same_state' => 1, 'metro_metro' => 4, 'roi' => 5, 'special' => 8]);
});

test('adding delivery days never lands on a Sunday', function () {
    $saturday = Carbon::parse('2025-11-08');

    expect(PincodeMatrixDeliveryEstimator::addDeliveryDays($saturday, 1)->toDateString())->toBe('2025-11-10')
        ->and(PincodeMatrixDeliveryEstimator::addDeliveryDays(Carbon::parse('2025-11-09'), 0)->toDateString())->toBe('2025-11-10')
        ->and(PincodeMatrixDeliveryEstimator::addDeliveryDays($saturday, 0)->toDateString())->toBe('2025-11-08');
});

test('cash on delivery needs the pincode, the product and the order value to allow it', function () {
    DirectoryPincode::factory()->create(['pincode' => '570001', 'district' => 'Mysuru', 'state_id' => $this->ka]);
    DirectoryPincode::factory()->withoutCod()->create(['pincode' => '570002', 'district' => 'Mysuru', 'state_id' => $this->ka]);
    $expensive = ProductVariant::query()->where('product_id', $this->product->id)->where('price', '599.00')->first();

    expect(($this->estimate)('570001')->codAvailable)->toBeTrue()
        ->and(($this->estimate)('570002')->codAvailable)->toBeFalse();

    config(['storefront.defaults.cod_max_value' => '550']);
    expect(($this->estimate)('570001')->codAvailable)->toBeTrue()
        ->and(($this->estimate)('570001', $expensive)->codAvailable)->toBeFalse();

    config(['storefront.defaults.cod_max_value' => '5000']);
    DB::table('product')->where('id', $this->product->id)->update(['is_cod_allowed' => 2]);
    expect(($this->estimate)('570001')->codAvailable)->toBeFalse()
        ->and(($this->estimate)('570001')->toArray()['cod_text'])->toBe('Cash on Delivery not available');
});

test('unknown and unserviceable pincodes are not serviceable', function () {
    DirectoryPincode::factory()->unserviceable()->create(['pincode' => '570003', 'district' => 'Mysuru', 'state_id' => $this->ka]);

    foreach (['999999', '570003'] as $pincode) {
        $estimate = ($this->estimate)($pincode);

        expect($estimate->serviceable)->toBeFalse()
            ->and($estimate->codAvailable)->toBeFalse()
            ->and($estimate->eddFrom)->toBeNull()
            ->and($estimate->message)->toBe('This pincode isn\'t serviceable yet. Try another address.');
    }
});

test('without a warehouse the destination alone decides (rest of India or special)', function () {
    SellerWarehouse::query()->where('seller_id', $this->product->seller_id)->delete();
    DirectoryPincode::factory()->create(['pincode' => '570001', 'district' => 'Mysuru', 'state_id' => $this->ka]);

    // 2 dispatch + 5 transit (roi) = 7 delivery days after Mon 3 Nov → Tue 11 Nov.
    expect(($this->estimate)('570001')->eddFrom->toDateString())->toBe('2025-11-11');
});
