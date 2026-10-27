<?php

use Illuminate\Support\Facades\Notification;
use Modules\Block\Models\Block;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Models\ProductCollection;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    // Start from an empty home page (the migration seeds default sections).
    HomeSection::query()->get()->each->delete();
});

test('the home route keeps its name and renders the default sections that have content', function () {
    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertViewIs('storefront::frontend.home');
});

test('sections render in sort order and inactive, expired or future ones are hidden', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Footwear Finds']);
    HomeSection::factory()->ofType('category_tiles', ['category_ids' => [$root->id]])->create(['title' => 'Second Tiles', 'sort_order' => 20]);
    HomeSection::factory()->ofType('trust_strip')->create(['sort_order' => 10]);
    HomeSection::factory()->ofType('category_tiles', ['category_ids' => [$root->id]])->disabled()->create(['title' => 'Disabled Tiles']);
    HomeSection::factory()->ofType('category_tiles', ['category_ids' => [$root->id]])->expired()->create(['title' => 'Expired Tiles']);
    HomeSection::factory()->ofType('category_tiles', ['category_ids' => [$root->id]])->scheduled()->create(['title' => 'Future Tiles']);

    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertSeeInOrder([trans('theme::frontend.trust.value_prices'), 'Second Tiles', 'Footwear Finds'])
        ->assertDontSee('Disabled Tiles')
        ->assertDontSee('Expired Tiles')
        ->assertDontSee('Future Tiles');
});

test('a collection section shows its products and links to the collection', function () {
    storefrontProduct(['name' => 'Budget Tee'], ['price' => '149', 'mrp' => '299']);
    storefrontProduct(['name' => 'Premium Tee'], ['price' => '899', 'mrp' => '999']);
    $collection = ProductCollection::factory()->create(['name' => 'Budget Picks', 'rules' => ['max_price' => 199]]);
    HomeSection::factory()->ofType('collection', ['collection_id' => $collection->id, 'limit' => 12])->create();

    $this->get(frontRoute('homepage'))
        ->assertSee('Budget Picks')
        ->assertSee('Budget Tee')
        ->assertDontSee('Premium Tee')
        ->assertSee(frontRoute('storefront.collection', [$collection->slug]), false);
});

test('a content block section renders the CMS block and empty sections are skipped', function () {
    $block = Block::query()->create(['slug' => 'home_story_'.uniqid(), 'is_enabled' => 1, 'title' => 'Our Story', 'content' => '<p>Made with care</p>']);
    HomeSection::factory()->ofType('html_block', ['block_code' => $block->slug])->create();
    HomeSection::factory()->ofType('hero', ['banner_group_code' => 'no-such-group'])->create(['title' => 'Empty Hero']);

    $this->get(frontRoute('homepage'))
        ->assertSee('Our Story')
        ->assertSee('Made with care', false)
        ->assertDontSee('Empty Hero');
});

test('the Picked for You grid lists products with filters and answers partial requests', function () {
    storefrontProduct(['name' => 'Home Kurti']);
    HomeSection::factory()->ofType('products_for_you')->create(['title' => 'Picked for You']);

    $this->get(frontRoute('homepage'))
        ->assertSee('Picked for You')
        ->assertSee('Home Kurti')
        ->assertSee('data-listing-filters', false);

    $this->getJson(frontRoute('homepage').'?partial=1&sort=new')
        ->assertSuccessful()
        ->assertJsonPath('total', 1);
});

test('partial requests are not found without a product grid section', function () {
    $this->getJson(frontRoute('homepage').'?partial=grid')->assertNotFound();
});

test('saving a section refreshes the cached home page', function () {
    $section = HomeSection::factory()->ofType('trust_strip')->create(['sort_order' => 10]);
    // Kept out of the header mega-menu, so only the tiles section can show it.
    $root = CatalogCategory::factory()->create(['name' => 'Fresh Tiles Category', 'show_in_menu' => 2]);

    $this->get(frontRoute('homepage'))->assertDontSee('Fresh Tiles Category');

    HomeSection::factory()->ofType('category_tiles', ['category_ids' => [$root->id]])->create(['sort_order' => 20]);
    $this->get(frontRoute('homepage'))->assertSee('Fresh Tiles Category');

    $section->update(['status' => 2]);
    $this->get(frontRoute('homepage'))->assertDontSee(trans('theme::frontend.trust.value_prices'));
});

test('the home API lists the active sections with their content', function () {
    storefrontProduct(['name' => 'Budget Tee'], ['price' => '149', 'mrp' => '299']);
    $collection = ProductCollection::factory()->create(['rules' => ['max_price' => 199]]);
    HomeSection::factory()->ofType('collection', ['collection_id' => $collection->id])->create(['sort_order' => 1]);
    HomeSection::factory()->ofType('products_for_you')->create(['sort_order' => 2]);

    $this->getJson(route('storefront_api.home'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.type', 'collection')
        ->assertJsonPath('data.0.data.products.0.name', 'Budget Tee')
        ->assertJsonPath('data.1.type', 'products_for_you')
        ->assertJsonPath('data.1.data.endpoint', route('storefront_api.search'));
});
