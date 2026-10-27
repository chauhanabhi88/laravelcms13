<?php

use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Product\DTO\ImageData;
use Modules\Product\DTO\ProductData;
use Modules\Product\DTO\VariantData;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductModerationService;
use Modules\Seller\Models\Seller;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->catalog = productCatalog();
    $this->seller = Seller::factory()->active()->create();
    $this->factory = app(ProductFactoryService::class);
});

/**
 * @return array<string, string>
 */
function productErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ProductValidationException $e) {
        return $e->errors();
    }

    return [];
}

/**
 * Create, submit and approve: a live product as QC would leave it.
 */
function liveProduct(Seller $seller, array $catalog): Product
{
    $product = app(ProductFactoryService::class)->create(productData($seller, $catalog));
    app(ProductModerationService::class)->submit($product);

    return app(ProductModerationService::class)->approve($product, productAdmin());
}

test('a product is created as a draft with its whole variant matrix', function () {
    $product = $this->factory->create(productData($this->seller, $this->catalog));

    expect($product)
        ->status->toBe(ProductStatus::Draft)
        ->seller_id->toBe($this->seller->id)
        ->slug->toBe('printed-cotton-kurti')
        ->gst_percent->toBe('5.00')
        ->seller_active->toBe(1)
        ->name->toBe('Printed Cotton Kurti')
        ->highlights->toBe(['Pure cotton', 'Machine wash']);

    $size = $this->catalog['size'];
    $colour = $this->catalog['colour'];
    $options = $this->catalog['options'];

    expect($product->variants->pluck('combination_key')->all())->toBe([
        "{$size}:{$options['S']}|{$colour}:{$options['Red']}",
        "{$size}:{$options['M']}|{$colour}:{$options['Red']}",
    ])
        ->and($product->variants->pluck('sku')->all())->toBe([
            strtoupper($this->seller->code)."-{$product->id}-1",
            strtoupper($this->seller->code)."-{$product->id}-2",
        ])
        ->and($product->variants->first()->attributeValues->pluck('attribute_option_id', 'attribute_id')->all())->toBe([$size => $options['S'], $colour => $options['Red']])
        ->and($product->attributeValues->pluck('attribute_option_id')->all())->toBe([$options['Cotton']])
        ->and($product->images)->toHaveCount(1);

    $openings = InventoryMovement::query()->where('product_id', $product->id)->get();
    expect($openings)->toHaveCount(2)
        ->and($openings->pluck('type')->unique()->all())->toBe(['opening'])
        ->and($openings->pluck('quantity')->all())->toBe([10, 10]);

    expect($product->fresh())
        ->min_price->toDecimal()->toBe('499.00')
        ->max_price->toDecimal()->toBe('549.00')
        ->min_mrp->toDecimal()->toBe('999.00')
        ->max_discount_percent->toBe(50)
        ->total_stock->toBe(20)
        ->primary_image->toBe($product->images->first()->path);
});

test('a second product with the same name gets its own slug', function () {
    $first = $this->factory->create(productData($this->seller, $this->catalog));
    $second = $this->factory->create(productData($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'OTHER-1', 'L', 'Blue')]]));

    expect($second->slug)->not->toBe($first->slug)->toStartWith('printed-cotton-kurti-');
});

test('the variant matrix must follow the category axes', function () {
    $other = productAttribute('pattern', ['Solid'])[1];
    $broken = new VariantData(sellerSku: 'NO-COLOUR', mrp: Money::of('999'), price: Money::of('499'), options: [$this->catalog['size'] => $this->catalog['options']['L']], weightGrams: 300);

    $errors = productErrors(fn () => $this->factory->create(productData($this->seller, $this->catalog, ['variants' => [
        productVariant($this->catalog, 'KRT-1', 'S', 'Red'),
        productVariant($this->catalog, 'KRT-2', 'S', 'Red'),
        $broken,
        new VariantData(sellerSku: 'WRONG-OPTION', mrp: Money::of('999'), price: Money::of('499'), options: [$this->catalog['size'] => $other['Solid'], $this->catalog['colour'] => $this->catalog['options']['Blue']], weightGrams: 300),
        productVariant($this->catalog, 'krt-1', 'L', 'Blue'),
        productVariant($this->catalog, 'KRT-6', 'M', 'Blue', '1200', '999'),
    ]])));

    expect($errors)->toHaveKeys([
        'variants.1.options',
        'variants.2.options',
        'variants.3.options',
        'variants.4.seller_sku',
        'variants.5.price',
    ]);
    expect(Product::query()->where('seller_id', $this->seller->id)->exists())->toBeFalse();
});

