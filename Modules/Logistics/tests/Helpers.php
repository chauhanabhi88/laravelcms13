<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Drivers\FakeCourierDriver;
use Modules\Logistics\DTO\TrackingEvent;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

require_once __DIR__.'/../../Order/tests/Helpers.php';

/*
| Shared helpers for the Logistics module tests (require_once'd: Pest test files with
| same-named global functions would collide).
*/

if (! function_exists('logisticsSetup')) {
    /**
     * Order test setup plus an active fake courier as the default courier, so nothing calls a
     * real courier API. Returns that courier.
     */
    function logisticsSetup(): Courier
    {
        orderSetup();
        config(['logistics.shiprocket.email' => null, 'logistics.shiprocket.password' => null, 'logistics.shiprocket.webhook_token' => 'test-webhook-token']);

        $courier = Courier::factory()->create(['code' => 'fake_default', 'priority' => 1]);
        logisticsSettings(['default_courier' => $courier->code, 'default_strategy' => 'recommended', 'auto_rto_after_attempts' => 3, 'live_serviceability' => 1, 'rto_charge_percent' => 100]);

        return $courier;
    }

    /**
     * Merge values into Settings > Logistics.
     *
     * @param  array<string, mixed>  $values
     */
    function logisticsSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'logistics')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'logistics'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    function fakeCourier(): FakeCourierDriver
    {
        return app(FakeCourierDriver::class);
    }

    /**
     * A placed and confirmed COD order of one seller (qty × the variant) and its planned
     * shipment: [order, seller order, shipment].
     *
     * @param  array<string, mixed>  $variant
     * @return array{0: SalesOrder, 1: SellerOrder, 2: Shipment}
     */
    function logisticsOrder(array $variant = [], int $quantity = 1, ?Seller $seller = null): array
    {
        $seller ??= orderSeller();
        $item = cartVariant(array_merge(['stock' => 10, 'price' => '499', 'weight_grams' => 400], $variant), $seller);
        $customer = orderCustomer();
        orderCart($customer, [[$item, $quantity]]);

        $order = placeOrder($customer)->order;
        $sellerOrder = SellerOrder::query()->where('sales_order_id', $order->id)->firstOrFail();
        $shipment = Shipment::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

        return [$order->refresh(), $sellerOrder, $shipment];
    }

    /**
     * A courier scan (mapped status) at $at (default: now).
     */
    function scan(string $status, ?string $at = null, string $label = '', ?string $location = 'Hub'): TrackingEvent
    {
        return new TrackingEvent($status, $label !== '' ? $label : strtoupper(str_replace('_', ' ', $status)), Carbon::parse($at ?? 'now'), '', $location, null);
    }

    function logisticsVariant(ProductVariant $variant): ProductVariant
    {
        return ProductVariant::query()->findOrFail($variant->id);
    }

    /**
     * @return array<string, mixed>
     */
    function shiprocketFixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/Fixtures/shiprocket/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * An admin whose role holds the given permissions (default: every Logistics permission).
     *
     * @param  list<string>|null  $permissions
     */
    function logisticsAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Logistics/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Logistics Ops',
            'slug' => 'logistics_ops_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Logistics Ops',
            'email' => uniqid('logistics-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
