<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Logistics\Models\Courier;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Seller\Services\WarehouseService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    logisticsSetup();
    config(['core.integrations.retries' => 0]);
    Http::swap(new Factory);
    Http::fake([
        '*/v1/external/auth/login' => Http::response(shiprocketFixture('auth')),
        '*/v1/external/settings/company/addpickup' => Http::response(['success' => true]),
    ]);
});

test('a new warehouse of an active seller is registered with the configured API courier', function () {
    Courier::factory()->shiprocket()->create();
    $seller = orderSeller();

    $warehouse = app(WarehouseService::class)->save($seller, [
        'name' => 'Second', 'contact_name' => 'Asha', 'contact_phone' => '9876543210',
        'address_line1' => '12 MG Road', 'pincode' => orderPincode()->pincode,
    ]);

    expect($warehouse->fresh()->courier_pickup_ref)->toStartWith('WH-');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'addpickup') && $request['pin_code'] === $warehouse->pincode);
});

test('nothing is sent while no API courier is configured', function () {
    // Only the seeded Shiprocket courier, without .env credentials.
    $seller = orderSeller();

    app(WarehouseService::class)->save($seller, [
        'name' => 'Second', 'contact_name' => 'Asha', 'contact_phone' => '9876543210',
        'address_line1' => '12 MG Road', 'pincode' => orderPincode()->pincode,
    ]);

    Http::assertNothingSent();
    expect(SellerWarehouse::query()->where('seller_id', $seller->id)->whereNotNull('courier_pickup_ref')->exists())->toBeFalse();
});
