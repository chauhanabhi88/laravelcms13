<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogSizeChart;
use Modules\Catalog\Support\CatalogCache;
use Modules\Customer\Models\Customer;
use Modules\Product\Models\Product;
use Modules\Seller\Models\SellerUser;
use Modules\Storefront\Contracts\CartEntryPoint;
use Modules\Storefront\Models\CustomerRecentView;
use Modules\Storefront\Support\NullCartEntryPoint;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

test('a live product renders with its name, price, canonical link and structured data', function () {
    ['product' => $product] = pdpProduct();
    $url = StorefrontUrls::product($product->slug, $product->id);

    expect($url)->toEndWith('/p/'.$product->slug.'-'.$product->id);

    $this->get($url)
        ->assertSuccessful()
        ->assertViewIs('storefront::frontend.product.show')
        ->assertSee('Printed Cotton Kurti')
        ->assertSee('₹499')
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('window.__pdp', false)
        ->assertSee('No ratings yet')
        ->assertDontSee('noindex');
});

test('a wrong or outdated slug is redirected permanently to the canonical url, keeping the query', function () {
    ['product' => $product] = pdpProduct();
    $canonical = StorefrontUrls::product($product->slug, $product->id);

    $this->get(url('p/old-name-'.$product->id).'?variant=7')
        ->assertStatus(301)
        ->assertRedirect($canonical.'?variant=7');
});

test('hidden products are a 404', function (string $state) {
    ['product' => $product] = pdpProduct();
    $url = StorefrontUrls::product($product->slug, $product->id);

    match ($state) {
        'blocked', 'paused' => DB::table('product')->where('id', $product->id)->update(['status' => $state]),
        'seller suspended' => DB::table('product')->where('id', $product->id)->update(['seller_active' => 2]),
        'deleted' => Product::query()->whereKey($product->id)->delete(),
    };

    $this->get($url)->assertNotFound();
})->with(['blocked', 'paused', 'seller suspended', 'deleted']);

test('unknown ids and malformed urls are a 404', function () {
    $this->get(url('p/no-such-product-999999999'))->assertNotFound();
    $this->get(url('p/no-id-here'))->assertNotFound();
});

test('the 404 for a hidden product suggests popular products from its category', function () {
    ['product' => $product, 'catalog' => $catalog] = pdpProduct();
    storefrontProduct(['name' => 'Neighbour Kurti'], [], $catalog['category']);
    DB::table('product')->where('id', $product->id)->update(['status' => 'paused']);

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertNotFound()
        ->assertSee('You may also like')
        ->assertSee('Neighbour Kurti');
});

test('admins with the product permission and the owning seller can preview a hidden product', function () {
    ['product' => $product, 'seller' => $seller] = pdpProduct();
    DB::table('product')->where('id', $product->id)->update(['status' => 'paused']);
    $url = StorefrontUrls::product($product->slug, $product->id).'?preview=1';

    $this->actingAs(productAdmin(['admin.product.show']), 'web')
        ->get($url)
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('Preview');

    auth('web')->logout();

    $owner = SellerUser::factory()->create(['seller_id' => $seller->id]);
    $this->actingAs($owner, 'seller')->get($url)->assertSuccessful();
});

test('preview is refused to everyone else', function () {
    ['product' => $product] = pdpProduct();
    DB::table('product')->where('id', $product->id)->update(['status' => 'paused']);
    $url = StorefrontUrls::product($product->slug, $product->id).'?preview=1';

    $this->get($url)->assertNotFound();
    $this->actingAs(productAdmin(['admin.product.index']), 'web')->get($url)->assertNotFound();

    auth('web')->logout();
    ['seller' => $otherSeller] = pdpProduct();
    $stranger = SellerUser::factory()->create(['seller_id' => $otherSeller->id]);
    $this->actingAs($stranger, 'seller')->get($url)->assertNotFound();
});

test('a signed-in customer\'s view is recorded as recently viewed', function () {
    ['product' => $product] = pdpProduct();
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful();

    expect(CustomerRecentView::query()->where('customer_id', $customer->id)->pluck('product_id')->all())->toBe([$product->id]);
});

test('the null cart hides the buy buttons outside local environments', function () {
    // The Cart module (M10) binds the real entry point; this checks the Storefront's fallback.
    app()->instance(CartEntryPoint::class, new NullCartEntryPoint);
    ['product' => $product] = pdpProduct();

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertDontSee('pdp-cart-form', false)
        ->assertDontSee('Cart and checkout are coming soon.');
});

test('a bound cart entry point shows add to cart and buy now posting the variant', function () {
    app()->instance(CartEntryPoint::class, new class implements CartEntryPoint
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function addUrl(): ?string
        {
            return 'https://shop.test/cart/add';
        }

        public function buyNowUrl(): ?string
        {
            return 'https://shop.test/checkout/buy-now';
        }
    });
    ['product' => $product] = pdpProduct();

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertSee('action="https://shop.test/cart/add"', false)
        ->assertSee('formaction="https://shop.test/checkout/buy-now"', false)
        ->assertSee('name="variant_id"', false)
        ->assertSee('Add to Cart')
        ->assertSee('Buy Now');
});

test('the size chart of the category opens from the size axis', function () {
    $chart = CatalogSizeChart::factory()->create(['name' => 'Kurti chart', 'columns' => ['Bust', 'Length'], 'rows' => [['size' => 'S', 'values' => ['34', '44']]]]);
    ['product' => $product, 'catalog' => $catalog] = pdpProduct();
    DB::table('catalog_category')->where('id', $catalog['category']->id)->update(['size_chart_id' => $chart->id]);
    app(CatalogCache::class)->flush();

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertSee('Size chart')
        ->assertSee('Bust');
});

test('resellers see Share & Earn, other visitors do not', function () {
    ['product' => $product] = pdpProduct();
    $url = StorefrontUrls::product($product->slug, $product->id);

    $this->get($url)->assertDontSee('Share &amp; Earn', false);

    $reseller = Customer::factory()->create();
    $reseller->setRawAttributes(array_merge($reseller->getAttributes(), ['is_reseller' => 1]));

    $this->actingAs($reseller, 'customer')->get($url)->assertSee('Share &amp; Earn', false);
});
