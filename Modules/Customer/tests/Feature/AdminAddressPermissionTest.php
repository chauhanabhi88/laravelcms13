<?php

use Illuminate\Support\Facades\DB;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Storefront/tests/Helpers.php';

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
});

test('the customer address routes check their permissions', function () {
    $admin = storefrontAdmin(['admin.customer.edit']);

    $this->actingAs($admin)->get(route('admin.address.get_address', updateUrlParams()).'?id='.$this->address->id)->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.address.delete', updateUrlParams([$this->address->id])))->assertForbidden();

    expect(CustomerAddress::query()->find($this->address->id))->not->toBeNull();
});

test('an admin with the address permissions reads and deletes addresses', function () {
    $admin = storefrontAdmin(['admin.customer.edit', 'admin.address.get_address', 'admin.address.delete']);

    $this->actingAs($admin)->get(route('admin.address.get_address', updateUrlParams()).'?id='.$this->address->id)
        ->assertOk()
        ->assertJsonPath('data.id', $this->address->id);

    $this->actingAs($admin)->delete(route('admin.address.delete', updateUrlParams([$this->address->id])))
        ->assertRedirect(route('admin.customer.edit', updateUrlParams([$this->customer->id])));

    expect(CustomerAddress::query()->find($this->address->id))->toBeNull();
});

test('the migration grants the address permissions to roles that edit customers', function () {
    $editor = DB::table('role')->insertGetId(['name' => 'Support', 'slug' => 'support_'.uniqid(), 'permissions' => json_encode(['admin.customer.edit']), 'created_at' => now(), 'updated_at' => now()]);
    $other = DB::table('role')->insertGetId(['name' => 'Catalog', 'slug' => 'catalog_'.uniqid(), 'permissions' => json_encode(['admin.product.index']), 'created_at' => now(), 'updated_at' => now()]);

    (require base_path('Modules/Customer/database/migrations/2026_11_22_000001_grant_customer_address_permissions.php'))->up();

    expect(json_decode(DB::table('role')->where('id', $editor)->value('permissions'), true))
        ->toBe(['admin.customer.edit', 'admin.address.get_address', 'admin.address.delete'])
        ->and(json_decode(DB::table('role')->where('id', $other)->value('permissions'), true))->toBe(['admin.product.index']);
});
