<?php

use Illuminate\Support\Facades\Hash;
use Modules\Customer\Models\Customer;

require_once __DIR__.'/../Helpers.php';

test('a reseller can switch reseller mode on and off', function () {
    $customer = Customer::factory()->create();
    $this->actingAs($customer, 'customer');

    $this->get(frontRoute('customer.reseller.edit'))->assertSuccessful()->assertSee(trans('customer::customer_front.reseller.toggle'));

    $this->post(frontRoute('customer.reseller.update'), ['is_reseller' => '1', 'reseller_business_name' => '  Asha Collections '])
        ->assertRedirect(frontRoute('customer.reseller.edit'))
        ->assertSessionHas('success', trans('customer::customer_front.reseller.enabled'));

    expect($customer->fresh())->isReseller()->toBeTrue()->reseller_business_name->toBe('Asha Collections');

    $this->post(frontRoute('customer.reseller.update'), ['is_reseller' => '0', 'reseller_business_name' => ''])
        ->assertSessionHas('success', trans('customer::customer_front.reseller.disabled'));

    expect($customer->fresh())->isReseller()->toBeFalse()->reseller_business_name->toBeNull();
});

test('a new OTP customer completes their profile and continues', function () {
    $customer = Customer::factory()->otpOnly()->create();
    $this->actingAs($customer, 'customer');

    $this->get(frontRoute('customer.profile.complete'))->assertSuccessful()->assertSee('name="email"', false);

    $this->post(frontRoute('customer.profile.complete.store'), ['first_name' => 'Meera', 'last_name' => '', 'email' => 'Meera@Example.com'])
        ->assertRedirect(frontRoute('customer.myaccount'));

    expect($customer->fresh())->first_name->toBe('Meera')->email->toBe('meera@example.com')
        ->and($customer->fresh()->needsProfileCompletion())->toBeFalse();
});

test('the profile prompt can be skipped and is not shown to customers with a name', function () {
    $this->actingAs(Customer::factory()->otpOnly()->create(), 'customer')
        ->get(frontRoute('customer.profile.complete.skip'))
        ->assertRedirect(frontRoute('customer.myaccount'));

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(frontRoute('customer.profile.complete'))
        ->assertRedirect(frontRoute('customer.myaccount'));
});

test('the email offered on the profile prompt must be unique', function () {
    Customer::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs(Customer::factory()->otpOnly()->create(), 'customer')
        ->post(frontRoute('customer.profile.complete.store'), ['first_name' => 'Meera', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

test('an OTP customer can update the profile without an email', function () {
    $customer = Customer::factory()->otpOnly()->create(['first_name' => 'Meera']);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.update'), [
            'first_name' => 'Meera',
            'last_name' => 'Iyer',
            'email' => '',
            'gender' => 'female',
            'date_of_birth' => '1994-05-17',
            'marketing_opt_in' => config('core.no'),
        ])
        ->assertRedirect(frontRoute('customer.myaccount'));

    expect($customer->fresh())
        ->last_name->toBe('Iyer')
        ->gender->toBe('female')
        ->email->toBeNull()
        ->and($customer->fresh()->date_of_birth->format('Y-m-d'))->toBe('1994-05-17')
        ->and((int) $customer->fresh()->marketing_opt_in)->toBe((int) config('core.no'));
});

test('an account without a mobile still needs an email on the profile', function () {
    $this->actingAs(Customer::factory()->create(), 'customer')
        ->post(frontRoute('customer.update'), ['first_name' => 'Ada', 'email' => ''])
        ->assertSessionHasErrors('email');
});

test('profile values are validated', function () {
    $this->actingAs(Customer::factory()->withPhone()->create(), 'customer')
        ->post(frontRoute('customer.update'), ['first_name' => 'Ada', 'gender' => 'robot', 'date_of_birth' => now()->addDay()->format('Y-m-d')])
        ->assertSessionHasErrors(['gender', 'date_of_birth']);
});

test('an OTP customer can set a password without a current one, then log in with it', function () {
    $customer = Customer::factory()->otpOnly()->create(['first_name' => 'Meera', 'email' => 'meera@example.com']);
    $this->actingAs($customer, 'customer');

    $this->get(frontRoute('customer.change-password'))
        ->assertSuccessful()
        ->assertSee(trans('customer::customer_front.titles.set_password'))
        ->assertDontSee('name="old_password"', false);

    $this->post(frontRoute('customer.update-password'), ['password' => 'Brand#New99', 'password_confirmation' => 'Brand#New99'])
        ->assertRedirect(frontRoute('customer.myaccount'));

    expect(Hash::check('Brand#New99', $customer->fresh()->password))->toBeTrue();
});

test('the account overview works for a phone-only customer', function () {
    $this->actingAs(Customer::factory()->otpOnly('+919800011122')->create(), 'customer')
        ->get(frontRoute('customer.myaccount'))
        ->assertSuccessful()
        ->assertSee('+91 98000 11122')
        ->assertSee(frontRoute('customer.profile.complete'), false)
        ->assertSee(frontRoute('customer.wishlist.index'), false)
        ->assertSee(frontRoute('customer.reseller.edit'), false);
});

test('the new account pages render', function (string $route) {
    $this->actingAs(Customer::factory()->withPhone()->create(), 'customer')
        ->get(frontRoute($route))
        ->assertSuccessful();
})->with(['customer.wishlist.index', 'customer.reseller.edit', 'customer.deletion.create', 'customer.profile.edit']);

test('guests are sent to the login page from the new account pages', function (string $route) {
    $this->get(frontRoute($route))->assertRedirect(frontRoute('customer.login'));
})->with(['customer.wishlist.index', 'customer.reseller.edit', 'customer.deletion.create', 'customer.profile.complete']);