test('products attach only to active leaf categories', function () {
    $errors = productErrors(fn () => $this->factory->create(productData($this->seller, $this->catalog, ['categoryId' => $this->catalog['root']->id])));
    expect($errors)->toHaveKey('catalog_category_id');

    $disabled = CatalogCategory::factory()->disabled()->create();
    expect(productErrors(fn () => $this->factory->create(productData($this->seller, $this->catalog, ['categoryId' => $disabled->id]))))->toHaveKey('catalog_category_id');
});

test('content, specification and image rules are checked', function () {
    $errors = productErrors(fn () => $this->factory->create(productData($this->seller, $this->catalog, [
        'translations' => ['en' => ['name' => '  ', 'highlights' => array_fill(0, 9, 'Soft')]],
        'attributes' => [$this->catalog['fabric'] => 999999, $this->catalog['size'] => $this->catalog['options']['S']],
        'images' => array_fill(0, 9, new ImageData('photo.jpg')),
        'hsnCode' => '12AB',
        'gstPercent' => '41',
    ])));

    expect($errors)->toHaveKeys([
        'translations.en.name',
        'attributes.'.$this->catalog['fabric'],
        'attributes.'.$this->catalog['size'],
        'images',
        'hsn_code',
        'gst_percent',
    ]);
});

test('only active sellers can create products and seller SKUs stay unique per seller', function () {
    $suspended = Seller::factory()->suspended()->create();
    expect(productErrors(fn () => $this->factory->create(productData($suspended, $this->catalog))))->toHaveKey('seller_id');

    $this->factory->create(productData($this->seller, $this->catalog));
    $errors = productErrors(fn () => $this->factory->create(productData($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'KRT-M-RED', 'L', 'Blue')]])));
    expect($errors)->toHaveKey('variants.0.seller_sku');

    // Another seller may reuse the same seller SKU.
    $otherSeller = Seller::factory()->active()->create();
    expect($this->factory->create(productData($otherSeller, $this->catalog))->variants)->toHaveCount(2);
});

test('editing an unpublished product applies everything at once', function () {
    $product = $this->factory->create(productData($this->seller, $this->catalog));
    $kept = $product->variants->first();

    $updated = $this->factory->update($product, productData($this->seller, $this->catalog, [
        'translations' => ['en' => ['name' => 'Block Print Kurti']],
        'variants' => [
            productVariant($this->catalog, 'KRT-S-RED', 'S', 'Red', '459', '999', 7, $kept->id),
            productVariant($this->catalog, 'KRT-L-BLUE', 'L', 'Blue', '599', '1199', 4),
        ],
        'attributes' => [$this->catalog['fabric'] => $this->catalog['options']['Rayon']],
    ]));

    expect($updated)
        ->name->toBe('Block Print Kurti')
        ->slug->toBe('block-print-kurti')
        ->has_pending_changes->toBe(2)
        ->and($updated->variants->pluck('seller_sku')->all())->toBe(['KRT-S-RED', 'KRT-L-BLUE'])
        ->and($updated->variants->first())->id->toBe($kept->id)->stock->toBe(7)->price->toDecimal()->toBe('459.00')
        ->and($updated->attributeValues->pluck('attribute_option_id')->all())->toBe([$this->catalog['options']['Rayon']])
        ->and(ProductVariant::query()->where('seller_sku', 'KRT-M-RED')->exists())->toBeFalse()
        ->and($updated->fresh()->min_price->toDecimal())->toBe('459.00');

    $adjust = InventoryMovement::query()->where('variant_id', $kept->id)->where('type', 'adjust')->sole();
    expect($adjust->quantity)->toBe(-3);
});

