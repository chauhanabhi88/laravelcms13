<?php

use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogSizeChart;
use Modules\Catalog\Services\CategoryDefaults;

require_once __DIR__.'/../Helpers.php';

test('brand screens need their permissions', function () {
    $admin = catalogAdmin(['admin.catalog_category.index']);

    $this->actingAs($admin)->get(route('admin.catalog_brand.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.catalog_size_chart.index'))->assertForbidden();
});

test('an admin creates and edits a brand', function () {
    $admin = catalogAdmin();

    $this->actingAs($admin)->get(route('admin.catalog_brand.create'))->assertSuccessful();

    $this->actingAs($admin)
        ->post(route('admin.catalog_brand.store'), [
            app()->getLocale() => ['name' => 'Loomcraft', 'description' => 'Handloom wear'],
            'slug' => 'loomcraft',
            'is_verified' => config('core.yes'),
            'status' => config('core.enabled'),
        ])
        ->assertRedirect(route('admin.catalog_brand.index'))
        ->assertSessionHas('success');

    $brand = CatalogBrand::query()->with('translations')->where('slug', 'loomcraft')->firstOrFail();
    expect($brand)->name->toBe('Loomcraft')->is_verified->toBe(1)->status->toBe(1);

    $this->actingAs($admin)->get(route('admin.catalog_brand.index'))->assertSuccessful()->assertSee('Loomcraft');

    $this->actingAs($admin)
        ->put(route('admin.catalog_brand.update', [$brand->id]), [
            app()->getLocale() => ['name' => 'Loomcraft India'],
            'slug' => 'loomcraft',
        ])
        ->assertSessionHas('success');

    expect($brand->fresh()->load('translations'))
        ->name->toBe('Loomcraft India')
        ->is_verified->toBe(2)
        ->status->toBe(2);
});

test('a pending brand request is approved', function () {
    $brand = CatalogBrand::factory()->requested(7)->create(['name' => 'Requested Label']);
    $admin = catalogAdmin();

    $this->actingAs($admin)
        ->post(route('admin.catalog_brand.filters'), ['pending' => config('core.yes')])
        ->assertSuccessful()
        ->assertSee('Requested Label');

    $this->actingAs($admin)
        ->postJson(route('admin.catalog_brand.approve', [$brand->id]))
        ->assertSuccessful()
        ->assertJsonPath('type', 'success');

    expect($brand->fresh())->status->toBe(1)->isPendingRequest()->toBeFalse();
});

test('an admin creates a size chart from the grid editor', function () {
    $admin = catalogAdmin();

    $this->actingAs($admin)->get(route('admin.catalog_size_chart.create'))->assertSuccessful()->assertSee('data-size-chart-grid', false);

    $this->actingAs($admin)
        ->post(route('admin.catalog_size_chart.store'), [
            'name' => 'Kurti Sizes',
            'unit' => 'in',
            'status' => config('core.enabled'),
            'columns' => ['Bust', 'Length'],
            'rows' => [
                ['size' => 'S', 'values' => ['34', '44']],
                ['size' => 'M', 'values' => ['36', '44']],
            ],
        ])
        ->assertRedirect(route('admin.catalog_size_chart.index'))
        ->assertSessionHas('success');

    $chart = CatalogSizeChart::query()->where('name', 'Kurti Sizes')->firstOrFail();

    expect($chart->columns)->toBe(['Bust', 'Length'])
        ->and($chart->rows)->toBe([['size' => 'S', 'values' => ['34', '44']], ['size' => 'M', 'values' => ['36', '44']]])
        ->and($chart->sizes())->toBe(['S', 'M']);

    $this->actingAs($admin)->get(route('admin.catalog_size_chart.edit', [$chart->id]))->assertSuccessful()->assertSee('value="Bust"', false);
});

test('a size chart row needs a value per column and sizes must be unique', function () {
    $this->actingAs(catalogAdmin())
        ->from(route('admin.catalog_size_chart.create'))
        ->post(route('admin.catalog_size_chart.store'), [
            'name' => 'Broken',
            'unit' => 'cm',
            'columns' => ['Bust', 'Waist'],
            'rows' => [
                ['size' => 'S', 'values' => ['34']],
                ['size' => 's', 'values' => ['34', '28']],
            ],
        ])
        ->assertSessionHasErrors(['rows.0.values', 'rows.1.size']);
});

test('deleting a size chart unlinks it from categories', function () {
    $chart = CatalogSizeChart::factory()->create();
    $category = CatalogCategory::factory()->create(['size_chart_id' => $chart->id]);

    expect(app(CategoryDefaults::class)->for($category)['size_chart_id'])->toBe($chart->id);

    $this->actingAs(catalogAdmin())
        ->delete(route('admin.catalog_size_chart.delete', [$chart->id]))
        ->assertSessionHas('success');

    expect($category->fresh()->size_chart_id)->toBeNull()
        ->and(app(CategoryDefaults::class)->for($category->fresh())['size_chart_id'])->toBeNull();
});
