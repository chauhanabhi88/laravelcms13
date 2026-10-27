<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Product\Models\ProductVariant;
use Modules\Storefront\Support\StorefrontUrls;
use Modules\Storefront\ViewModels\ProductPageViewModel;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

afterEach(function () {
    pdpCleanupImages();
});

test('the payload lists the axes and every enabled variant with its options', function () {
    ['product' => $product, 'catalog' => $catalog] = pdpProduct();
    $payload = pdpPage($product)->payload();

    expect(collect($payload['axes'])->pluck('attributeId')->sort()->values()->all())->toBe(collect([$catalog['size'], $catalog['colour']])->sort()->values()->all())
        ->and($payload['variants'])->toHaveCount(3)
        ->and($payload['defaultVariantId'])->toBeNull();

    $sizeAxis = collect($payload['axes'])->firstWhere('attributeId', $catalog['size']);
    expect(collect($sizeAxis['options'])->pluck('label')->all())->toBe(['S', 'M', 'L'])
        ->and($sizeAxis['type'])->toBe(ProductPageViewModel::AXIS_BUTTON);

    $colourAxis = collect($payload['axes'])->firstWhere('attributeId', $catalog['colour']);
    expect($colourAxis['type'])->toBe(ProductPageViewModel::AXIS_SWATCH)
        ->and($payload['axes'][0]['attributeId'])->toBe($catalog['colour']);

    $small = collect($payload['variants'])->firstWhere('price', '499.00');
    expect((array) $small['options'])->toBe([
        (string) min($catalog['size'], $catalog['colour']) => (string) ($catalog['size'] < $catalog['colour'] ? $catalog['options']['S'] : $catalog['options']['Red']),
        (string) max($catalog['size'], $catalog['colour']) => (string) ($catalog['size'] < $catalog['colour'] ? $catalog['options']['Red'] : $catalog['options']['S']),
    ])
        ->and($small['mrp'])->toBe('999.00')
        ->and($small['priceText'])->toBe('₹499')
        ->and($small['discount'])->toBe(50);
});

test('available is capped and the real stock never leaves the server', function () {
    ['product' => $product] = pdpProduct();
    $payload = pdpPage($product)->payload();
    $available = collect($payload['variants'])->pluck('available', 'price')->all();

    expect($available)->toBe(['499.00' => 3, '549.00' => 10, '599.00' => 0]);

    foreach ($payload['variants'] as $variant) {
        expect($variant)->not->toHaveKeys(['stock', 'reserved']);
    }

    $html = $this->get(StorefrontUrls::product($product->slug, $product->id))->assertSuccessful()->getContent();
    expect($html)->not->toContain('"available":25')->not->toContain('"stock"');
});

test('reserved units are not available and disabled variants are left out', function () {
    ['product' => $product] = pdpProduct();
    $medium = ProductVariant::query()->where('product_id', $product->id)->where('price', '549.00')->first();
    DB::table('product_variant')->where('id', $medium->id)->update(['reserved' => 24]);
    DB::table('product_variant')->where('product_id', $product->id)->where('price', '599.00')->update(['status' => 2]);

    $payload = pdpPage($product)->payload();

    expect($payload['variants'])->toHaveCount(2)
        ->and(collect($payload['variants'])->firstWhere('id', $medium->id)['available'])->toBe(1);
});

test('?variant= preselects that variant, its price and the low-stock message', function () {
    ['product' => $product] = pdpProduct();
    $small = ProductVariant::query()->where('product_id', $product->id)->where('price', '499.00')->first();

    $page = pdpPage($product, $small->id);

    expect($page->payload()['defaultVariantId'])->toBe($small->id)
        ->and($page->displayPrice()->toDecimal())->toBe('499.00')
        ->and($page->lowStockCount())->toBe(3);

    $this->get(StorefrontUrls::product($product->slug, $product->id).'?variant='.$small->id)
        ->assertSuccessful()
        ->assertSee('Only 3 left')
        ->assertSee($small->sku);
});

test('a variant id of another product is ignored', function () {
    ['product' => $product] = pdpProduct();
    ['product' => $other] = pdpProduct();
    $foreign = ProductVariant::query()->where('product_id', $other->id)->value('id');

    expect(pdpPage($product, (int) $foreign)->selectedVariant)->toBeNull();
});

