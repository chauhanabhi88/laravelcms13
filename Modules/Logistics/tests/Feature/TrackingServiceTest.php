<?php

use Illuminate\Support\Facades\Event;
use Modules\Logistics\DTO\TrackingEvent;
use Modules\Logistics\Events\NdrRaised;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\ShipmentEvent;
use Modules\Logistics\Services\RtoService;
use Modules\Logistics\Services\ShipmentService;
use Modules\Logistics\Services\TrackingService;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\ProductVariant;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    logisticsSetup();
});

/**
 * A booked COD shipment of 2 units: [order, seller order, shipment, variant].
 *
 * @return array{0: SalesOrder, 1: SellerOrder, 2: Shipment, 3: ProductVariant}
 */
function bookedShipment(): array
{
    [$order, $sellerOrder, $shipment] = logisticsOrder(['stock' => 10], 2);
    $shipment = app(ShipmentService::class)->book($shipment);
    $variant = ProductVariant::query()->findOrFail($sellerOrder->items()->value('variant_id'));

    return [$order, $sellerOrder->fresh(), $shipment, $variant];
}

test('duplicate scans are stored once', function () {
    [, , $shipment] = bookedShipment();
    $tracking = app(TrackingService::class);

    $first = $tracking->apply($shipment, [scan('picked_up', '2026-10-29 10:00'), scan('in_transit', '2026-10-29 16:00')]);
    $second = $tracking->apply($shipment, [scan('picked_up', '2026-10-29 10:00'), scan('in_transit', '2026-10-29 16:00')]);

    expect($first)->toBe(2)
        ->and($second)->toBe(0)
        ->and(ShipmentEvent::query()->where('shipment_id', $shipment->id)->count())->toBe(2)
        ->and($shipment->fresh())
        ->status->toBe(Shipment::STATUS_IN_TRANSIT)
        ->last_status_label->toBe('IN TRANSIT');
});

test('the shipment only moves forward, a late older scan does not regress it', function () {
    [, $sellerOrder, $shipment] = bookedShipment();
    $tracking = app(TrackingService::class);

    $tracking->apply($shipment, [scan('out_for_delivery', '2026-10-31 08:00')]);
    $tracking->apply($shipment, [scan('in_transit', '2026-10-30 08:00')]);

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_OUT_FOR_DELIVERY)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_OUT_FOR_DELIVERY)
        ->and(ShipmentEvent::query()->where('shipment_id', $shipment->id)->count())->toBe(2);
});

test('the first pickup scan ships the seller order and commits the stock', function () {
    [$order, $sellerOrder, $shipment, $variant] = bookedShipment();
    expect($variant)->stock->toBe(10)->reserved->toBe(2);

    app(TrackingService::class)->apply($shipment, [scan('picked_up')]);

    $sellerOrder->refresh();
    $variant->refresh();

    expect($sellerOrder->status)->toBe(SellerOrder::STATUS_SHIPPED)
        ->and($sellerOrder->packed_at)->not->toBeNull()
        ->and($sellerOrder->shipped_at)->not->toBeNull()
        ->and($variant->stock)->toBe(8)
        ->and($variant->reserved)->toBe(0)
        ->and($order->fresh()->status)->toBe(SalesOrder::STATUS_SHIPPED)
        ->and($shipment->fresh()->picked_up_at)->not->toBeNull();

    // A second in-transit scan does not commit again.
    app(TrackingService::class)->apply($shipment, [scan('in_transit')]);
    expect($variant->fresh()->stock)->toBe(8);
});

test('delivery sets the return window and marks the COD payment collected', function () {
    [$order, $sellerOrder, $shipment] = bookedShipment();

    app(TrackingService::class)->apply($shipment, [scan('picked_up', '-2 days'), scan('delivered', '-1 hour')]);

    $sellerOrder->refresh();
    $days = (int) $sellerOrder->items()->max('return_window_days');

    expect($sellerOrder->status)->toBe(SellerOrder::STATUS_DELIVERED)
        ->and($sellerOrder->return_window_ends_at?->toDateString())->toBe($sellerOrder->delivered_at->copy()->addDays($days)->toDateString())
        ->and($order->fresh()->status)->toBe(SalesOrder::STATUS_DELIVERED)
        ->and(Payment::query()->forOrder($order->id)->value('status'))->toBe(Payment::STATUS_COLLECTED)
        ->and($shipment->fresh()->delivered_at)->not->toBeNull();
});

