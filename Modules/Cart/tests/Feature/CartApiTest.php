<?php

use Laravel\Passport\Passport;
use Modules\Cart\Models\Cart;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\CustomerAuthService;

require_once __DIR__.'/../Helpers.php';

test('a guest adds over the API and keeps the cart with the returned token', function () {
    $variant = cartVariant(['price' => '499.00', 'mrp' => '999.00']);

    $response = $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])
        ->assertCreated()
        ->assertJsonPath('data.cart.item_count', 2)
        ->assertJsonPath('data.totals.subtotal', '998.00')
        ->assertJsonPath('data.totals.you_save', '1000.00')
        ->assertJsonPath('data.cart.groups.0.lines.0.variant_id', $variant->id);

    $token = $response->json('meta.cart_token');
    expect($token)->toHaveLength(40);

    $this->getJson('/api/v1/cart', ['X-Cart-Token' => $token])
        ->assertSuccessful()
        ->assertJsonPath('data.cart.item_count', 2);

    $this->getJson('/api/v1/cart')->assertJsonPath('data.cart.item_count', 0);
});

test('a customer token reaches the customer cart and can update and delete lines', function () {
    $customer = Customer::factory()->create();
    $result = cartService()->add(customerOwner($customer), cartVariant()->id, 1);
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->patchJson('/api/v1/cart/items/'.$result->item->id, ['quantity' => 4], ['Authorization' => 'Bearer test'])
        ->assertSuccessful()
        ->assertJsonPath('data.cart.item_count', 4);

    $this->deleteJson('/api/v1/cart/items/'.$result->item->id, [], ['Authorization' => 'Bearer test'])
        ->assertSuccessful()
        ->assertJsonPath('data.cart.item_count', 0);
});

test('API item routes stay inside the caller cart', function () {
    $item = cartService()->add(guestOwner(), cartVariant()->id, 1)->item;
    $other = guestCartWith([cartVariant()]);

    $this->patchJson('/api/v1/cart/items/'.$item->id, ['quantity' => 2], ['X-Cart-Token' => $other->token])->assertNotFound();
});

test('API buy now needs a customer', function () {
    $variant = cartVariant();

    $this->postJson('/api/v1/cart/buy-now', ['variant_id' => $variant->id])->assertUnauthorized();

    $customer = Customer::factory()->create();
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->postJson('/api/v1/cart/buy-now', ['variant_id' => $variant->id], ['Authorization' => 'Bearer test'])
        ->assertCreated()
        ->assertJsonPath('data.cart.type', Cart::TYPE_BUY_NOW)
        ->assertJsonPath('data.cart.item_count', 1);
});

test('API errors use the error envelope', function () {
    $this->postJson('/api/v1/cart/items', ['variant_id' => cartVariant(['stock' => 0])->id])
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'code' => '422', 'message' => 'Sorry, this item is out of stock.']);
});
