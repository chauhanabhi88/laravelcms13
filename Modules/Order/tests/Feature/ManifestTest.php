<?php

use Modules\Logistics\Models\PickupBatch;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SellerOrder;
use Modules\Seller\Models\SellerWarehouse;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    $this->courier = fulfilmentSetup();
});

test('a manifest batches packed orders and schedules the pickup with the courier', function () {
    $seller = orderSeller();
    $first = packedFulfilmentOrder($seller);
    $second = packedFulfilmentOrder($seller);

    $outcome = fulfilment()->createManifest([$first, $second], now()->addDay());
    $batch = PickupBatch::query()->findOrFail($outcome->pickupBatchId);

    expect($outcome->manifestNo)->toStartWith('MAN-')
        ->and($outcome->pickupScheduled)->toBeTrue()
        ->and($outcome->shipmentsCount)->toBe(2)
        ->and($batch->status)->toBe(PickupBatch::STATUS_MANIFESTED)
        ->and($batch->seller_id)->toBe($seller->id)
        ->and($batch->courier_id)->toBe($this->courier->id)
        ->and($batch->manifest_url)->toStartWith('https://manifests.example.test/')
        ->and(fakeCourier()->called('schedulePickup'))->toBe(1)
        ->and(fakeCourier()->called('generateManifest'))->toBe(1)
        ->and(Shipment::query()->where('pickup_batch_id', $batch->id)->count())->toBe(2)
        ->and(Shipment::query()->where('pickup_batch_id', $batch->id)->pluck('status')->unique()->all())->toBe([Shipment::STATUS_PICKUP_SCHEDULED])
        ->and($first->fresh()->status)->toBe(SellerOrder::STATUS_PACKED);

    // The courier's first scan ships the orders and the batch is picked up.
    fulfilmentScans($first, ['picked_up']);
    expect($first->fresh()->status)->toBe(SellerOrder::STATUS_SHIPPED)
        ->and($batch->fresh()->status)->toBe(PickupBatch::STATUS_PARTIALLY_PICKED);

    fulfilmentScans($second, ['picked_up']);
    expect($batch->fresh()->status)->toBe(PickupBatch::STATUS_PICKED_UP);
});

test('only packed orders of one warehouse can be manifested', function () {
    $seller = orderSeller();
    $ready = readyFulfilmentOrder($seller);
    $packed = packedFulfilmentOrder($seller);

    expect(fn () => fulfilment()->createManifest([$ready, $packed], now()->addDay()))
        ->toThrow(OrderException::class, trans('order::fulfilment.errors.manifest_packed_only', ['order' => $ready->sub_order_no]));

    $otherWarehouse = SellerWarehouse::factory()->create(['seller_id' => $seller->id, 'pincode' => orderPincode()->pincode]);
    $elsewhere = packedFulfilmentOrder($seller);
    $elsewhere->forceFill(['warehouse_id' => $otherWarehouse->id])->save();

    expect(fn () => fulfilment()->createManifest([$packed, $elsewhere], now()->addDay()))
        ->toThrow(OrderException::class, trans('order::fulfilment.errors.one_warehouse'));

    expect(fn () => fulfilment()->createManifest([$packed], now()->subDay()))
        ->toThrow(OrderException::class, trans('order::fulfilment.errors.pickup_date_past'));

    expect(PickupBatch::query()->count())->toBe(0)
        ->and(fakeCourier()->called('schedulePickup'))->toBe(0);
});

test('an order already on a manifest cannot be batched again', function () {
    $packed = packedFulfilmentOrder();
    fulfilment()->createManifest([$packed], now()->addDay());

    expect(fn () => fulfilment()->createManifest([$packed], now()->addDay()))->toThrow(OrderException::class);
    expect(PickupBatch::query()->count())->toBe(1);
});

test('the seller creates a manifest from the desk, lists it and prints it', function () {
    $seller = orderSeller();
    $packed = packedFulfilmentOrder($seller);
    $owner = fulfilmentOwner($seller);

    $this->actingAs($owner, 'seller')
        ->from(route('seller.order.index', ['tab' => 'ready']))
        ->post(route('seller.manifest.create'), ['ids' => [$packed->id], 'pickup_date' => now()->addDay()->toDateString()])
        ->assertRedirect(route('seller.manifest.index'))
        ->assertSessionHas('success');

    $batch = PickupBatch::query()->where('seller_id', $seller->id)->firstOrFail();

    $this->get(route('seller.manifest.index'))->assertOk()->assertSee($batch->manifest_no);

    $response = $this->get(route('seller.manifest.download', [$batch->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');

    $this->actingAs(fulfilmentOwner(orderSeller()), 'seller')->get(route('seller.manifest.download', [$batch->id]))->assertNotFound();
});
