<?php

use Modules\Catalog\Database\Seeders\CatalogDatabaseSeeder;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Catalog\Support\ResolvedAttribute;

/*
| One test on purpose: seeding the whole taxonomy takes several seconds.
*/
test('the seeders build the starter taxonomy with mappings and defaults, and can run twice', function () {
    $this->seed(CatalogDatabaseSeeder::class);

    $total = CatalogCategory::query()->count();

    expect(CatalogCategory::query()->whereNull('parent_id')->count())->toBe(9)
        ->and(CatalogCategory::query()->where('depth', 1)->count())->toBeGreaterThanOrEqual(45)
        ->and(CatalogCategory::query()->where('depth', 2)->count())->toBeGreaterThanOrEqual(120)
        ->and(CatalogCategory::query()->where('depth', '>', 2)->count())->toBe(0)
        ->and(CatalogCategory::query()->where('slug', 'men-ethnic-wear')->exists())->toBeTrue();

    $kurtis = CatalogCategory::query()->where('slug', 'anarkali-kurtis')->firstOrFail();
    $attributes = app(CategoryAttributeResolver::class)->for($kurtis);

    expect($attributes->filter(fn (ResolvedAttribute $attribute) => $attribute->isVariantAxis)->pluck('code')->sort()->values()->all())->toBe(['colour', 'size'])
        ->and($attributes->filter(fn (ResolvedAttribute $attribute) => $attribute->isFilterable)->pluck('code')->all())->toContain('fabric', 'pattern', 'sleeve-length')
        ->and(collect($attributes->firstWhere('code', 'size')->options)->pluck('name')->all())->toContain('XS', '3XL')->not->toContain('UK 7', '30')
        ->and(app(CategoryDefaults::class)->for($kurtis))->toMatchArray(['gst_percent' => '5.00', 'hsn_code' => '6206', 'return_window_days' => 7]);

    $this->seed(CatalogDatabaseSeeder::class);

    expect(CatalogCategory::query()->count())->toBe($total);
});
