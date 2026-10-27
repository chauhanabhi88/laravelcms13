<?php

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductAggregateService;
use Modules\Storefront\Services\Search\SearchIndexer;
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
}
