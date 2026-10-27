<?php

use Modules\Catalog\Models\CatalogCategory;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Models\ProductCollection;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->admin = storefrontAdmin();
});

test('the home section and collection grids render', function () {
    HomeSection::factory()->ofType('trust_strip')->create(['title' => 'Promise Strip']);
    ProductCollection::factory()->create(['name' => 'Festive Picks']);

    $this->actingAs($this->admin)->get(route('admin.home_section.index'))->assertSuccessful()->assertSee(trans('storefront::home_section.titles.home_section'));
    $this->actingAs($this->admin)->postJson(route('admin.home_section.filters'))->assertSuccessful()->assertJsonPath('type', 'success');
    $this->actingAs($this->admin)->get(route('admin.product_collection.index'))->assertSuccessful();
    $this->actingAs($this->admin)->postJson(route('admin.product_collection.filters'))->assertSuccessful()->assertJsonPath('type', 'success');
});

test('an admin creates a scheduled category tiles section', function () {
    $category = CatalogCategory::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.home_section.create'))->assertSuccessful();

    $this->actingAs($this->admin)->post(route('admin.home_section.store'), [
        'type' => 'category_tiles',
        'category_ids' => [$category->id],
        'en' => ['title' => 'Shop the Look', 'subtitle' => 'Fresh picks'],
        'sort_order' => 15,
        'status' => 1,
        'starts_at' => '01-11-2026',
        'ends_at' => '30-11-2026',
    ])->assertRedirect(route('admin.home_section.index'))->assertSessionHas('success');

    $section = HomeSection::query()->with('translations')->latest('id')->firstOrFail();

    expect($section->type)->toBe('category_tiles')
        ->and(array_map('intval', $section->config['category_ids']))->toBe([$category->id])
        ->and($section->title)->toBe('Shop the Look')
        ->and($section->starts_at->toDateTimeString())->toBe('2026-11-01 00:00:00')
        ->and($section->ends_at->toDateTimeString())->toBe('2026-11-30 23:59:59');

    $this->actingAs($this->admin)->get(route('admin.home_section.edit', [$section->id]))->assertSuccessful()->assertSee('Shop the Look');
});

test('the section type decides which setting is required', function () {
    $this->actingAs($this->admin)->post(route('admin.home_section.store'), ['type' => 'collection', 'status' => 1])
        ->assertSessionHasErrors('collection_id');

    $this->actingAs($this->admin)->post(route('admin.home_section.store'), ['type' => 'hero', 'status' => 1, 'banner_group_code' => 'missing-group'])
        ->assertSessionHasErrors('banner_group_code');

    $this->actingAs($this->admin)->post(route('admin.home_section.store'), ['type' => 'trust_strip', 'starts_at' => '10-11-2026', 'ends_at' => '01-11-2026'])
        ->assertSessionHasErrors('ends_at');
});

test('sections move up and down and can be switched off', function () {
    HomeSection::query()->get()->each->delete();
    $first = HomeSection::factory()->create(['sort_order' => 10]);
    $second = HomeSection::factory()->create(['sort_order' => 20]);

    $this->actingAs($this->admin)->postJson(route('admin.home_section.reorder'), ['id' => $second->id, 'direction' => 'up'])->assertSuccessful();

    expect($second->fresh()->sort_order)->toBeLessThan($first->fresh()->sort_order);

    $this->actingAs($this->admin)->postJson(route('admin.home_section.update_status'), ['id' => $first->id, 'status' => 2])->assertSuccessful();
    expect($first->fresh()->isEnabled())->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.home_section.delete', [$first->id]))->assertSessionHas('success');
    expect(HomeSection::query()->find($first->id))->toBeNull();
});

test('an admin creates rule and manual collections', function () {
    $category = CatalogCategory::factory()->create();
    $product = storefrontProduct();

    $this->actingAs($this->admin)->get(route('admin.product_collection.create'))->assertSuccessful();

    $this->actingAs($this->admin)->post(route('admin.product_collection.store'), [
        'slug' => 'budget-kurtis',
        'type' => 'rule',
        'sort' => 'price_asc',
        'status' => 1,
        'en' => ['name' => 'Budget Kurtis'],
        'rules' => ['category_ids' => [$category->id], 'max_price' => '299', 'min_discount' => '', 'min_rating' => '4'],
    ])->assertRedirect(route('admin.product_collection.index'))->assertSessionHas('success');

    $rule = ProductCollection::query()->where('slug', 'budget-kurtis')->firstOrFail();
    expect($rule->rules)->toEqual(['category_ids' => [$category->id], 'max_price' => 299, 'min_rating' => 4])
        ->and($rule->name)->toBe('Budget Kurtis');

    $this->actingAs($this->admin)->post(route('admin.product_collection.store'), [
        'slug' => 'editors-picks', 'type' => 'manual', 'sort' => 'popularity', 'status' => 1,
        'en' => ['name' => 'Editors Picks'], 'product_ids' => $product->id.', 999999',
    ])->assertSessionHasErrors('product_ids');

    $this->actingAs($this->admin)->post(route('admin.product_collection.store'), [
        'slug' => 'editors-picks', 'type' => 'manual', 'sort' => 'popularity', 'status' => 1,
        'en' => ['name' => 'Editors Picks'], 'product_ids' => (string) $product->id,
    ])->assertSessionHas('success');

    expect(ProductCollection::query()->where('slug', 'editors-picks')->firstOrFail()->manualProductIds())->toBe([$product->id]);

    $this->actingAs($this->admin)->get(route('admin.product_collection.edit', [$rule->id]))->assertSuccessful()->assertSee('Budget Kurtis');
});

test('storefront screens need their permissions', function () {
    $viewer = storefrontAdmin(['admin.home_section.index']);

    $this->actingAs($viewer)->get(route('admin.home_section.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('admin.home_section.create'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.product_collection.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.search_report.index'))->assertForbidden();
});
