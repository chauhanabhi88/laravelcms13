<?php

use Modules\Attribute\Models\Attribute;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryAttribute;

require_once __DIR__.'/../Helpers.php';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function categoryPayload(string $name, array $overrides = []): array
{
    return $overrides + [
        app()->getLocale() => ['name' => $name, 'description' => 'About '.$name],
        'slug' => str($name)->slug()->toString(),
        'status' => config('core.enabled'),
        'show_in_menu' => config('core.yes'),
    ];
}

test('guests are sent to the admin login', function () {
    $this->get(route('admin.catalog_category.index'))->assertRedirect(route('backend_login'));
});

test('admins without the permission get a 403', function () {
    $admin = catalogAdmin(['admin.dashboard.index']);

    $this->actingAs($admin)->get(route('admin.catalog_category.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.catalog_category.store'), categoryPayload('Sarees'))->assertForbidden();
    $this->actingAs($admin)->postJson(route('admin.catalog_category.move'), ['id' => 1])->assertForbidden();

    expect(CatalogCategory::query()->where('slug', 'sarees')->exists())->toBeFalse();
});

test('the index shows the tree and the grid', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Ethnic Wear']);
    CatalogCategory::factory()->child($root)->create(['name' => 'Kurtis']);

    $this->actingAs(catalogAdmin())
        ->get(route('admin.catalog_category.index'))
        ->assertSuccessful()
        ->assertSee('id="catalog-tree-panel"', false)
        ->assertSee('data-name="Ethnic Wear"', false)
        ->assertSee('data-name="Kurtis"', false)
        ->assertSee('modules/catalog/js/tree.js', false);
});

test('an admin creates a top-level category and a subcategory', function () {
    $admin = catalogAdmin();

    $this->actingAs($admin)
        ->post(route('admin.catalog_category.store'), categoryPayload('Ethnic Wear', ['gst_percent' => '5', 'hsn_code' => '6204']))
        ->assertRedirect()
        ->assertSessionHas('success');

    $root = CatalogCategory::query()->where('slug', 'ethnic-wear')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.catalog_category.create', ['parent_id' => $root->id]))
        ->assertSuccessful()
        ->assertSee('Inherited: 5%');

    $this->actingAs($admin)
        ->post(route('admin.catalog_category.store'), categoryPayload('Kurtis', ['parent_id' => $root->id]))
        ->assertSessionHas('success');

    $child = CatalogCategory::query()->with('translations')->where('slug', 'kurtis')->firstOrFail();

    expect($child)
        ->parent_id->toBe($root->id)
        ->path->toBe("/{$root->id}/{$child->id}/")
        ->depth->toBe(1)
        ->name->toBe('Kurtis')
        ->gst_percent->toBeNull()
        ->and($root->fresh()->is_leaf)->toBe(2)
        ->and((string) $root->fresh()->gst_percent)->toBe('5.00');
});

test('the form is validated', function () {
    CatalogCategory::factory()->create(['slug' => 'taken']);

    $this->actingAs(catalogAdmin())
        ->from(route('admin.catalog_category.create'))
        ->post(route('admin.catalog_category.store'), [
            'slug' => 'taken',
            'hsn_code' => '12',
            'gst_percent' => '99',
            'return_type' => 'forever',
        ])
        ->assertSessionHasErrors([app()->getLocale().'.name', 'slug', 'hsn_code', 'gst_percent', 'return_type']);
});

test('an admin edits a category and can move it by changing the parent', function () {
    $women = CatalogCategory::factory()->create(['name' => 'Women']);
    $men = CatalogCategory::factory()->create(['name' => 'Men']);
    $category = CatalogCategory::factory()->child($women)->create(['name' => 'Ethnic']);
    $admin = catalogAdmin();

    $this->actingAs($admin)->get(route('admin.catalog_category.edit', [$category->id]))
        ->assertSuccessful()
        ->assertSee('value="Ethnic"', false);

    $this->actingAs($admin)
        ->put(route('admin.catalog_category.update', [$category->id]), categoryPayload('Ethnic Wear', [
            'slug' => $category->slug,
            'parent_id' => $men->id,
            'return_window_days' => '10',
        ]))
        ->assertSessionHas('success');

    expect($category->fresh()->load('translations'))
        ->name->toBe('Ethnic Wear')
        ->parent_id->toBe($men->id)
        ->path->toBe("/{$men->id}/{$category->id}/")
        ->return_window_days->toBe(10)
        ->and($women->fresh()->is_leaf)->toBe(1);
});

