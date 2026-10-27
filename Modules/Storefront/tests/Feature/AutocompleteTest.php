<?php

use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Storefront\Models\SearchTermStat;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

function suggestionGroups(string $q): array
{
    return collect(test()->getJson(route('storefront_api.autocomplete', ['q' => $q]))->assertSuccessful()->json('data.groups'))
        ->mapWithKeys(fn (array $group): array => [$group['key'] => collect($group['items'])->pluck('label')->all()])
        ->all();
}

test('suggestions group categories, brands, product names and trending terms', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Ethnic Wear']);
    CatalogCategory::factory()->child($root)->create(['name' => 'Kurtis']);
    CatalogBrand::factory()->create(['name' => 'Kurtaland']);
    storefrontProduct(['name' => 'Printed Cotton Kurti']);
    SearchTermStat::query()->create(['term' => 'kurti sets', 'searches_7d' => 30, 'searches_30d' => 80, 'avg_results' => 12]);
    SearchTermStat::query()->create(['term' => 'kurta', 'searches_7d' => 0, 'searches_30d' => 5, 'avg_results' => 3]);

    $groups = suggestionGroups('kur');

    expect($groups['categories'])->toBe(['Kurtis'])
        ->and($groups['brands'])->toBe(['Kurtaland'])
        ->and($groups['products'])->toBe(['Printed Cotton Kurti'])
        ->and($groups['trending'])->toBe(['kurti sets']);
});

test('a category suggestion carries its breadcrumb and listing URL', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Ethnic Wear']);
    $leaf = CatalogCategory::factory()->child($root)->create(['name' => 'Kurtis', 'slug' => 'kurtis']);

    $item = $this->getJson(route('storefront_api.autocomplete', ['q' => 'kurt']))->json('data.groups.0.items.0');

    expect($item)->toMatchArray(['label' => 'Kurtis', 'sublabel' => 'Ethnic Wear', 'url' => frontRoute('storefront.category', [$leaf->slug]), 'type' => 'category']);
});

test('fewer than two characters return trending terms only', function () {
    storefrontProduct(['name' => 'Kurti']);
    SearchTermStat::query()->create(['term' => 'saree', 'searches_7d' => 5, 'searches_30d' => 9, 'avg_results' => 7]);

    expect(suggestionGroups('k'))->toBe(['trending' => ['saree']])
        ->and(suggestionGroups(''))->toBe(['trending' => ['saree']]);
});

test('disabled categories are never suggested', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Hidden Root', 'status' => 2]);
    CatalogCategory::factory()->child($root)->create(['name' => 'Hidden Kurtis']);

    expect(suggestionGroups('hidden'))->not->toHaveKey('categories');
});

test('the autocomplete API is throttled to 30 requests per 10 seconds', function () {
    foreach (range(1, 30) as $attempt) {
        $this->getJson(route('storefront_api.autocomplete', ['q' => 'ku']))->assertSuccessful();
    }

    $this->getJson(route('storefront_api.autocomplete', ['q' => 'ku']))->assertTooManyRequests();
});

test('the header search box is wired to the autocomplete API', function () {
    $this->get(frontRoute('storefront.search'))
        ->assertSee("suggestUrl: '".str_replace('/', '\/', route('storefront_api.autocomplete'))."'", false);
});
