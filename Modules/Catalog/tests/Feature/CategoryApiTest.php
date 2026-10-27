<?php

use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryAttribute;

test('the tree endpoint returns enabled categories down to the requested depth', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Ethnic Wear', 'slug' => 'ethnic-wear']);
    $kurtis = CatalogCategory::factory()->child($root)->create(['name' => 'Kurtis', 'slug' => 'kurtis']);
    CatalogCategory::factory()->child($kurtis)->create(['name' => 'Anarkali Kurtis', 'slug' => 'anarkali-kurtis']);
    CatalogCategory::factory()->child($root)->disabled()->create(['name' => 'Hidden', 'slug' => 'hidden-cat']);

    $response = $this->getJson(route('catalog_api.categories.tree', ['depth' => 2]))
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $node = collect($response->json('data'))->firstWhere('slug', 'ethnic-wear');

    expect($node)->toMatchArray(['name' => 'Ethnic Wear', 'depth' => 0, 'is_leaf' => false])
        ->and(collect($node['children'])->pluck('slug')->all())->toBe(['kurtis'])
        ->and($node['children'][0]['children'])->toBe([])
        ->and(array_keys($node))->toBe(['id', 'name', 'slug', 'depth', 'is_leaf', 'image', 'icon', 'children']);
});

test('a category by slug has breadcrumbs, children and filterable attributes', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Ethnic Wear', 'slug' => 'ethnic-wear']);
    $leaf = CatalogCategory::factory()->child($root)->create(['name' => 'Kurtis', 'slug' => 'kurtis', 'return_window_days' => 10]);

    $fabric = Attribute::query()->create(['code' => 'fabric-'.uniqid(), 'input_type' => 'select', 'custom_value' => 1, 'is_required' => 2]);
    $fabric->translations()->forceCreate(['name' => 'Fabric', 'locale' => app()->getLocale()]);
    $cotton = AttributeOption::query()->create(['attribute_id' => $fabric->id, 'custom_option' => 'cotton', 'default' => 2, 'sort_order' => 1]);
    $cotton->translations()->forceCreate(['name' => 'Cotton', 'locale' => app()->getLocale()]);
    $hidden = Attribute::query()->create(['code' => 'care-'.uniqid(), 'input_type' => 'textbox', 'custom_value' => 2, 'is_required' => 2]);
    $hidden->translations()->forceCreate(['name' => 'Care', 'locale' => app()->getLocale()]);

    CatalogCategoryAttribute::query()->create(['catalog_category_id' => $root->id, 'attribute_id' => $fabric->id, 'is_filterable' => 1]);
    CatalogCategoryAttribute::query()->create(['catalog_category_id' => $leaf->id, 'attribute_id' => $hidden->id, 'is_filterable' => 2]);

    $this->getJson(route('catalog_api.categories.show', ['kurtis']))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Kurtis')
        ->assertJsonPath('data.is_leaf', true)
        ->assertJsonPath('data.breadcrumbs', [['id' => $root->id, 'name' => 'Ethnic Wear', 'slug' => 'ethnic-wear']])
        ->assertJsonPath('data.children', [])
        ->assertJsonCount(1, 'data.filterable_attributes')
        ->assertJsonPath('data.filterable_attributes.0.name', 'Fabric')
        ->assertJsonPath('data.filterable_attributes.0.options.0.name', 'Cotton')
        ->assertJsonPath('data.return_policy.window_days', 10);

    $this->getJson(route('catalog_api.categories.show', ['ethnic-wear']))
        ->assertSuccessful()
        ->assertJsonPath('data.children.0.slug', 'kurtis');
});

test('unknown or hidden categories are a 404', function () {
    $root = CatalogCategory::factory()->disabled()->create(['slug' => 'switched-off']);
    CatalogCategory::factory()->child($root)->create(['slug' => 'under-switched-off']);

    foreach (['does-not-exist', 'switched-off', 'under-switched-off'] as $slug) {
        $this->getJson(route('catalog_api.categories.show', [$slug]))
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => trans('catalog::catalog.errors.not_found'), 'code' => '404']);
    }
});

test('brands are searchable and pending requests are not listed', function () {
    CatalogBrand::factory()->verified()->create(['name' => 'Loomcraft', 'slug' => 'loomcraft']);
    CatalogBrand::factory()->create(['name' => 'Urban Thread', 'slug' => 'urban-thread']);
    CatalogBrand::factory()->requested()->create(['name' => 'Loom Pending', 'slug' => 'loom-pending']);

    $this->getJson(route('catalog_api.brands.index', ['q' => 'loom']))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0', ['id' => CatalogBrand::query()->where('slug', 'loomcraft')->value('id'), 'name' => 'Loomcraft', 'slug' => 'loomcraft', 'logo' => null, 'is_verified' => true]);
});
