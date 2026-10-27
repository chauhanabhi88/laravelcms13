<?php

use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Log;
use Modules\Customer\Models\Customer;
use Modules\Directory\Models\DirectoryPincode;

test('lazy loading throws for marketplace models', function () {
    DirectoryPincode::factory()->count(2)->create();

    $pincode = DirectoryPincode::query()->get()->first();

    expect(fn () => $pincode->state)->toThrow(LazyLoadingViolationException::class);
});

test('eager loading marketplace models is fine', function () {
    DirectoryPincode::factory()->count(2)->create();

    expect(DirectoryPincode::query()->with('state')->get()->first()->state)->toBeNull();
});

test('legacy models only log a lazy load', function () {
    Log::spy();
    Customer::factory()->count(2)->create();

    $customer = Customer::query()->get()->first();

    expect($customer->address)->toBeEmpty();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Lazy loading [address]'));
});