test('a failed attempt opens an NDR case, a re-attempt can still deliver', function () {
    Event::fake([NdrRaised::class]);
    [, $sellerOrder, $shipment] = bookedShipment();
    $tracking = app(TrackingService::class);

    $tracking->apply($shipment, [scan('picked_up', '-3 days'), scan('out_for_delivery', '-2 days'), new TrackingEvent('ndr', 'UNDELIVERED', now()->subDays(2)->addHours(5), '21', 'Mumbai', 'Customer not available')]);

    $case = NdrCase::query()->where('shipment_id', $shipment->id)->firstOrFail();

    expect($shipment->fresh())->status->toBe(Shipment::STATUS_NDR)->attempts->toBe(1)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_NDR)
        ->and($case)->status->toBe(NdrCase::STATUS_OPEN)->reason->toBe('Customer not available')->attempts->toBe(1);
    Event::assertDispatched(NdrRaised::class);

    $tracking->apply($shipment, [scan('out_for_delivery', '-1 day'), scan('delivered', '-20 hours')]);

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_DELIVERED)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_DELIVERED)
        ->and($case->fresh()->status)->toBe(NdrCase::STATUS_CLOSED);
});

test('three failed attempts send the package back automatically', function () {
    [, , $shipment] = bookedShipment();
    $tracking = app(TrackingService::class);
    $tracking->apply($shipment, [scan('picked_up', '-5 days')]);

    foreach ([4, 3, 2] as $daysAgo) {
        $tracking->apply($shipment, [scan('out_for_delivery', "-{$daysAgo} days"), scan('ndr', "-{$daysAgo} days +4 hours")]);
    }

    $case = NdrCase::query()->where('shipment_id', $shipment->id)->firstOrFail();

    expect($case)->attempts->toBe(3)->status->toBe(NdrCase::STATUS_ACTIONED)->customer_action->toBe(NdrCase::ACTION_CANCEL)->actioned_by->toBe('system')
        ->and(fakeCourier()->called('ndrAction'))->toBe(1);
});

test('the RTO branch moves the seller order back to origin, then the seller restocks', function () {
    [$order, $sellerOrder, $shipment, $variant] = bookedShipment();
    $tracking = app(TrackingService::class);

    $tracking->apply($shipment, [scan('picked_up', '-4 days'), scan('rto_initiated', '-3 days')]);

    expect($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_RTO_INITIATED)
        ->and($shipment->fresh()->rto_charge->toDecimal())->toBe('60.00')
        ->and($order->customer()->first()->rto_count)->toBe(1);

    // No way back to the forward path.
    $tracking->apply($shipment, [scan('delivered', '-2 days')]);
    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_RTO_INITIATED);

    $tracking->apply($shipment, [scan('rto_in_transit', '-2 days'), scan('rto_delivered', '-1 day')]);

    expect($shipment->fresh())->status->toBe(Shipment::STATUS_RTO_DELIVERED)->rto_delivered_at->not->toBeNull()
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_RTO_DELIVERED)
        ->and($order->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED)
        ->and(Payment::query()->forOrder($order->id)->value('status'))->toBe(Payment::STATUS_CANCELLED)
        ->and($variant->fresh()->stock)->toBe(8);

    app(RtoService::class)->confirmReceipt($shipment, true);

    expect($variant->fresh()->stock)->toBe(10)
        ->and($shipment->fresh()->rto_restocked)->toBe(1);
});

test('the poll job resyncs stale booked shipments only', function () {
    [, , $stale] = bookedShipment();
    [, , $fresh] = bookedShipment();
    $stale->forceFill(['last_synced_at' => now()->subHours(6)])->save();
    $fresh->forceFill(['last_synced_at' => now()->subHour()])->save();
    fakeCourier()->events = [scan('picked_up', '-1 hour')];

    $this->artisan('logistics:poll-tracking')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(Shipment::STATUS_PICKED_UP)
        ->and($stale->fresh()->last_synced_at->greaterThan(now()->subMinute()))->toBeTrue()
        ->and($fresh->fresh()->status)->toBe(Shipment::STATUS_AWB_ASSIGNED);
});

test('Logistics Ops record scans of a manual-courier shipment', function () {
    logisticsSettings(['default_courier' => 'manual']);
    [, $sellerOrder, $shipment] = logisticsOrder();
    $service = app(ShipmentService::class);
    $shipment = $service->recordManualAwb($service->book($shipment), 'MAN998877', null, null);

    app(TrackingService::class)->recordManualStatus($shipment, 'picked_up', 'Pune', 'Handed over');
    app(TrackingService::class)->recordManualStatus($shipment, 'delivered', 'Mumbai', null);

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_DELIVERED)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_DELIVERED);

    expect(fn () => app(TrackingService::class)->recordManualStatus($shipment->fresh(), 'in_transit', null, null))
        ->toThrow(ShipmentException::class);
});
