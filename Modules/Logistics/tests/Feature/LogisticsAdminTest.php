<?php

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\CourierRule;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\ShipmentService;
use Modules\Logistics\Services\TrackingService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->default = logisticsSetup();
});

test('the logistics screens need their permissions', function (string $route) {
    $this->actingAs(logisticsAdmin([]))
        ->get(route($route, updateUrlParams()))
        ->assertForbidden();
})->with(['admin.courier.index', 'admin.courier_rule.index', 'admin.shipment.index', 'admin.ndr.index', 'admin.courier_rule.simulate']);

test('Logistics Ops list couriers and create one with encrypted credentials', function () {
    $admin = logisticsAdmin();

    $this->actingAs($admin)->get(route('admin.courier.index', updateUrlParams()))->assertSuccessful()->assertSee('fake_default');
    $this->actingAs($admin)->get(route('admin.courier.create', updateUrlParams()))->assertSuccessful();

    $this->actingAs($admin)
        ->post(route('admin.courier.store', updateUrlParams()), [
            'code' => 'sr_backup', 'name' => 'Shiprocket backup', 'driver' => 'shiprocket',
            'credentials_email' => 'ops@example.test', 'credentials_password' => 'p<a>ss&word',
            'supports_cod' => 1, 'priority' => 3, 'status' => 1,
        ])
        ->assertRedirect(route('admin.courier.index', updateUrlParams()))
        ->assertSessionHas('success');

    $courier = Courier::query()->where('code', 'sr_backup')->firstOrFail();

    expect($courier->credential('password'))->toBe('p<a>ss&word')
        ->and($courier->supportsReverse())->toBeFalse()
        ->and((string) DB::table('courier')->where('id', $courier->id)->value('credentials'))->not->toContain('ops@example.test');

    // A blank password on update keeps the stored one.
    $this->actingAs($admin)
        ->put(route('admin.courier.update', updateUrlParams([$courier->id])), [
            'code' => 'sr_backup', 'name' => 'Renamed', 'driver' => 'shiprocket', 'credentials_email' => 'ops@example.test',
            'credentials_password' => '', 'supports_cod' => 1, 'supports_reverse' => 1, 'priority' => 3, 'status' => 1,
        ])
        ->assertSessionHas('success');

    expect($courier->fresh())->name->toBe('Renamed')
        ->and($courier->fresh()->credential('password'))->toBe('p<a>ss&word');
});

test('a courier in use cannot be deleted', function () {
    CourierRule::factory()->for($this->default, 'courier')->create();

    $this->actingAs(logisticsAdmin())
        ->delete(route('admin.courier.delete', updateUrlParams([$this->default->id])))
        ->assertSessionHas('error', trans('logistics::courier.messages.in_use'));

    expect(Courier::query()->whereKey($this->default->id)->exists())->toBeTrue();
});

test('rules are built from the form and simulated', function () {
    $admin = logisticsAdmin();
    $destination = orderPincode();
    $destination->forceFill(['zone' => 'metro'])->save();
    $prefix = substr($destination->pincode, 0, 3);

    $this->actingAs($admin)
        ->post(route('admin.courier_rule.store', updateUrlParams()), [
            'name' => 'Metro COD light', 'priority' => 5, 'courier_id' => $this->default->id, 'strategy' => 'cheapest',
            'zones' => ['metro'], 'payment' => ['cod'], 'pincode_prefixes' => '560, '.$prefix, 'weight_max_g' => 2000, 'status' => 1,
        ])
        ->assertSessionHas('success');

    $rule = CourierRule::query()->where('name', 'Metro COD light')->firstOrFail();

    expect($rule->conditions)->toEqual(['zones' => ['metro'], 'pincode_prefixes' => array_values(array_unique(['560', $prefix])), 'weight_max_g' => 2000, 'payment' => ['cod']])
        ->and($rule->strategy)->toBe('cheapest');

    $this->actingAs($admin)->get(route('admin.courier_rule.index', updateUrlParams()))->assertSuccessful()->assertSee('Metro COD light');
    $this->actingAs($admin)->get(route('admin.courier_rule.edit', updateUrlParams([$rule->id])))->assertSuccessful();

    $this->actingAs($admin)
        ->post(route('admin.courier_rule.simulate', updateUrlParams()), [
            'origin_pincode' => '400001', 'destination_pincode' => $destination->pincode, 'weight_grams' => 500, 'payment' => 'cod',
        ])
        ->assertSuccessful()
        ->assertSee('#'.$rule->id.' Metro COD light')
        ->assertSee('Fake Cheap');

    $this->actingAs($admin)
        ->post(route('admin.courier_rule.store', updateUrlParams()), ['name' => 'Bad', 'priority' => 1, 'courier_id' => $this->default->id, 'strategy' => 'preferred_list'])
        ->assertSessionHasErrors('preferred_carrier_ids');
});