test('a single-variant product has no picker and its variant is selected', function () {
    $product = storefrontProduct(['name' => 'Steel Bottle'], ['price' => '299.00', 'mrp' => '399.00', 'stock' => 40]);
    $page = pdpPage($product);

    expect($page->hasPicker())->toBeFalse()
        ->and($page->selectedVariant)->not->toBeNull()
        ->and($page->payload()['axes'])->toBe([])
        ->and($page->payload()['variants'][0]['available'])->toBe(10);

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertDontSee('role="radiogroup"', false);
});

test('colour images form their own set and the variant points at it', function () {
    $general = pdpImageFile();
    $red = pdpImageFile();
    $missing = 'missing-'.uniqid().'.jpg';

    ['product' => $product, 'catalog' => $catalog] = pdpProduct();
    DB::table('product_image')->where('product_id', $product->id)->delete();
    DB::table('product_image')->insert([
        ['product_id' => $product->id, 'attribute_option_id' => null, 'path' => $general, 'position' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['product_id' => $product->id, 'attribute_option_id' => $catalog['options']['Red'], 'path' => $red, 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['product_id' => $product->id, 'attribute_option_id' => null, 'path' => $missing, 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $payload = pdpPage($product)->payload();
    $images = (array) $payload['images'];

    expect(array_map('strval', array_keys($images)))->toBe(['default', (string) $catalog['options']['Red']])
        ->and($images['default'])->toHaveCount(1)
        ->and($images['default'][0]['src'])->toContain($general)
        ->and(collect($payload['variants'])->firstWhere('price', '499.00')['imagesKey'])->toBe((string) $catalog['options']['Red'])
        ->and(collect($payload['variants'])->firstWhere('price', '599.00')['imagesKey'])->toBeNull();

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertSee($general)
        ->assertSee('fetchpriority="high"', false)
        ->assertDontSee($missing);
})->skip(fn () => ! function_exists('imagecreatetruecolor'), 'GD is required to write the test images');

test('specifications, highlights, manufacturer details and the return policy come from the product and category', function () {
    ['product' => $product] = pdpProduct(overrides: [
        'manufacturerDetails' => ['name' => 'Acme Textiles', 'address' => 'Jaipur, Rajasthan'],
    ]);
    $page = pdpPage($product);

    expect($page->specs)->toBe([['name' => 'Fabric', 'value' => 'Cotton']])
        ->and($page->highlights())->toBe(['Pure cotton', 'Machine wash'])
        ->and($page->manufacturerRows()[0])->toBe(['label' => 'Manufacturer', 'value' => 'Acme Textiles, Jaipur, Rajasthan'])
        ->and($page->returnPolicy->summary())->toContain('-day');

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertSee('Cotton')
        ->assertSee('Machine wash')
        ->assertSee('Acme Textiles');
});

test('the availability endpoint returns fresh capped availability without images', function () {
    ['product' => $product] = pdpProduct();
    DB::table('product_variant')->where('product_id', $product->id)->where('price', '499.00')->update(['stock' => 1]);

    $response = $this->getJson(frontRoute('storefront.product.availability', [$product->id]))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(collect($response->json('variants'))->pluck('available', 'price')->all())->toBe(['499.00' => 1, '549.00' => 10, '599.00' => 0])
        ->and($response->json('variants.0'))->not->toHaveKeys(['stock', 'imagesKey']);

    DB::table('product')->where('id', $product->id)->update(['status' => 'paused']);
    $this->getJson(frontRoute('storefront.product.availability', [$product->id]))->assertNotFound();
});

test('the API returns the product page payload', function () {
    ['product' => $product, 'seller' => $seller] = pdpProduct();

    $this->getJson(route('storefront_api.product.show', ['id' => $product->id]))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', 'Printed Cotton Kurti')
        ->assertJsonPath('data.price', '499.00')
        ->assertJsonPath('data.seller.name', $seller->displayName())
        ->assertJsonPath('data.reviews.count', 0)
        ->assertJsonCount(3, 'data.variants')
        ->assertJsonMissingPath('data.variants.0.stock');

    DB::table('product')->where('id', $product->id)->update(['status' => 'blocked']);
    $this->getJson(route('storefront_api.product.show', ['id' => $product->id]))
        ->assertNotFound()
        ->assertJsonPath('success', false);
});
