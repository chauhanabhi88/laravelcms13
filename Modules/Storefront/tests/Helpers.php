<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductAggregateService;
use Modules\Seller\Models\Seller;
use Modules\Storefront\Services\Search\SearchIndexer;
use Modules\Storefront\ViewModels\ProductPageViewModel;
use Modules\User\Models\User;

require_once __DIR__.'/../../Product/tests/SellerCatalogHelpers.php';

if (! function_exists('storefrontProduct')) {
    /**
     * A live product with one variant (indexed through the aggregate event).
     *
     * @param  array<string, mixed>  $attributes  product attributes ("name" lands in the translation)
     * @param  array<string, mixed>  $variant  variant attributes (price, mrp, stock…)
     */
    function storefrontProduct(array $attributes = [], array $variant = [], ?CatalogCategory $category = null): Product
    {
        $factory = Product::factory()->live()->withVariants(1, $variant);

        if ($category !== null) {
            $factory = $factory->inCategory($category);
        }

        return $factory->create($attributes);
    }

    /**
     * Change a product's aggregate inputs directly (tests only) and re-index it.
     *
     * @param  array<string, mixed>  $productColumns  e.g. rating_avg, rating_count, sold_count, published_at
     */
    function storefrontUpdate(Product $product, array $productColumns = [], array $variantColumns = []): Product
    {
        if ($productColumns !== []) {
            DB::table('product')->where('id', $product->id)->update($productColumns);
        }

        if ($variantColumns !== []) {
            ProductVariant::query()->where('product_id', $product->id)->update($variantColumns);
            app(ProductAggregateService::class)->refresh($product->id);
        }

        app(SearchIndexer::class)->index($product->id);

        return $product->refresh();
    }

    /**
     * An admin whose role holds $permissions (null = every Storefront permission).
     *
     * @param  list<string>|null  $permissions
     */
    function storefrontAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Storefront/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Merchandiser',
            'slug' => 'merchandiser_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Merchandiser',
            'email' => uniqid('storefront-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }

    /**
     * @return list<int>
     */
    function indexedIds(): array
    {
        return DB::table('product_search_index')->where('is_visible', 1)->distinct()->pluck('product_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
    }

    /**
     * A live product with Size × Colour variants through the real write path (M07 tests):
     * S / Red ₹499 (3 in stock), M / Red ₹549 (25), L / Blue ₹599 (0) unless $variants
     * (fn (array $catalog): list<VariantData>) says otherwise.
     *
     * @param  array<string, mixed>  $overrides  ProductData arguments
     * @return array{product: Product, catalog: array<string, mixed>, seller: Seller}
     */
    function pdpProduct(?Closure $variants = null, array $overrides = [], ?Seller $seller = null): array
    {
        $catalog = productCatalog();
        $seller ??= Seller::factory()->active()->create();
        $variants ??= fn (array $catalog): array => [
            productVariant($catalog, 'PDP-S-RED-'.uniqid(), 'S', 'Red', '499', '999', 3),
            productVariant($catalog, 'PDP-M-RED-'.uniqid(), 'M', 'Red', '549', '1099', 25),
            productVariant($catalog, 'PDP-L-BLUE-'.uniqid(), 'L', 'Blue', '599', '1199', 0),
        ];

        $product = liveCatalogProduct($seller, $catalog, ['variants' => $variants($catalog)] + $overrides);

        return ['product' => $product->fresh(), 'catalog' => $catalog, 'seller' => $seller];
    }

    /**
     * A real image file in the public product folder (getImageUrl() checks the file exists);
     * removed again by pdpCleanupImages().
     */
    function pdpImageFile(): string
    {
        $name = 'pdp-test-'.Str::random(16).'.jpg';
        $folder = public_path('storage/'.config('product.upload.folder', 'product'));
        File::ensureDirectoryExists($folder);
        File::put($folder.'/'.$name, jpegBytes(600, 800));
        $GLOBALS['pdpTestImages'][] = $folder.'/'.$name;

        return $name;
    }

    function pdpCleanupImages(): void
    {
        foreach ($GLOBALS['pdpTestImages'] ?? [] as $path) {
            File::delete($path);
        }
        $GLOBALS['pdpTestImages'] = [];
    }

    /**
     * Product page view model for $product, loaded like the controller does.
     */
    function pdpPage(Product $product, ?int $variantId = null): ProductPageViewModel
    {
        return app(ProductPageViewModel::class)->load($product->fresh(), $variantId);
    }
}