test('the tree move endpoint re-parents and rejects cycles', function () {
    $root = CatalogCategory::factory()->create();
    $child = CatalogCategory::factory()->child($root)->create();
    $other = CatalogCategory::factory()->create();
    $admin = catalogAdmin();

    $this->actingAs($admin)
        ->postJson(route('admin.catalog_category.move'), ['id' => $other->id, 'parent_id' => $root->id, 'position' => 0])
        ->assertSuccessful()
        ->assertJsonPath('type', 'success');

    expect($other->fresh())->parent_id->toBe($root->id)->sort_order->toBe(0)
        ->and($child->fresh()->sort_order)->toBe(1);

    $this->actingAs($admin)
        ->postJson(route('admin.catalog_category.move'), ['id' => $root->id, 'parent_id' => $child->id])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'error');

    expect($root->fresh()->parent_id)->toBeNull();
});

test('deleting is blocked while a category has subcategories', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Bags']);
    $child = CatalogCategory::factory()->child($root)->create();
    $admin = catalogAdmin();

    $this->actingAs($admin)
        ->delete(route('admin.catalog_category.delete', [$root->id]))
        ->assertSessionHas('error');

    expect($root->fresh()->trashed())->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('admin.catalog_category.delete', [$child->id]))
        ->assertSessionHas('success');

    expect($child->fresh()->trashed())->toBeTrue()
        ->and($root->fresh()->is_leaf)->toBe(1);
});

test('mass delete reports the categories it skipped', function () {
    $root = CatalogCategory::factory()->create();
    CatalogCategory::factory()->child($root)->create();
    $lonely = CatalogCategory::factory()->create();

    $this->actingAs(catalogAdmin())
        ->delete(route('admin.catalog_category.mass_delete'), ['idsToDelete' => $root->id.','.$lonely->id])
        ->assertSessionHas('success')
        ->assertSessionHas('error');

    expect($lonely->fresh()->trashed())->toBeTrue()
        ->and($root->fresh()->trashed())->toBeFalse();
});

test('the status toggle refreshes the grid', function () {
    $category = CatalogCategory::factory()->create();

    $this->actingAs(catalogAdmin())
        ->postJson(route('admin.catalog_category.update_status'), ['id' => $category->id, 'status' => config('core.disabled')])
        ->assertSuccessful()
        ->assertJsonPath('type', 'success');

    expect($category->fresh()->status)->toBe(2);
});

test('the attribute mapping screen saves own rows and lists inherited ones', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Apparel']);
    $leaf = CatalogCategory::factory()->child($root)->create(['name' => 'Kurtis']);
    $colour = Attribute::query()->create(['code' => 'colour-'.uniqid(), 'input_type' => 'select', 'custom_value' => 1, 'is_required' => 2]);
    $colour->translations()->forceCreate(['name' => 'Colour', 'locale' => app()->getLocale()]);
    $size = Attribute::query()->create(['code' => 'size-'.uniqid(), 'input_type' => 'select', 'custom_value' => 1, 'is_required' => 2]);
    $size->translations()->forceCreate(['name' => 'Size', 'locale' => app()->getLocale()]);
    CatalogCategoryAttribute::query()->create(['catalog_category_id' => $root->id, 'attribute_id' => $colour->id, 'is_variant_axis' => 1]);
    $admin = catalogAdmin();

    $this->actingAs($admin)->get(route('admin.catalog_category.attributes', [$leaf->id]))
        ->assertSuccessful()
        ->assertSee('Colour')
        ->assertSee('Apparel');

    $this->actingAs($admin)
        ->post(route('admin.catalog_category.attributes_save', [$leaf->id]), ['mappings' => [
            $size->id => ['is_variant_axis' => 1, 'is_filterable' => 1, 'sort_order' => 1],
        ]])
        ->assertRedirect(route('admin.catalog_category.attributes', [$leaf->id]))
        ->assertSessionHas('success');

    expect(CatalogCategoryAttribute::query()->where('catalog_category_id', $leaf->id)->sole())
        ->attribute_id->toBe($size->id)
        ->is_variant_axis->toBe(1)
        ->is_required->toBe(2);

    $this->actingAs($admin)
        ->post(route('admin.catalog_category.attributes_save', [$leaf->id]), ['mappings' => [999999 => ['is_filterable' => 1]]])
        ->assertSessionHasErrors('mappings');
});
