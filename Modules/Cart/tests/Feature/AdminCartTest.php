<?php

use Modules\Customer\Models\Customer;

require_once __DIR__.'/../Helpers.php';

test('support staff list carts and open one read-only', function () {
    $admin = cartAdmin();
    $customer = Customer::factory()->create(['first_name' => 'Ritu', 'last_name' => 'Sharma']);
    $variant = cartVariant(['stock' => 10]);
    $result = cartService()->add(customerOwner($customer), $variant->id, 4);
    cartUpdateVariant($variant, ['stock' => 2]);

    $this->actingAs($admin)
        ->get(route('admin.cart.index', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee('Ritu Sharma');

    $this->actingAs($admin)
        ->get(route('admin.cart.show', updateUrlParams([$result->cart->id])))
        ->assertSuccessful()
        ->assertSee('Ritu Sharma')
        ->assertSee($variant->sku);

    // Viewing never changes what the buyer sees.
    expect($result->item->fresh()->quantity)->toBe(4);
});

test('the cart screens need their permission', function () {
    $this->actingAs(storefrontAdmin([]))
        ->get(route('admin.cart.index', updateUrlParams()))
        ->assertForbidden();
});
