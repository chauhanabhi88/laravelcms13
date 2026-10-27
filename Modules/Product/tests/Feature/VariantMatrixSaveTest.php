<?php

use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductFactoryService;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    $this->catalog = productCatalog();
    [$this->seller, $this->owner] = sellerWithOwner();
    $this->actingAs($this->owner, 'seller');
    $this->product = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog, ['variants' => []]));
});

/**
 * @return array<string, mixed>
 */
function matrixRow(array $catalog, string $size, string $colour, string $sku, array $overrides = []): array
{
    return $overrides + [
        'options' => [$catalog['size'] => $catalog['options'][$size], $catalog['colour'] => $catalog['options'][$colour]],
        'seller_sku' => $sku,
        'mrp' => '999',
        'price' => '499',
        'stock' => 5,
        'weight_grams' => 300,
    ];
}

test('every combination becomes one variant with a unique combination key', function () {
    $rows = [];
    foreach (['S', 'M', 'L'] as $size) {
        foreach (['Red', 'Blue'] as $colour) {
            $rows[] = matrixRow($this->catalog, $size, $colour, "K-{$size}-{$colour}");
        }
    }

    $this->postJson(route('seller.listing.variants.save', [$this->product->id]), ['variants' => $rows])->assertSuccessful();

    $keys = ProductVariant::query()->where('product_id', $this->product->id)->pluck('combination_key');
    expect($keys)->toHaveCount(6)->and($keys->unique())->toHaveCount(6);
});

test('a duplicate combination is refused on its row', function () {
    $this->postJson(route('seller.listing.variants.save', [$this->product->id]), ['variants' => [
        matrixRow($this->catalog, 'S', 'Red', 'K-1'),
        matrixRow($this->catalog, 'S', 'Red', 'K-2'),
    ]])->assertUnprocessable()->assertJsonValidationErrors('variants.1.options');
});

test('regenerating keeps existing variants and their values, and removes dropped ones', function () {
    $this->postJson(route('seller.listing.variants.save', [$this->product->id]), ['variants' => [
        matrixRow($this->catalog, 'S', 'Red', 'K-S-RED', ['stock' => 7]),
        matrixRow($this->catalog, 'M', 'Red', 'K-M-RED'),
    ]])->assertSuccessful();

    $small = ProductVariant::query()->where('seller_sku', 'K-S-RED')->sole();
    $small->update(['length_cm' => '30.0']);

    $this->postJson(route('seller.listing.variants.save', [$this->product->id]), ['variants' => [
        matrixRow($this->catalog, 'S', 'Red', 'K-S-RED', ['id' => $small->id, 'stock' => 7]),
        matrixRow($this->catalog, 'L', 'Red', 'K-L-RED'),
    ]])->assertSuccessful();

    $variants = ProductVariant::query()->where('product_id', $this->product->id)->orderBy('position')->get();
    expect($variants->pluck('seller_sku')->all())->toBe(['K-S-RED', 'K-L-RED'])
        ->and($variants->first()->id)->toBe($small->id)
        ->and($variants->first()->stock)->toBe(7)
        ->and($variants->first()->length_cm)->toBe('30.0');
});

test('more than the maximum number of variants is rejected', function () {
    config(['product.max_variants' => 3]);

    $rows = [];
    foreach (['S', 'M'] as $size) {
        foreach (['Red', 'Blue'] as $colour) {
            $rows[] = matrixRow($this->catalog, $size, $colour, "K-{$size}-{$colour}");
        }
    }

    $this->postJson(route('seller.listing.variants.save', [$this->product->id]), ['variants' => $rows])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('variants');

    expect(ProductVariant::query()->where('product_id', $this->product->id)->count())->toBe(0);
});

test('a price above the MRP is rejected on that row', function () {
    $this->postJson(route('seller.listing.variants.save', [$this->product->id]), ['variants' => [
        matrixRow($this->catalog, 'S', 'Red', 'K-S-RED'),
        matrixRow($this->catalog, 'M', 'Red', 'K-M-RED', ['price' => '1200']),
    ]])->assertUnprocessable()->assertJsonValidationErrors('variants.1.price');
});

test('a seller SKU used on another product is rejected', function () {
    app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog));

    $this->postJson(route('seller.listing.variants.save', [$this->product->id]), ['variants' => [
        matrixRow($this->catalog, 'S', 'Red', 'KRT-S-RED'),
    ]])->assertUnprocessable()->assertJsonValidationErrors('variants.0.seller_sku');
});
