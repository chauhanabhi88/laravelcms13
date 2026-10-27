<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Storefront/tests/Helpers.php';

const API_PHONE = '+919844455566';

/**
 * Personal access tokens need signing keys and a personal access client for the
 * "customer" provider (created by the M08 migration; recreated here for an empty schema).
 */
function customerApiReady(): void
{
    if (! is_file(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    if (! DB::table('oauth_clients')->where('provider', 'customer')->where('revoked', 0)->exists()) {
        $columns = DB::getSchemaBuilder()->getColumnListing('oauth_clients');
        $row = ['id' => (string) Str::orderedUuid(), 'name' => 'Customer test client', 'secret' => Hash::make('secret'), 'provider' => 'customer', 'revoked' => 0, 'created_at' => now(), 'updated_at' => now()];
        $row += in_array('grant_types', $columns, true)
            ? ['grant_types' => json_encode(['personal_access']), 'redirect_uris' => '[]']
            : ['redirect' => '', 'personal_access_client' => 1, 'password_client' => 0];
        DB::table('oauth_clients')->insert(array_intersect_key($row, array_flip($columns)));
    }
}

test('the OTP API issues a token with the customer scope that unlocks the profile', function () {
    customerApiReady();
    $sms = customerFakeSms();

    $this->postJson('/api/v1/customer/otp/send', ['phone' => '98444 55566'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.masked', '+91 98XXX XX566');

    $response = $this->postJson('/api/v1/customer/otp/verify', [
        'phone' => '9844455566',
        'code' => $sms->lastOtpFor(API_PHONE),
        'device_name' => 'Pixel 8',
    ])->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.new_customer', true)
        ->assertJsonPath('data.customer.phone', API_PHONE);

    $token = $response->json('data.token');
    $customer = Customer::query()->where('phone', API_PHONE)->sole();

    expect($token)->toBeString()->not->toBeEmpty()
        ->and($response->json('data.expires_in'))->toBeGreaterThan(0)
        ->and($customer->tokens()->sole()->scopes)->toBe(['customer']);

    $this->withToken($token)->getJson('/api/v1/customer/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.needs_profile', true);
});

test('the profile requires a token', function () {
    $this->getJson('/api/v1/customer/profile')->assertUnauthorized();
});

test('a token without the customer scope is refused', function () {
    Passport::actingAs(Customer::factory()->create(), [], 'api');

    $this->getJson('/api/v1/customer/profile')->assertForbidden();
});

test('a wrong code is a 422 and issues no token', function () {
    customerLoginCode(API_PHONE, '111111');

    $this->postJson('/api/v1/customer/otp/verify', ['phone' => API_PHONE, 'code' => '222222', 'device_name' => 'Pixel 8'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(Customer::query()->where('phone', API_PHONE)->exists())->toBeFalse();
});

test('verify needs the device name', function () {
    $this->postJson('/api/v1/customer/otp/verify', ['phone' => API_PHONE, 'code' => '123456'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('device_name');
});

test('the profile can be updated through the API', function () {
    $customer = Customer::factory()->otpOnly(API_PHONE)->create();
    Passport::actingAs($customer, ['customer'], 'api');

    $this->putJson('/api/v1/customer/profile', ['first_name' => 'Nisha', 'gender' => 'female', 'is_reseller' => true, 'reseller_business_name' => 'Nisha Styles'])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Nisha')
        ->assertJsonPath('data.is_reseller', true);

    expect($customer->fresh())->isReseller()->toBeTrue()->reseller_business_name->toBe('Nisha Styles');
});

test('addresses can be managed through the API and stay private', function () {
    customerPincode('560001');
    $customer = Customer::factory()->withPhone(API_PHONE)->create();
    Passport::actingAs($customer, ['customer'], 'api');

    $id = $this->postJson('/api/v1/customer/addresses', customerAddressPayload())
        ->assertCreated()
        ->assertJsonPath('data.city.name', 'Bengaluru Urban')
        ->assertJsonPath('data.is_default', true)
        ->assertJsonPath('meta.serviceable', true)
        ->json('data.id');

    $this->getJson('/api/v1/customer/addresses')->assertOk()->assertJsonCount(1, 'data');
    $this->putJson("/api/v1/customer/addresses/{$id}", customerAddressPayload(['name' => 'Asha R']))->assertOk()->assertJsonPath('data.name', 'Asha R');
    $this->postJson('/api/v1/customer/addresses', customerAddressPayload(['pincode' => '799999']))->assertStatus(422)->assertJsonValidationErrors('pincode');

    $someoneElses = CustomerAddress::factory()->create();
    $this->putJson("/api/v1/customer/addresses/{$someoneElses->id}", customerAddressPayload())->assertNotFound();
    $this->deleteJson("/api/v1/customer/addresses/{$someoneElses->id}")->assertNotFound();

    $this->deleteJson("/api/v1/customer/addresses/{$id}")->assertOk();
    expect($customer->address()->count())->toBe(0);
});

test('the wishlist can be managed through the API', function () {
    $product = storefrontProduct();
    $customer = Customer::factory()->withPhone(API_PHONE)->create();
    Passport::actingAs($customer, ['customer'], 'api');

    $this->postJson('/api/v1/customer/wishlist', ['product_id' => $product->id])->assertCreated()->assertJsonPath('data.count', 1);
    $this->getJson('/api/v1/customer/wishlist')->assertOk()->assertJsonPath('data.0.id', $product->id)->assertJsonPath('meta.total', 1);
    $this->deleteJson("/api/v1/customer/wishlist/{$product->id}")->assertOk()->assertJsonPath('data.count', 0);
});

test('logout revokes the token', function () {
    customerApiReady();
    $customer = Customer::factory()->withPhone(API_PHONE)->create();
    $token = $customer->createToken('Pixel 8', ['customer'])->accessToken;

    $this->withToken($token)->postJson('/api/v1/customer/logout')->assertOk();

    expect($customer->tokens()->sole()->revoked)->toBeTrue();
});