test('a live edit parks content changes for QC but applies price and stock now', function () {
    $product = liveProduct($this->seller, $this->catalog);
    [$small, $medium] = $product->variants->all();

    $edit = productEditData($product->fresh(), $this->catalog, [
        'translations' => ['en' => ['name' => 'Festive Cotton Kurti']],
    ]);
    $variants = $edit->variants;
    $variants[0] = productVariant($this->catalog, 'KRT-S-RED', 'S', 'Red', '399', '999', 25, $small->id);
    $variants[] = productVariant($this->catalog, 'KRT-L-BLUE', 'L', 'Blue', '599', '1199', 5);

    $updated = $this->factory->update($product, new ProductData(...[
        'sellerId' => $edit->sellerId,
        'categoryId' => $edit->categoryId,
        'translations' => $edit->translations,
        'variants' => $variants,
        'images' => $edit->images,
        'attributes' => $edit->attributes,
    ]));

    // Content stays as approved until QC decides.
    expect($updated)
        ->status->toBe(ProductStatus::Live)
        ->name->toBe('Printed Cotton Kurti')
        ->has_pending_changes->toBe(1)
        ->and($updated->pending_changes)->toHaveKeys(['translations', 'variants'])
        ->and($updated->pending_changes['translations']['en']['name'])->toBe('Festive Cotton Kurti')
        ->and($updated->pending_changes['variants'][0]['seller_sku'])->toBe('KRT-L-BLUE')
        ->and($updated->variants)->toHaveCount(2)
        ->and($updated->submitted_at)->not->toBeNull();

    // Price and stock apply immediately, through the ledger.
    expect($small->fresh())->price->toDecimal()->toBe('399.00')->stock->toBe(25)
        ->and(InventoryMovement::query()->where('variant_id', $small->id)->where('type', 'adjust')->sole()->quantity)->toBe(15)
        ->and($updated->fresh())->min_price->toDecimal()->toBe('399.00')->total_stock->toBe(35)
        ->and($medium->fresh()->price->toDecimal())->toBe('549.00');

    // Saving the live content again clears the pending changes.
    $cleared = $this->factory->update($updated, productEditData($updated->fresh(), $this->catalog));
    expect($cleared)->has_pending_changes->toBe(2)->pending_changes->toBeNull();
});

test('a live variant keeps its options and a left-out variant is only disabled', function () {
    $product = liveProduct($this->seller, $this->catalog);
    $small = $product->variants->first();

    $edit = productEditData($product->fresh(), $this->catalog);
    $variants = $edit->variants;
    $variants[0] = productVariant($this->catalog, 'KRT-S-RED', 'L', 'Blue', '499', '999', 10, $small->id);

    $errors = productErrors(fn () => $this->factory->update($product, new ProductData(
        sellerId: $edit->sellerId,
        categoryId: $edit->categoryId,
        translations: $edit->translations,
        variants: $variants,
        images: $edit->images,
        attributes: $edit->attributes,
    )));
    expect($errors)->toHaveKey('variants.0.options');

    // Leave the cheaper variant out: it is disabled, kept for order history, and the
    // denormalised price moves to the remaining variant.
    $this->factory->update($product, new ProductData(
        sellerId: $edit->sellerId,
        categoryId: $edit->categoryId,
        translations: $edit->translations,
        variants: [$edit->variants[1]],
        images: $edit->images,
        attributes: $edit->attributes,
    ));

    expect($small->fresh()->status)->toBe(2)
        ->and($product->fresh())->min_price->toDecimal()->toBe('549.00')->total_stock->toBe(10)->has_pending_changes->toBe(2);
});

test('required specifications are enforced on submission', function () {
    $product = $this->factory->create(productData($this->seller, $this->catalog, ['attributes' => []]));

    expect(fn () => app(ProductModerationService::class)->submit($product))
        ->toThrow(ProductException::class, 'Fabric');

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});
