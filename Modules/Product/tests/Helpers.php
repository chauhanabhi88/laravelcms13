<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryAttribute;
use Modules\Core\Support\Money;
use Modules\Product\DTO\ImageData;
use Modules\Product\DTO\ProductData;
use Modules\Product\DTO\VariantData;
use Modules\Product\Models\Product;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

if (! function_exists('productAdmin')) {
    /**
     * An admin user whose role holds $permissions (null = every Product permission).
     *
     * @param  list<string>|null  $permissions
     */
    function productAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Product/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Catalog Moderator',
            'slug' => 'catalog_moderator_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Catalog Moderator',
            'email' => uniqid('product-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }

    /**
     * An attribute with options in the current locale.
     *
     * @param  list<string>  $options
     * @return array{0: Attribute, 1: array<string, int>} the attribute and option name => id
     */
    function productAttribute(string $code, array $options = [], string $inputType = 'select'): array
    {
        $attribute = Attribute::query()->create(['code' => $code.'-'.uniqid(), 'input_type' => $inputType, 'custom_value' => 1, 'is_required' => 2]);
        $attribute->translations()->forceCreate(['name' => ucfirst($code), 'locale' => app()->getLocale()]);

        $ids = [];
        foreach ($options as $index => $option) {
            $row = AttributeOption::query()->create(['attribute_id' => $attribute->id, 'custom_option' => strtolower($option), 'default' => 2, 'sort_order' => $index]);
            $row->translations()->forceCreate(['name' => $option, 'locale' => app()->getLocale()]);
            $ids[$option] = (int) $row->id;
        }

        return [$attribute, $ids];
    }

    /**
     * Women › Kurtis with Size and Colour as variant axes and Fabric as a required
     * specification.
     *
     * @param  array<string, mixed>  $leafAttributes  extra category columns (min_selling_price…)
     * @return array{category: CatalogCategory, root: CatalogCategory, size: int, colour: int, fabric: int, options: array<string, int>}
     */
    function productCatalog(array $leafAttributes = []): array
    {
        $root = CatalogCategory::factory()->create(['name' => 'Women']);
        $leaf = CatalogCategory::factory()->child($root)->create(['name' => 'Kurtis'] + $leafAttributes);

        [$size, $sizes] = productAttribute('size', ['S', 'M', 'L']);
        [$colour, $colours] = productAttribute('colour', ['Red', 'Blue']);
        [$fabric, $fabrics] = productAttribute('fabric', ['Cotton', 'Rayon']);

        $map = fn (Attribute $attribute, array $flags) => CatalogCategoryAttribute::query()->create($flags + [
            'catalog_category_id' => $leaf->id,
            'attribute_id' => $attribute->id,
            'is_variant_axis' => 2,
            'is_filterable' => 1,
            'is_required' => 2,
            'show_on_card' => 2,
            'sort_order' => 0,
        ]);

        $map($size, ['is_variant_axis' => 1, 'sort_order' => 1]);
        $map($colour, ['is_variant_axis' => 1, 'sort_order' => 2]);
        $map($fabric, ['is_required' => 1, 'sort_order' => 3]);

        return [
            'category' => $leaf->fresh(),
            'root' => $root,
            'size' => (int) $size->id,
            'colour' => (int) $colour->id,
            'fabric' => (int) $fabric->id,
            'options' => $sizes + $colours + $fabrics,
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog  from productCatalog()
     */
    function productVariant(array $catalog, string $sku, string $size, string $colour, string $price = '499', string $mrp = '999', int $stock = 10, ?int $id = null): VariantData
    {
        return new VariantData(
            sellerSku: $sku,
            mrp: Money::of($mrp),
            price: Money::of($price),
            stock: $stock,
            options: [$catalog['size'] => $catalog['options'][$size], $catalog['colour'] => $catalog['options'][$colour]],
            weightGrams: 350,
            lengthCm: '30',
            breadthCm: '25',
            heightCm: '3',
            id: $id,
        );
    }

    /**
     * A complete submission: two variants (S / Red, M / Red), one image, Fabric = Cotton.
     *
     * @param  array<string, mixed>  $catalog  from productCatalog()
     * @param  array<string, mixed>  $overrides  ProductData constructor arguments
     */
    function productData(Seller $seller, array $catalog, array $overrides = []): ProductData
    {
        return new ProductData(...$overrides + [
            'sellerId' => $seller->id,
            'categoryId' => $catalog['category']->id,
            'translations' => ['en' => [
                'name' => 'Printed Cotton Kurti',
                'description' => 'Straight-cut kurti in soft cotton.',
                'highlights' => ['Pure cotton', 'Machine wash'],
            ]],
            'variants' => [
                productVariant($catalog, 'KRT-S-RED', 'S', 'Red'),
                productVariant($catalog, 'KRT-M-RED', 'M', 'Red', '549', '1099'),
            ],
            'images' => [new ImageData(path: Str::random(40).'.jpg', width: 800, height: 1067, contentHash: md5(Str::random()))],
            'attributes' => [$catalog['fabric'] => $catalog['options']['Cotton']],
        ]);
    }

    /**
     * The same submission as productData() for an existing product, with variant ids kept.
     *
     * @param  array<string, mixed>  $catalog
     * @param  array<string, mixed>  $overrides
     */
    function productEditData(Product $product, array $catalog, array $overrides = []): ProductData
    {
        $product->loadMissing(['variants.attributeValues', 'images', 'translations']);
        $variants = $product->variants->map(fn ($variant) => new VariantData(
            sellerSku: $variant->seller_sku,
            mrp: $variant->mrp,
            price: $variant->price,
            stock: $variant->stock,
            options: $variant->attributeValues->pluck('attribute_option_id', 'attribute_id')->map(fn ($id) => (int) $id)->all(),
            weightGrams: $variant->weight_grams,
            lengthCm: $variant->length_cm,
            breadthCm: $variant->breadth_cm,
            heightCm: $variant->height_cm,
            lowStockThreshold: $variant->low_stock_threshold,
            active: $variant->isEnabled(),
            id: $variant->id,
        ))->all();

        return new ProductData(...$overrides + [
            'sellerId' => $product->seller_id,
            'categoryId' => $product->catalog_category_id,
            'translations' => ['en' => [
                'name' => $product->translations->firstWhere('locale', 'en')?->name,
                'description' => $product->translations->firstWhere('locale', 'en')?->description,
                'highlights' => $product->translations->firstWhere('locale', 'en')?->highlights,
            ]],
            'variants' => $variants,
            'images' => $product->images->map(fn ($image) => new ImageData($image->path, $image->attribute_option_id, $image->alt, $image->width, $image->height, $image->content_hash))->all(),
            'attributes' => [$catalog['fabric'] => $catalog['options']['Cotton']],
        ]);
    }
}
