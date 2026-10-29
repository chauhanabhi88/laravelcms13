<?php

use Modules\Order\Models\SalesOrder;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    apiSetup();
});

dataset('public endpoints', [
    'app config' => ['core_api.app.config', [], 200],
    'category tree' => ['catalog_api.categories.tree', [], 200],
    'unknown category' => ['catalog_api.categories.show', ['slug' => 'no-such-category'], 404],
    'brands' => ['catalog_api.brands.index', [], 200],
    'home' => ['storefront_api.home', [], 200],
    'search' => ['storefront_api.search', [], 200],
    'autocomplete' => ['storefront_api.autocomplete', ['q' => 'ki'], 200],
    'recently viewed' => ['storefront_api.recently_viewed', ['ids' => '1,2'], 200],
    'unknown product' => ['storefront_api.product.show', ['id' => 999999999], 404],
    'similar of an unknown product' => ['storefront_api.product.similar', ['id' => 999999999], 404],
    'reviews of an unknown product' => ['review_api.index', ['id' => 999999999], 404],
    'unknown pincode' => ['directory_api.pincode.show', ['pincode' => '999999'], 404],
    'guest cart' => ['cart_api.show', [], 200],
    'guest coupon offers' => ['cart_api.offers', [], 200],
]);

dataset('customer endpoints', [
    'profile' => ['customer_api.profile.show'],
    'addresses' => ['customer_api.addresses.index'],
    'wishlist' => ['customer_api.wishlist.index'],
    'devices' => ['customer_api.devices.index'],
    'notifications' => ['notification_api.notifications.index'],
    'orders' => ['order_api.orders.index'],
    'returns' => ['returns_api.index'],
    'my reviews' => ['review_api.mine'],
    'earnings' => ['finance_api.earnings.show'],
]);

it('answers every public endpoint with the envelope', function (string $route, array $params, int $status) {
    $response = $this->getJson(route($route, $params))
        ->assertStatus($status)
        ->assertJsonStructure(['success', 'code', 'message'])
        ->assertJsonPath('success', $status < 400)
        ->assertJsonPath('code', $status);

    expect($response->json('message'))->toBeString()->not->toBe('');
})->with('public endpoints');

it('answers every customer endpoint with the envelope', function (string $route) {
    apiCustomer();

    $this->getJson(route($route))
        ->assertOk()
        ->assertJsonStructure(['success', 'code', 'message'])
        ->assertJsonPath('success', true)
        ->assertJsonPath('code', 200);
})->with('customer endpoints');

it('answers an order, its tracking and its returnable items with the envelope', function () {
    $customer = apiCustomer();
    $order = SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create();

    foreach (['order_api.orders.show', 'logistics_api.orders.tracking', 'returns_api.returnable_items'] as $route) {
        $this->getJson(route($route, ['orderNo' => $order->order_no]))
            ->assertOk()
            ->assertJsonStructure(['success', 'code', 'message', 'data'])
            ->assertJsonPath('code', 200);
    }
});

it('keeps success, code and message when a controller returns a bare resource', function () {
    apiCustomer();

    $this->getJson(route('customer_api.addresses.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('code', 200)
        ->assertJsonStructure(['data']);
});

it('adds meta.pagination next to the older paging keys', function () {
    $customer = apiCustomer();
    SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->count(3)->create();

    $this->getJson(route('order_api.orders.index', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.pagination.current_page', 1)
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.pagination.has_more', true);
});

it('pages orders by cursor when asked to', function () {
    $customer = apiCustomer();
    SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->count(3)->create();

    $first = $this->getJson(route('order_api.orders.index', ['cursor' => '', 'per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.has_more', true)
        ->assertJsonPath('meta.total', 3);

    $cursor = $first->json('meta.pagination.next_cursor');
    expect($cursor)->toBeString()->not->toBe('');

    $second = $this->getJson(route('order_api.orders.index', ['cursor' => $cursor, 'per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.has_more', false)
        ->assertJsonPath('meta.pagination.next_cursor', null);

    expect(collect($first->json('data'))->pluck('order_no')->all())
        ->not->toContain($second->json('data.0.order_no'));
});

it('caps per_page at 50', function () {
    apiCustomer();

    $this->getJson(route('order_api.orders.index', ['per_page' => 500]))
        ->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 50);
});

it('never answers an API call with an HTML page', function (string $method, string $uri) {
    // No Accept header on purpose: an app that forgets it must still get JSON.
    $response = $this->call($method, $uri);

    expect($response->headers->get('Content-Type'))->toContain('application/json');
    $response->assertJsonPath('success', false);
})->with([
    'unknown path' => ['GET', '/api/v1/no/such/endpoint'],
    'wrong method' => ['DELETE', '/api/v1/home'],
    'unauthenticated' => ['GET', '/api/v1/orders'],
    'invalid input' => ['POST', '/api/v1/cart/items'],
]);

it('answers 405 with the Allow header and the envelope', function () {
    $this->deleteJson('/api/v1/home')
        ->assertStatus(405)
        ->assertHeader('Allow')
        ->assertJsonPath('code', 405)
        ->assertJsonPath('success', false);
});
