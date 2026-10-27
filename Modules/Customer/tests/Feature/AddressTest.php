<?php

use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->customer = Customer::factory()->withPhone('+919811112222')->create();
    $this->actingAs($this->customer, 'customer');
});

test('a new address takes city and state from the pincode and becomes the default', function () {
    customerPincode('560001');

    $this->post(frontRoute('customer.addresses.store'), ['address' => customerAddressPayload(['tag' => 'Work'])])
        ->assertRedirect(frontRoute('customer.addresses.index'))
        ->assertSessionHas('success')
        ->assertSessionMissing('warning');

    $address = $this->customer->address()->sole();

    expect($address)
        ->pincode->toBe('560001')
        ->city_name->toBe('Bengaluru Urban')
        ->tag->toBe('work')
        ->phone->toBe('+919876543210')
        ->name->toBe('Asha Rao')
        ->and($address->isDefault())->toBeTrue()
        // legacy columns stay readable for the admin screen and the delivery pincode lookup
        ->and($address->postal_code)->toBe('560001')
        ->and($address->building)->toBe('Flat 4B, Lake View Apartments')
        ->and($address->street_name)->toBe('MG Road');
});

test('only one address is the default', function () {
    customerPincode('560001');
    $first = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);

    $this->post(frontRoute('customer.addresses.store'), ['address' => customerAddressPayload(['is_default_address' => '1'])]);
    $this->post(frontRoute('customer.addresses.store'), ['address' => customerAddressPayload(['name' => 'Second'])]);

    $defaults = $this->customer->address()->where('is_default_address', config('customer.is_default_address.yes'))->get();

    expect($defaults)->toHaveCount(1)
        ->and($defaults->first()->name)->toBe('Asha Rao')
        ->and($first->fresh()->isDefault())->toBeFalse();
});

test('the default address stays default when the box is unticked', function () {
    customerPincode('560001');
    $address = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);

    $this->put(frontRoute('customer.addresses.update', [$address->id]), ['address' => customerAddressPayload(['name' => 'Renamed'])])
        ->assertRedirect(frontRoute('customer.addresses.index'));

    expect($address->fresh())->name->toBe('Renamed')->and($address->fresh()->isDefault())->toBeTrue();
});

test('deleting the default promotes the newest remaining address', function () {
    $older = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
    $newer = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
    $default = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);

    $this->delete(frontRoute('customer.addresses.destroy', [$default->id]))->assertRedirect(frontRoute('customer.addresses.index'));

    expect($newer->fresh()->isDefault())->toBeTrue()->and($older->fresh()->isDefault())->toBeFalse();
});

test('an unknown pincode is rejected', function () {
    $this->post(frontRoute('customer.addresses.store'), ['address' => customerAddressPayload(['pincode' => '799999'])])
        ->assertSessionHasErrors(['address.pincode' => trans('customer::customer_front.address.unknown_pincode')]);

    expect($this->customer->address()->count())->toBe(0);
});

test('an address for a pincode we do not deliver to is saved with a warning', function () {
    customerPincode('744101', serviceable: false);

    $this->post(frontRoute('customer.addresses.store'), ['address' => customerAddressPayload(['pincode' => '744101'])])
        ->assertSessionHas('warning', trans('customer::customer_front.address.not_serviceable'));

    expect($this->customer->address()->count())->toBe(1);
});

test('a customer can keep at most the configured number of addresses', function () {
    config(['customer.max_addresses' => 2]);
    customerPincode('560001');
    CustomerAddress::factory()->count(2)->create(['customer_id' => $this->customer->id]);

    $this->post(frontRoute('customer.addresses.store'), ['address' => customerAddressPayload()])
        ->assertSessionHasErrors(['address' => trans('customer::customer_front.address.limit', ['max' => 2])]);

    expect($this->customer->address()->count())->toBe(2);
});

test('the mobile number on an address must be valid', function () {
    customerPincode('560001');

    $this->post(frontRoute('customer.addresses.store'), ['address' => customerAddressPayload(['phone' => '12345'])])
        ->assertSessionHasErrors('address.phone');
});

test('only the owner can update or delete an address', function () {
    customerPincode('560001');
    $someoneElses = CustomerAddress::factory()->create();

    $this->put(frontRoute('customer.addresses.update', [$someoneElses->id]), ['address' => customerAddressPayload()])->assertNotFound();
    $this->delete(frontRoute('customer.addresses.destroy', [$someoneElses->id]))->assertNotFound();

    expect($someoneElses->fresh())->not->toBeNull();
});

test('the address book renders the pincode-first form', function () {
    CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id, 'name' => 'Ravi Kumar']);

    $this->get(frontRoute('customer.addresses.index'))
        ->assertSuccessful()
        ->assertSee('Ravi Kumar')
        ->assertSee('name="address[pincode]"', false)
        ->assertSee('addressForm(', false);
});

test('addresses written by the admin screen in the legacy shape fill the new columns', function () {
    $address = CustomerAddress::query()->create([
        'customer_id' => $this->customer->id,
        'building' => 'Tower A',
        'unit_no' => '12',
        'street_name' => 'Main Street',
        'postal_code' => '411001',
        'tag' => 'Home',
        'is_default_address' => 0,
    ]);

    expect($address->fresh())
        ->address_line1->toBe('12, Tower A')
        ->address_line2->toBe('Main Street')
        ->pincode->toBe('411001');
});
