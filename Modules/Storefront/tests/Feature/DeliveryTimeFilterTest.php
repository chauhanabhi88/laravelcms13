<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Support\CatalogCache;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Services\Search\FacetService;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Support\DeliveryPincode;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    config(['storefront.delivery.range_days' => []]);

    $ka = sellerTestState('KA', 'Karnataka');
    $mh = sellerTestState('MH', 'Maharashtra');

    // Destination: Bengaluru (metro).
    DirectoryPincode::factory()->create(['pincode' => '560001', 'district' => 'Bengaluru Urban', 'state_id' => $ka, 'zone' => 'metro']);
    DirectoryPincode::factory()->create(['pincode' => '560034', 'district' => 'Bengaluru Urban', 'state_id' => $ka, 'zone' => 'metro']);
    DirectoryPincode::factory()->create(['pincode' => '400001', 'district' => 'Mumbai', 'state_id' => $mh, 'zone' => 'metro']);
    DirectoryPincode::factory()->create(['pincode' => '110001', 'district' => 'New Delhi', 'is_serviceable' => config('core.no')]);

    // 24 h dispatch SLA = 1 day.
    $this->leaf = CatalogCategory::factory()->create(['name' => 'Kurtis', 'dispatch_sla_hours' => 24]);
    app(CatalogCache::class)->flush();

    $product = function (?string $warehousePincode): int {
        $seller = Seller::factory()->active()->create();

        if ($warehousePincode !== null) {
            SellerWarehouse::factory()->create(['seller_id' => $seller->id, 'pincode' => $warehousePincode]);
        }

        return storefrontProduct(['seller_id' => $seller->id], [], $this->leaf)->id;
    };

    $this->local = $product('560034');   // same city: 1 + 2 = 3 days
    $this->mumbai = $product('400001');  // metro to metro: 1 + 4 = 5 days
    $this->far = $product(null);         // rest of India: 1 + 5 = 6 days

    $this->query = fn (array $values = []) => new ListingQuery(...$values + ['categoryId' => $this->leaf->id, 'categoryPath' => $this->leaf->fresh()->path]);
    $this->ids = fn (ListingQuery $query): array => collect(app(ProductSearchEngine::class)->run($query)->productIds)->sort()->values()->all();
});

test('the delivery filter keeps products that arrive within the chosen days', function () {
    expect(($this->ids)(($this->query)(['deliveryPincode' => '560001', 'deliveryDays' => 3])))->toBe([$this->local])
        ->and(($this->ids)(($this->query)(['deliveryPincode' => '560001', 'deliveryDays' => 5])))->toBe([$this->local, $this->mumbai])
        ->and(($this->ids)(($this->query)(['deliveryPincode' => '560001'])))->toBe([$this->local, $this->mumbai, $this->far]);
});

test('a slower category dispatch pushes products out of the window', function () {
    DB::table('catalog_category')->where('id', $this->leaf->id)->update(['dispatch_sla_hours' => 72]);
    app(CatalogCache::class)->flush();
    cache()->flush();

    // 3 dispatch days + 2 transit days = 5.
    expect(($this->ids)(($this->query)(['deliveryPincode' => '560001', 'deliveryDays' => 3])))->toBe([])
        ->and(($this->ids)(($this->query)(['deliveryPincode' => '560001', 'deliveryDays' => 5])))->toBe([$this->local]);
});

test('an unserviceable or unknown pincode matches nothing', function (string $pincode) {
    expect(($this->ids)(($this->query)(['deliveryPincode' => $pincode, 'deliveryDays' => 5])))->toBe([]);
})->with(['not serviceable' => '110001', 'not in the master' => '799999']);

test('the facet counts each window once the pincode is known', function () {
    $facets = app(FacetService::class)->facets(($this->query)(['deliveryPincode' => '560001', 'deliveryDays' => 3]));

    // Counted without the delivery filter itself, so the other window stays visible.
    expect(collect($facets['delivery'])->pluck('count', 'value')->all())->toBe([3 => 1, 5 => 2])
        ->and($facets['cod'])->toBe(1)
        ->and(app(FacetService::class)->facets(($this->query)())['delivery'])->toBe([]);
});

test('the request parameter only applies with a known pincode', function () {
    $request = Request::create('/c/kurtis', 'GET', ['delivery' => '3']);

    expect(ListingQuery::fromRequest($request)->deliveryDays)->toBeNull();

    $request->setLaravelSession(app('session.store'));
    $request->session()->put(DeliveryPincode::SESSION_KEY, '560001');
    $query = ListingQuery::fromRequest($request);

    expect($query->deliveryDays)->toBe(3)
        ->and($query->deliveryPincode)->toBe('560001')
        ->and($query->urlParams())->toBe(['delivery' => '3'])
        // Days outside the configured buckets are ignored.
        ->and(ListingQuery::fromRequest(tap(Request::create('/c/kurtis', 'GET', ['delivery' => '4']), fn (Request $r) => $r->setLaravelSession(app('session.store'))))->deliveryDays)->toBeNull();
});

test('the pincode alone does not change the cache key of an unfiltered listing', function () {
    expect(($this->query)(['deliveryPincode' => '560001'])->cacheKey())->toBe(($this->query)()->cacheKey())
        ->and(($this->query)(['deliveryPincode' => '560001', 'deliveryDays' => 3])->cacheKey())->not->toBe(($this->query)(['deliveryPincode' => '400001', 'deliveryDays' => 3])->cacheKey());
});

test('the listing page offers the filter and shows it as a removable chip', function () {
    $url = StorefrontUrls::category($this->leaf->slug);

    $this->get($url)->assertOk()->assertDontSee(trans('storefront::storefront.filters.delivery'));

    $this->withSession([DeliveryPincode::SESSION_KEY => '560001'])
        ->get($url.'?delivery=3')
        ->assertOk()
        ->assertSee(trans('storefront::storefront.filters.delivery'))
        ->assertSee(trans('storefront::storefront.filters.delivery_value', ['days' => 3]))
        ->assertSee(e($url), false);
});

test('the products API accepts a pincode parameter', function () {
    $this->getJson('/api/v1/products?category='.$this->leaf->slug.'&delivery=3&pincode=560001')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $this->local)
        ->assertJsonPath('meta.filters.delivery', '3');
});
