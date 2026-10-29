<?php

use Laravel\Passport\Token;
use Modules\Customer\Models\Customer;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    apiSetup();
});

dataset('protected endpoints', [
    ['GET', '/api/v1/customer/profile'],
    ['GET', '/api/v1/customer/addresses'],
    ['GET', '/api/v1/customer/wishlist'],
    ['GET', '/api/v1/customer/devices'],
    ['GET', '/api/v1/customer/notifications'],
    ['GET', '/api/v1/customer/earnings'],
    ['GET', '/api/v1/orders'],
    ['POST', '/api/v1/checkout/summary'],
    ['POST', '/api/v1/checkout/place'],
    ['GET', '/api/v1/returns'],
    ['GET', '/api/v1/reviews/mine'],
    ['POST', '/api/v1/orders/ORD-0000-000000/payment'],
    ['GET', '/api/v1/orders/ORD-0000-000000/tracking'],
    ['GET', '/api/v1/orders/ORD-0000-000000/invoice/1'],
]);

it('rejects a missing token with code 4011', function (string $method, string $uri) {
    $this->json($method, $uri)
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 4011)
        ->assertJsonPath('error', 'token_expired_or_invalid');
})->with('protected endpoints');

it('rejects a malformed token with code 4011, even without an Accept header', function () {
    $response = $this->call('GET', '/api/v1/customer/profile', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-real-token']);

    $response->assertUnauthorized()->assertJsonPath('code', 4011);
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});

it('rejects a token without the customer scope', function () {
    apiCustomer(scopes: ['admin']);

    $this->getJson(route('customer_api.profile.show'))
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 403);
});

it('signs in with OTP, lists the device, and signs devices out one by one', function () {
    $first = apiLogin('9844400111', 'Pixel 8');
    $second = apiToken(Customer::query()->where('phone', '+919844400111')->firstOrFail(), 'iPhone 15');

    $devices = $this->withToken($first)->getJson('/api/v1/customer/devices')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    $byName = collect($devices)->keyBy('name');
    expect($byName->keys()->sort()->values()->all())->toBe(['Pixel 8', 'iPhone 15'])
        ->and($byName['Pixel 8']['current'])->toBeTrue()
        ->and($byName['iPhone 15']['current'])->toBeFalse()
        ->and($byName['Pixel 8']['scopes'])->toBe(['customer']);

    // Sign the second device out from the first one.
    $this->withToken($first)->deleteJson('/api/v1/customer/devices/'.$byName['iPhone 15']['id'])
        ->assertOk()
        ->assertJsonPath('data.current', false);

    app('auth')->forgetGuards();

    $this->withToken($second)->getJson('/api/v1/customer/profile')->assertUnauthorized()->assertJsonPath('code', 4011);
    $this->withToken($first)->getJson('/api/v1/customer/profile')->assertOk();
    $this->withToken($first)->getJson('/api/v1/customer/devices')->assertOk()->assertJsonCount(1, 'data');
});

it('gives sign-in tokens a one-year lifetime and revokes them on logout', function () {
    $token = apiLogin('9844400222', 'Pixel 8');

    $this->withToken($token)->getJson('/api/v1/customer/profile')->assertOk();
    $customer = Customer::query()->where('phone', '+919844400222')->firstOrFail();
    $row = Token::query()->where('user_id', $customer->id)->firstOrFail();

    expect((int) now()->diffInDays($row->expires_at, true))->toBeGreaterThanOrEqual(364);

    $this->withToken($token)->postJson('/api/v1/customer/logout')->assertOk();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/customer/profile')->assertUnauthorized()->assertJsonPath('code', 4011);
    expect($row->refresh()->revoked)->toBeTrue();
});

it('answers 404 for a device that is not theirs', function () {
    $mine = apiToken(Customer::factory()->withPhone()->create(), 'Pixel 8');
    apiToken(Customer::factory()->withPhone()->create(), 'Galaxy S24');
    $foreignId = Token::query()->where('name', 'Galaxy S24')->value('id');

    $this->withToken($mine)->deleteJson('/api/v1/customer/devices/'.$foreignId)
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 404);
});
