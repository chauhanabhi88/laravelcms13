<?php

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    apiSetup();
});

it('answers invalid input with 422 and the errors per field', function () {
    $this->postJson('/api/v1/customer/otp/send', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 422)
        ->assertJsonStructure(['message', 'errors' => ['phone']]);
});

it('keeps the field messages as lists of strings', function () {
    $response = $this->postJson('/api/v1/cart/items', [])->assertUnprocessable();

    expect($response->json('errors.variant_id'))->toBeArray()->each->toBeString();
});

it('validates authenticated requests the same way', function () {
    apiCustomer();

    $this->postJson(route('order_api.checkout.summary'), [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 422)
        ->assertJsonValidationErrors(['address_id']);

    $this->postJson(route('customer_api.addresses.store'), ['pincode' => 'abc'])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['pincode']);
});

it('answers 422 as JSON even when the client sends no Accept header', function () {
    $response = $this->call('POST', '/api/v1/cart/items');

    $response->assertStatus(422)->assertJsonPath('code', 422);
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});
