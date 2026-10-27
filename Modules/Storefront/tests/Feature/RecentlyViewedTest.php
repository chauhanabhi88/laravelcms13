<?php

use Illuminate\Support\Facades\Notification;
use Modules\Customer\Models\Customer;
use Modules\Product\Services\ProductModerationService;
use Modules\Storefront\Models\CustomerRecentView;
use Modules\Storefront\Services\RecentlyViewedService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

test('a guest gets cards for the ids kept in the browser, visible products only, in order', function () {
    $first = storefrontProduct(['name' => 'First Viewed']);
    $second = storefrontProduct(['name' => 'Second Viewed']);
    $paused = storefrontProduct(['name' => 'Paused Product']);
    app(ProductModerationService::class)->pause($paused);

    $response = $this->getJson(frontRoute('storefront.recently_viewed').'?ids='.$second->id.','.$paused->id.','.$first->id.',abc')
        ->assertSuccessful()
        ->assertJsonPath('count', 2);

    expect($response->json('html'))->toContain('Second Viewed', 'First Viewed')->not->toContain('Paused Product')
        ->and(strpos($response->json('html'), 'Second Viewed'))->toBeLessThan(strpos($response->json('html'), 'First Viewed'));

    $this->getJson(route('storefront_api.recently_viewed', ['ids' => $first->id]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $first->id);
});

test('a customer\'s history is recorded, de-duplicated and trimmed', function () {
    config(['storefront.recent_views_keep' => 3]);
    $customer = Customer::factory()->create();
    $products = collect(range(1, 4))->map(fn (int $index) => storefrontProduct(['name' => 'Viewed '.$index]));
    $service = app(RecentlyViewedService::class);

    foreach ($products as $offset => $product) {
        $this->travel($offset)->minutes();
        $service->record($customer->id, $product->id);
    }
    $this->travel(5)->minutes();
    $service->record($customer->id, $products[1]->id);

    expect($service->ids($customer->id))->toBe([$products[1]->id, $products[3]->id, $products[2]->id])
        ->and(CustomerRecentView::query()->where('customer_id', $customer->id)->count())->toBe(3);

    $this->actingAs($customer, 'customer')
        ->getJson(frontRoute('storefront.recently_viewed'))
        ->assertSuccessful()
        ->assertJsonPath('count', 3);
});
