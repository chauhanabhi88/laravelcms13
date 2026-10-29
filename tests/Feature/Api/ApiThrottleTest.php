<?php

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    apiSetup();
});

it('limits OTP requests per phone number with code 4291 and Retry-After', function () {
    $limit = (int) config('core.api_limits.auth_phone');

    for ($i = 0; $i < $limit; $i++) {
        $this->postJson('/api/v1/customer/otp/send', ['phone' => '9844411000'])->assertOk();
    }

    $this->postJson('/api/v1/customer/otp/send', ['phone' => '9844411000'])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 4291)
        ->assertJsonStructure(['message', 'retry_after']);

    // Another number from the same address is not affected by that phone's limit.
    $this->postJson('/api/v1/customer/otp/send', ['phone' => '9844411001'])->assertOk();
});

it('limits OTP requests per IP address', function () {
    config(['core.api_limits.auth_ip' => 3, 'core.api_limits.auth_phone' => 50]);

    foreach (['9844411101', '9844411102', '9844411103'] as $phone) {
        $this->postJson('/api/v1/customer/otp/send', ['phone' => $phone])->assertOk();
    }

    $this->postJson('/api/v1/customer/otp/send', ['phone' => '9844411104'])
        ->assertStatus(429)
        ->assertJsonPath('code', 4291);
});

it('answers a throttled general request with the envelope and Retry-After', function () {
    config(['core.api_limits.api' => 2]);

    $this->getJson(route('catalog_api.brands.index'))->assertOk();
    $this->getJson(route('catalog_api.categories.tree'))->assertOk();

    $this->getJson(route('catalog_api.brands.index'))
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 429)
        ->assertJsonStructure(['message', 'retry_after']);
});

it('counts the general limit per customer, not per address', function () {
    config(['core.api_limits.api' => 2]);
    $customer = apiCustomer();

    $this->getJson(route('customer_api.profile.show'))->assertOk();
    $this->getJson(route('customer_api.profile.show'))->assertOk();
    $this->getJson(route('customer_api.profile.show'))->assertStatus(429)->assertJsonPath('code', 429);

    expect($customer->exists)->toBeTrue();
});

it('gives search its own, larger budget', function () {
    config(['core.api_limits.api' => 1, 'core.api_limits.search' => 3]);

    foreach (range(1, 3) as $ignored) {
        $this->getJson(route('storefront_api.search'))->assertOk();
    }

    $this->getJson(route('storefront_api.search'))->assertStatus(429)->assertJsonPath('code', 429);
});
