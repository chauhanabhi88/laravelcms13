<?php

use Modules\Catalog\Contracts\CategoryInUse;
use Modules\Catalog\Exceptions\CatalogException;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Product\Models\Product;
use Modules\Product\Support\CategoryInUseChecker;

test('the product module answers the catalog\'s category-in-use question', function () {
    expect(app(CategoryInUse::class))->toBeInstanceOf(CategoryInUseChecker::class);
});

test('a category holding a product can be neither deleted nor given children', function () {
    $root = CatalogCategory::factory()->create();
    $leaf = CatalogCategory::factory()->child($root)->create(['name' => 'Silk Sarees']);
    $product = Product::factory()->inCategory($leaf)->create();
    $tree = app(CategoryTreeService::class);

    expect(fn () => $tree->delete($leaf->fresh()))->toThrow(CatalogException::class)
        ->and(fn () => $tree->create(['parent_id' => $leaf->id, 'slug' => 'silk-sub-'.uniqid(), 'en' => ['name' => 'Banarasi']]))->toThrow(CatalogException::class);

    expect(CatalogCategory::query()->whereKey($leaf->id)->exists())->toBeTrue();

    // A deleted product no longer holds the category.
    $product->delete();
    $tree->delete($leaf->fresh());

    expect(CatalogCategory::query()->whereKey($leaf->id)->exists())->toBeFalse();
});
