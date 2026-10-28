<?php

use Illuminate\Support\Facades\DB;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderNote;
use Modules\Order\Models\SellerOrder;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
});

test('admins see the orders grid and filter it', function () {
    $order = SalesOrder::factory()->withSellerOrders(1)->create();
    $other = SalesOrder::factory()->status(SalesOrder::STATUS_CANCELLED)->withSellerOrders(1)->create();

    $this->actingAs(orderAdmin())
        ->get(route('admin.sales_order.index', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee($order->order_no);

    $this->actingAs(orderAdmin())
        ->postJson(route('admin.sales_order.filters', updateUrlParams()), ['status' => SalesOrder::STATUS_CANCELLED])
        ->assertSuccessful()
        ->assertJsonPath('type', 'success')
        ->assertSee($other->order_no)
        ->assertDontSee($order->order_no);
});

test('the detail shows seller orders, items and addresses', function () {
    $order = SalesOrder::factory()->withSellerOrders(2)->create();
    $item = $order->items()->firstOrFail();

    $this->actingAs(orderAdmin())
        ->get(route('admin.sales_order.show', updateUrlParams([$order->id])))
        ->assertSuccessful()
        ->assertSee($order->order_no.'-2')
        ->assertSee($item->sku)
        ->assertSee('Asha Rao');
});

test('admins add internal notes and export CSV', function () {
    $order = SalesOrder::factory()->withSellerOrders(1)->create();
    $admin = orderAdmin();

    $this->actingAs($admin)
        ->post(route('admin.sales_order.note', updateUrlParams([$order->id])), ['note' => 'Customer called about the size.'])
        ->assertRedirect(route('admin.sales_order.show', updateUrlParams([$order->id])));

    expect(SalesOrderNote::query()->where('sales_order_id', $order->id)->value('note'))->toBe('Customer called about the size.');

    $csv = $this->actingAs($admin)->get(route('admin.sales_order.export', updateUrlParams()));
    $csv->assertSuccessful();
    expect($csv->streamedContent())->toContain('Order No')->toContain($order->order_no);
});

test('admins cancel an order before shipment', function () {
    $variant = cartVariant(['stock' => 3]);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $order = placeOrder($customer)->order;

    $this->actingAs(orderAdmin())
        ->post(route('admin.sales_order.cancel', updateUrlParams([$order->id])), ['reason' => 'Fraud check failed'])
        ->assertRedirect(route('admin.sales_order.show', updateUrlParams([$order->id])))
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED)
        ->and(SellerOrder::query()->where('sales_order_id', $order->id)->first())
        ->cancelled_by->toBe('admin')
        ->cancel_reason->toBe('Fraud check failed')
        ->and(orderVariant($variant)->reserved)->toBe(0);
});

test('the seller orders grid filters SLA breaches and shows the detail', function () {
    $late = SalesOrder::factory()->withSellerOrders(1)->create()->sellerOrders()->firstOrFail();
    $late->forceFill(['sla_dispatch_by' => now()->subHour()])->save();
    $onTime = SalesOrder::factory()->withSellerOrders(1)->create()->sellerOrders()->firstOrFail();

    $this->actingAs(orderAdmin())
        ->postJson(route('admin.seller_order.filters', updateUrlParams()), ['sla' => 'breached'])
        ->assertSuccessful()
        ->assertSee($late->sub_order_no)
        ->assertDontSee($onTime->sub_order_no);

    $this->actingAs(orderAdmin())
        ->get(route('admin.seller_order.show', updateUrlParams([$late->id])))
        ->assertSuccessful()
        ->assertSee($late->sub_order_no);
});

test('admins without the permission are refused', function () {
    $order = SalesOrder::factory()->create();
    $viewer = orderAdmin(['admin.sales_order.index']);

    $this->actingAs($viewer)->get(route('admin.sales_order.show', updateUrlParams([$order->id])))->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.sales_order.cancel', updateUrlParams([$order->id])), ['reason' => 'x'])->assertForbidden();
});

test('every permission has a label and the master admin holds them all', function () {
    $groups = require base_path('Modules/Order/config/permissions.php');

    foreach ($groups as $group => $permissions) {
        expect(trans($group))->not->toBe($group);

        foreach ($permissions as $label) {
            expect(trans($label))->not->toBe($label);
        }
    }

    $granted = json_decode((string) DB::table('role')->where('slug', 'master_admin')->value('permissions'), true) ?: [];

    expect(array_diff(array_merge(...array_map('array_keys', array_values($groups))), $granted))->toBe([]);
})->skip(fn () => ! DB::table('role')->where('slug', 'master_admin')->exists(), 'No master admin role in this database');
