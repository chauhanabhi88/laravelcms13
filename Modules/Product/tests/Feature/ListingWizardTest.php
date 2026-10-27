<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductModerationService;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    Storage::fake('public');
    $this->catalog = productCatalog();
    [$this->seller, $this->owner] = sellerWithOwner();
    $this->actingAs($this->owner, 'seller');
});

/**
 * @return array<string, mixed>
 */
function wizardDetails(array $catalog, array $overrides = []): array
{
    return array_replace_recursive([
        'catalog_category_id' => $catalog['category']->id,
        'translations' => ['en' => [
            'name' => 'Rayon Printed Kurti',
            'description' => "<b>Soft</b> rayon kurti.\n- Machine wash",
            'highlights' => ['Rayon', 'Three-quarter sleeves', ''],
        ]],
        'attributes' => [$catalog['fabric'] => $catalog['options']['Rayon']],
        'country_of_origin' => 'in',
        'hsn_code' => '6204',
        'gst_percent' => '5',
        'manufacturer_details' => ['name' => 'Shree Textiles', 'address' => 'Surat, Gujarat'],
    ], $overrides);
}

test('the category step searches listable leaves and moves on to the details', function () {
    $this->get(route('seller.listing.create'))->assertSuccessful()->assertSee('Women');

    $this->getJson(route('seller.category.search', ['q' => 'kurt']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $this->catalog['category']->id)
        ->assertJsonPath('data.0.breadcrumb', 'Women › Kurtis');

    $this->post(route('seller.listing.category'), ['catalog_category_id' => $this->catalog['root']->id])
        ->assertSessionHasErrors('catalog_category_id');

    $this->post(route('seller.listing.category'), ['catalog_category_id' => $this->catalog['category']->id])
        ->assertRedirect(route('seller.listing.details', ['category' => $this->catalog['category']->id]));

    $this->get(route('seller.listing.details', ['category' => $this->catalog['category']->id]))
        ->assertSuccessful()
        ->assertSee('Fabric')
        ->assertSee('Women › Kurtis');
});

test('the full wizard creates the product, its variants and attributes and submits it to QC', function () {
    $this->post(route('seller.listing.store'), wizardDetails($this->catalog))->assertSessionHasNoErrors();

    $product = Product::query()->forSeller($this->seller->id)->with('translations')->sole();
    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->name)->toBe('Rayon Printed Kurti')
        ->and($product->description)->toBe("Soft rayon kurti.\n- Machine wash")
        ->and($product->highlights)->toBe(['Rayon', 'Three-quarter sleeves'])
        ->and($product->hsn_code)->toBe('6204')
        ->and($product->country_of_origin)->toBe('IN');

    foreach (['details', 'variants', 'images', 'pricing', 'review'] as $step) {
        $this->get(route('seller.listing.step', [$product->id, $step]))->assertSuccessful();
    }

    ['size' => $size, 'colour' => $colour, 'options' => $options] = $this->catalog;
    $this->postJson(route('seller.listing.variants.save', [$product->id]), ['variants' => [
        ['options' => [$size => $options['S'], $colour => $options['Red']], 'seller_sku' => 'RK-S-RED', 'mrp' => '999', 'price' => '499', 'stock' => 10, 'weight_grams' => 300],
        ['options' => [$size => $options['M'], $colour => $options['Red']], 'seller_sku' => 'RK-M-RED', 'mrp' => '999', 'price' => '499', 'stock' => 8, 'weight_grams' => 300],
    ]])->assertSuccessful()->assertJsonPath('redirect', route('seller.listing.step', [$product->id, 'images']));

    $this->post(route('seller.image.upload', [$product->id]), ['image' => UploadedFile::fake()->image('front.jpg', 600, 800)], ['Accept' => 'application/json'])
        ->assertSuccessful()
        ->assertJsonCount(1, 'images');

    $this->post(route('seller.listing.save', [$product->id, 'pricing']), ['variants' => [
        ['seller_sku' => 'RK-S-RED', 'mrp' => '1099', 'price' => '549', 'stock' => 12, 'weight_grams' => 320, 'length_cm' => '30', 'breadth_cm' => '25', 'height_cm' => '3', 'low_stock_threshold' => 2, 'status' => '1'],
        ['seller_sku' => 'RK-M-RED', 'mrp' => '1099', 'price' => '549', 'stock' => 8, 'weight_grams' => 320, 'status' => '1'],
    ]])->assertRedirect(route('seller.listing.step', [$product->id, 'review']));

    $this->get(route('seller.listing.step', [$product->id, 'review']))->assertSuccessful()->assertSee('RK-S-RED')->assertSee('Rayon');

    $this->post(route('seller.listing.submit', [$product->id]))->assertRedirect();

    $product = $product->fresh(['variants.attributeValues', 'attributeValues', 'images']);
    expect($product->status)->toBe(ProductStatus::PendingQc)
        ->and($product->variants)->toHaveCount(2)
        ->and($product->variants->pluck('seller_sku')->all())->toBe(['RK-S-RED', 'RK-M-RED'])
        ->and($product->variants->first()->price->toDecimal())->toBe('549.00')
        ->and($product->variants->first()->stock)->toBe(12)
        ->and($product->variants->first()->low_stock_threshold)->toBe(2)
        ->and($product->attributeValues->pluck('attribute_option_id')->all())->toBe([$options['Rayon']])
        ->and($product->images)->toHaveCount(1)
        ->and($product->min_price->toDecimal())->toBe('549.00')
        ->and((int) InventoryMovement::query()->where('product_id', $product->id)->sum('quantity'))->toBe(20);
});