test('Logistics Ops see shipments, filter them and open one', function () {
    [, $sellerOrder, $shipment] = logisticsOrder();
    $shipment = app(ShipmentService::class)->book($shipment);
    app(TrackingService::class)->apply($shipment, [scan('picked_up', '-1 hour', location: 'Pune Hub')]);
    $admin = logisticsAdmin(array_merge(array_keys(array_merge(...array_values(require base_path('Modules/Logistics/config/permissions.php'))))));

    $this->actingAs($admin)->get(route('admin.shipment.index', updateUrlParams()))->assertSuccessful()->assertSee($shipment->awb);

    $this->actingAs($admin)
        ->post(route('admin.shipment.filters', updateUrlParams()), ['flag' => 'needs_attention'])
        ->assertSuccessful()
        ->assertJsonPath('type', 'success')
        ->assertDontSee($shipment->awb);

    $this->actingAs($admin)
        ->get(route('admin.shipment.show', updateUrlParams([$shipment->id])))
        ->assertSuccessful()
        ->assertSee($sellerOrder->sub_order_no)
        ->assertSee('Pune Hub');

    $this->actingAs($admin)->get(route('admin.shipment.export', updateUrlParams()))->assertSuccessful();
});

test('Logistics Ops book, enter a manual AWB and record a manual scan', function () {
    logisticsSettings(['default_courier' => 'manual']);
    [, , $shipment] = logisticsOrder();
    $admin = logisticsAdmin();

    $this->actingAs($admin)->post(route('admin.shipment.book', updateUrlParams([$shipment->id])))->assertSessionHas('error');

    $this->actingAs($admin)
        ->post(route('admin.shipment.manual_awb', updateUrlParams([$shipment->id])), ['awb' => 'OPS-778899', 'carrier_name' => 'City Couriers'])
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->post(route('admin.shipment.manual_status', updateUrlParams([$shipment->id])), ['status' => 'picked_up', 'location' => 'Pune'])
        ->assertSessionHas('success');

    expect($shipment->fresh())->awb->toBe('OPS-778899')->status->toBe(Shipment::STATUS_PICKED_UP);
});

test('Logistics Ops act on an NDR case', function () {
    [, , $shipment] = logisticsOrder();
    $shipment = app(ShipmentService::class)->book($shipment);
    app(TrackingService::class)->apply($shipment, [scan('picked_up', '-2 hours'), scan('ndr', '-1 hour')]);
    $case = NdrCase::query()->where('shipment_id', $shipment->id)->firstOrFail();
    $admin = logisticsAdmin();

    $this->actingAs($admin)->get(route('admin.ndr.index', updateUrlParams()))->assertSuccessful()->assertSee($shipment->awb);

    $this->actingAs($admin)
        ->post(route('admin.ndr.act', updateUrlParams([$case->id])), ['action' => 'change_address'])
        ->assertSessionHasErrors('address1');

    $this->actingAs($admin)
        ->post(route('admin.ndr.act', updateUrlParams([$case->id])), ['action' => 'change_address', 'address1' => '22 New Street', 'phone' => '9876501234'])
        ->assertSessionHas('success');

    expect($case->fresh())
        ->status->toBe(NdrCase::STATUS_ACTIONED)
        ->customer_action->toBe('change_address')
        ->actioned_by->toBe('admin:'.$admin->id)
        ->and($case->fresh()->action_payload)->toEqual(['phone' => '9876501234', 'address1' => '22 New Street'])
        ->and(fakeCourier()->called('ndrAction'))->toBe(1);
});
