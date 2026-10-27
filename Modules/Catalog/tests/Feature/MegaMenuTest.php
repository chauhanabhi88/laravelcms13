<?php

use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Catalog\Support\CatalogMegaMenuProvider;
use Modules\Theme\Contracts\MegaMenuProvider;

test('the catalog provides the storefront mega-menu', function () {
    expect(app(MegaMenuProvider::class))->toBeInstanceOf(CatalogMegaMenuProvider::class);
});

test('the home header renders the top-level categories with their subcategories', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Ethnic Wear', 'slug' => 'ethnic-wear']);
    $sarees = CatalogCategory::factory()->child($root)->create(['name' => 'Sarees', 'slug' => 'sarees']);
    CatalogCategory::factory()->child($sarees)->create(['name' => 'Silk Sarees', 'slug' => 'silk-sarees']);
    CatalogCategory::factory()->hiddenFromMenu()->create(['name' => 'Backstage', 'slug' => 'backstage']);

    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertSee('Ethnic Wear')
        ->assertSee('Silk Sarees')
        ->assertSee('href="'.CatalogMegaMenuProvider::categoryUrl('sarees').'"', false)
        ->assertDontSee('Backstage');
});

test('renaming a category refreshes the cached mega-menu', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Gadgets', 'slug' => 'gadgets']);

    $this->get(frontRoute('homepage'))->assertSee('Gadgets');

    app(CategoryTreeService::class)->update($root, [app()->getLocale() => ['name' => 'Gadgets & Electronics']]);

    $this->get(frontRoute('homepage'))->assertSee('Gadgets &amp; Electronics', false);
});