test('required specifications are enforced at submission, not while drafting', function () {
    $this->post(route('seller.listing.store'), wizardDetails($this->catalog, ['attributes' => [$this->catalog['fabric'] => '']]))->assertSessionHasNoErrors();
    $product = Product::query()->forSeller($this->seller->id)->sole();

    $this->get(route('seller.listing.step', [$product->id, 'review']))->assertSuccessful()->assertSee('Fabric');

    $this->post(route('seller.listing.submit', [$product->id]))
        ->assertRedirect(route('seller.listing.step', [$product->id, 'review']))
        ->assertSessionHas('error');

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

test('details are validated and errors return to the form', function () {
    $this->from(route('seller.listing.details', ['category' => $this->catalog['category']->id]))
        ->post(route('seller.listing.store'), wizardDetails($this->catalog, ['translations' => ['en' => ['name' => '']], 'gst_percent' => '7']))
        ->assertSessionHasErrors(['translations.en.name', 'gst_percent']);

    expect(Product::query()->forSeller($this->seller->id)->exists())->toBeFalse();
});

test('a seller can request a new brand from the details step and list under it', function () {
    $this->post(route('seller.listing.store'), wizardDetails($this->catalog, ['brand_request' => 'Shree Looms']))->assertSessionHasNoErrors();

    $brand = CatalogBrand::query()->where('requested_by_seller_id', $this->seller->id)->with('translations')->sole();
    expect($brand->isPendingRequest())->toBeTrue()
        ->and($brand->name)->toBe('Shree Looms')
        ->and(Product::query()->forSeller($this->seller->id)->sole()->catalog_brand_id)->toBe($brand->id);
});

test('a never-published draft can move to another category, which drops mismatched specs and variants', function () {
    $product = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog));
    $other = CatalogCategory::factory()->child($this->catalog['root'])->create(['name' => 'Sarees']);

    $this->get(route('seller.listing.create', ['product' => $product->id]))->assertSuccessful();
    $this->post(route('seller.listing.category'), ['catalog_category_id' => $other->id, 'product' => $product->id])
        ->assertRedirect(route('seller.listing.step', [$product->id, 'details']));

    $product = $product->fresh(['variants', 'attributeValues']);
    expect($product->catalog_category_id)->toBe($other->id)
        ->and($product->variants)->toHaveCount(0)
        ->and($product->attributeValues)->toHaveCount(0);
});

test('editing a live product sends content to QC while price applies at once', function () {
    $product = liveCatalogProduct($this->seller, $this->catalog);

    $this->post(route('seller.listing.save', [$product->id, 'details']), wizardDetails($this->catalog, ['translations' => ['en' => ['name' => 'Renamed Kurti']]]))
        ->assertSessionHas('success', trans('product::seller.wizard.messages.changes_pending'));

    $product = $product->fresh(['translations']);
    expect($product->status)->toBe(ProductStatus::Live)
        ->and($product->hasPendingChanges())->toBeTrue()
        ->and($product->name)->toBe('Printed Cotton Kurti')
        ->and($product->pending_changes['translations']['en']['name'])->toBe('Renamed Kurti');

    $variants = $product->variants()->get();
    $this->post(route('seller.listing.save', [$product->id, 'pricing']), ['variants' => $variants->map(fn ($variant) => [
        'seller_sku' => $variant->seller_sku, 'mrp' => '999', 'price' => '399', 'stock' => 5, 'weight_grams' => 350, 'status' => '1',
    ])->all()])->assertSessionHasNoErrors();

    $product = $product->fresh(['translations']);
    expect($product->variants()->first()->price->toDecimal())->toBe('399.00')
        ->and($product->pending_changes['translations']['en']['name'])->toBe('Renamed Kurti');
});

test('products in QC are read-only in the wizard', function () {
    $product = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog));
    app(ProductModerationService::class)->submit($product);

    $this->get(route('seller.listing.step', [$product->id, 'details']))->assertRedirect(route('seller.product.index'));
    $this->post(route('seller.listing.save', [$product->id, 'details']), wizardDetails($this->catalog))->assertRedirect(route('seller.product.index'));
});

test('the SKU check reports SKUs the seller already uses', function () {
    $product = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog));

    $this->getJson(route('seller.listing.sku_check', ['sku' => 'KRT-S-RED']))->assertJsonPath('available', false);
    $this->getJson(route('seller.listing.sku_check', ['sku' => 'KRT-S-RED', 'product' => $product->id]))->assertJsonPath('available', true);
    $this->getJson(route('seller.listing.sku_check', ['sku' => 'NEW-SKU']))->assertJsonPath('available', true);
});
