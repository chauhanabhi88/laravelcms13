<?php

use Modules\Customer\Models\Customer;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Storefront/tests/Helpers.php';

/**
 * @return array<string, mixed>
 */
function adminCustomerPayload(Customer $customer, array $overrides = []): array
{
    return array_replace_recursive([
        'customer' => [
            'first_name' => $customer->first_name ?: 'Kiran',
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'contact_number' => $customer->contact_number,
            'phone' => '',
            'is_reseller' => config('core.no'),
            'reseller_business_name' => '',
            'status' => 'on',
        ],
    ], $overrides);
}

test('an admin can edit a phone-only customer and set mobile and reseller fields', function () {
    $customer = Customer::factory()->otpOnly('+919855566677')->create(['first_name' => 'Kiran']);

    $this->actingAs(storefrontAdmin(['admin.customer.edit']))
        ->put(route('admin.customer.update', updateUrlParams([$customer->id])), adminCustomerPayload($customer, ['customer' => [
            'phone' => '98555 66677',
            'is_reseller' => config('core.yes'),
            'reseller_business_name' => 'Kiran Traders',
        ]]))
        ->assertRedirect(route('admin.customer.index', updateUrlParams()))
        ->assertSessionHas('success');

    expect($customer->fresh())
        ->phone->toBe('+919855566677')
        ->email->toBeNull()
        ->reseller_business_name->toBe('Kiran Traders')
        ->and($customer->fresh()->isReseller())->toBeTrue()
        ->and($customer->fresh()->phone_verified_at)->not->toBeNull();
});

test('the admin cannot give a customer a mobile another customer uses', function () {
    Customer::factory()->withPhone('+919855566677')->create();
    $customer = Customer::factory()->create();

    $this->actingAs(storefrontAdmin(['admin.customer.edit']))
        ->put(route('admin.customer.update', updateUrlParams([$customer->id])), adminCustomerPayload($customer, ['customer' => ['phone' => '9855566677']]))
        ->assertSessionHasErrors(['customer.phone' => trans('customer::customer.messages.phone_unique')]);
});

test('a customer without a mobile still needs an email in the admin', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(storefrontAdmin(['admin.customer.edit']))
        ->put(route('admin.customer.update', updateUrlParams([$customer->id])), adminCustomerPayload($customer, ['customer' => ['email' => '']]))
        ->assertSessionHasErrors('customer.email');
});

test('an admin with the permission can block and unblock cash on delivery', function () {
    $customer = Customer::factory()->create();
    $admin = storefrontAdmin(['admin.customer.toggle_cod_block']);

    $this->actingAs($admin)
        ->post(route('admin.customer.toggle_cod_block', updateUrlParams([$customer->id])))
        ->assertSessionHas('success', trans('customer::customer.messages.cod_blocked'));
    expect($customer->fresh()->isCodBlocked())->toBeTrue();

    $this->post(route('admin.customer.toggle_cod_block', updateUrlParams([$customer->id])))
        ->assertSessionHas('success', trans('customer::customer.messages.cod_unblocked'));
    expect($customer->fresh()->isCodBlocked())->toBeFalse();
});

test('blocking cash on delivery needs its own permission', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(storefrontAdmin(['admin.customer.edit']))
        ->post(route('admin.customer.toggle_cod_block', updateUrlParams([$customer->id])))
        ->assertForbidden();

    expect($customer->fresh()->isCodBlocked())->toBeFalse();
});

test('the new permission is listed for the role editor', function () {
    $permissions = array_merge(...array_values(require base_path('Modules/Customer/config/permissions.php')));

    expect($permissions)->toHaveKey('admin.customer.toggle_cod_block');
});
