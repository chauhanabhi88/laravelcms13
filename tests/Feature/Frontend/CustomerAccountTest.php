<?php

use Illuminate\Support\Facades\Hash;
use Modules\Customer\Database\Factories\CustomerFactory;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;

test('a customer can log in through the session guard', function () {
    $customer = Customer::factory()->create();

    $this->post(frontRoute('customer.loginpost'), ['email' => $customer->email, 'password' => CustomerFactory::PASSWORD])
        ->assertRedirect(frontRoute('customer.myaccount'));

    $this->assertAuthenticatedAs($customer, 'customer');
});

test('wrong credentials show a field error and keep the email', function () {
    $customer = Customer::factory()->create();

    $this->from(frontRoute('customer.login'))
        ->post(frontRoute('customer.loginpost'), ['email' => $customer->email, 'password' => 'not-the-password'])
        ->assertRedirect(frontRoute('customer.login'))
        ->assertSessionHasErrors('email')
        ->assertSessionHasInput('email', $customer->email);

    $this->assertGuest('customer');
});

test('guests are redirected to the customer login, not the admin login', function (string $route) {
    $this->get(frontRoute($route))->assertRedirect(frontRoute('customer.login'));
})->with(['customer.myaccount', 'customer.profile.edit', 'customer.change-password', 'customer.addresses.index']);

test('account pages render for a logged in customer', function (string $route) {
    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(frontRoute($route))
        ->assertSuccessful();
})->with(['customer.myaccount', 'customer.profile.edit', 'customer.change-password', 'customer.addresses.index']);

test('a logged in customer is sent away from the login and signup pages', function () {
    $this->actingAs(Customer::factory()->create(), 'customer');

    $this->get(frontRoute('customer.login'))->assertRedirect(frontRoute('customer.myaccount'));
    $this->get(frontRoute('customer.signup'))->assertRedirect(frontRoute('customer.myaccount'));
});

test('a visitor can sign up', function () {
    $this->post(frontRoute('customer.signuppost'), [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'contact_number' => '5551234567',
        'password' => 'Engine#1843',
        'password_confirmation' => 'Engine#1843',
    ])->assertRedirect(frontRoute('customer.login'))->assertSessionHas('success');

    expect(Customer::where('email', 'ada@example.test')->exists())->toBeTrue();
});

test('sign up is validated', function () {
    $this->from(frontRoute('customer.signup'))
        ->post(frontRoute('customer.signuppost'), ['email' => 'not-an-email', 'password' => 'weak', 'password_confirmation' => 'other'])
        ->assertRedirect(frontRoute('customer.signup'))
        ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'password', 'contact_number']);
});

test('a customer can update their profile', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.update'), [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => $customer->email,
            'contact_number' => '5550001111',
        ])
        ->assertRedirect(frontRoute('customer.myaccount'));

    expect($customer->fresh())->first_name->toBe('Grace')->last_name->toBe('Hopper');
});

test('a customer can change their password with the current one', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.update-password'), [
            'old_password' => CustomerFactory::PASSWORD,
            'password' => 'Brand#New99',
            'password_confirmation' => 'Brand#New99',
        ])
        ->assertRedirect(frontRoute('customer.myaccount'));

    expect(Hash::check('Brand#New99', $customer->fresh()->password))->toBeTrue();
});

test('changing the password requires the correct current password', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.update-password'), [
            'old_password' => 'wrong-password',
            'password' => 'Brand#New99',
            'password_confirmation' => 'Brand#New99',
        ])
        ->assertSessionHasErrors('old_password');

    expect(Hash::check(CustomerFactory::PASSWORD, $customer->fresh()->password))->toBeTrue();
});

test('the password route requires authentication', function () {
    $this->post(frontRoute('customer.update-password'), [])->assertRedirect(frontRoute('customer.login'));
});

test('logging out requires a post request', function () {
    $this->actingAs(Customer::factory()->create(), 'customer');

    $this->get('/customer/logout')->assertRedirect(frontRoute('homepage'));
    $this->assertAuthenticated('customer');

    $this->post(frontRoute('customer.logout'))->assertRedirect();
    $this->assertGuest('customer');
});

test('a customer can add an address and make it the default', function () {
    $customer = Customer::factory()->create();
    $existing = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_default_address' => config('customer.is_default_address.yes')]);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.addresses.store'), ['address' => [
            'tag' => 'Office',
            'street_name' => '1 Main Street',
            'building' => 'Tower A',
            'unit_no' => '12',
            'postal_code' => '123456',
            'is_default_address' => '1',
        ]])
        ->assertRedirect(frontRoute('customer.addresses.index'));

    $created = $customer->address()->where('tag', 'Office')->sole();

    expect((int) $created->is_default_address)->toBe((int) config('customer.is_default_address.yes'))
        ->and((int) $existing->fresh()->is_default_address)->toBe((int) config('customer.is_default_address.no'));
});

test('address fields are validated', function () {
    $this->actingAs(Customer::factory()->create(), 'customer')
        ->post(frontRoute('customer.addresses.store'), ['address' => ['postal_code' => 'abc']])
        ->assertSessionHasErrors(['address.street_name', 'address.building', 'address.unit_no', 'address.postal_code', 'address.tag']);
});

test('a customer cannot change or delete another customer\'s address', function () {
    $address = CustomerAddress::factory()->create();

    $this->actingAs(Customer::factory()->create(), 'customer');

    $this->put(frontRoute('customer.addresses.update', [$address->id]), ['address' => [
        'tag' => 'Hijacked', 'street_name' => 'x', 'building' => 'x', 'unit_no' => '1', 'postal_code' => '1',
    ]])->assertNotFound();
    $this->delete(frontRoute('customer.addresses.destroy', [$address->id]))->assertNotFound();

    expect($address->fresh())->not->toBeNull()->tag->not->toBe('Hijacked');
});
