<?php

use Modules\Logistics\DTO\ServiceabilityResult;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\CourierAssignmentService;
use Modules\Logistics\Services\ShipmentService;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->courier = logisticsSetup();
});

test('a confirmed COD order gets one planned shipment with the package and the COD amount', function () {
    [$order, $sellerOrder, $shipment] = logisticsOrder(['weight_grams' => 400, 'length_cm' => '20', 'breadth_cm' => '15', 'height_cm' => '4'], 2);

    expect($shipment)
        ->status->toBe(Shipment::STATUS_CREATED)
        ->direction->toBe(Shipment::DIRECTION_FORWARD)
        ->payment_mode->toBe(Shipment::PAYMENT_COD)
        ->weight_grams->toBe(800)
        ->length_cm->toBe('20.0')
        ->awb->toBeNull()
        ->and($shipment->cod_amount->toDecimal())->toBe($order->grand_total->toDecimal())
        ->and((int) $shipment->items()->sum('quantity'))->toBe(2)
        ->and($sellerOrder->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE)
        ->and(fakeCourier()->called('createShipment'))->toBe(0)
        ->and(fakeCourier()->called('assignAwb'))->toBe(0);

    // Planning again returns the same row.
    expect(app(ShipmentService::class)->plan($sellerOrder)->id)->toBe($shipment->id)
        ->and(Shipment::query()->where('seller_order_id', $sellerOrder->id)->count())->toBe(1);
});

test('booking assigns the AWB of the chosen carrier, records charges and accepts the seller order', function () {
    [, $sellerOrder, $shipment] = logisticsOrder();

    $booked = app(ShipmentService::class)->book($shipment);

    expect($booked)
        ->status->toBe(Shipment::STATUS_AWB_ASSIGNED)
        ->courier_id->toBe($this->courier->id)
        ->carrier_id->toBe('11')
        ->carrier_name->toBe('Fake Fast')
        ->strategy->toBe('recommended')
        ->provider_order_id->toBe('FO-'.$shipment->id)
        ->and($booked->awb)->toStartWith('FAKE')
        ->and($booked->freight_charge->toDecimal())->toBe('60.00')
        ->and($booked->cod_charge->toDecimal())->toBe('25.00')
        ->and($booked->edd?->toDateString())->toBe(today()->addDays(2)->toDateString())
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP)
        ->and($sellerOrder->fresh()->invoice_no)->not->toBeNull();

    expect(fn () => app(ShipmentService::class)->book($booked))->toThrow(ShipmentException::class);
});

test('nothing serviceable flags the shipment for Logistics Ops', function () {
    [, $sellerOrder, $shipment] = logisticsOrder();
    fakeCourier()->quote = ServiceabilityResult::none();
    cache()->flush();

    $result = app(ShipmentService::class)->book($shipment);

    expect($result)
        ->status->toBe(Shipment::STATUS_CREATED)
        ->awb->toBeNull()
        ->and($result->needsAttention())->toBeTrue()
        ->and($result->attention_reason)->toBe(trans('logistics::shipment.attention.not_serviceable'))
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE);
});

test('a courier error is flagged on the shipment and rethrown', function () {
    [, , $shipment] = logisticsOrder();

    // Warm the serviceability cache, so the next driver call (the order creation) fails.
    app(CourierAssignmentService::class)->assign($shipment);
    fakeCourier()->failWith = 'Courier down';

    expect(fn () => app(ShipmentService::class)->book($shipment))->toThrow(CourierException::class, 'Courier down');

    expect($shipment->fresh())
        ->status->toBe(Shipment::STATUS_CREATED)
        ->needs_attention->toBe(1)
        ->attention_reason->toContain('Courier down');
});

test('with the manual courier Ops enter the AWB by hand', function () {
    $manual = Courier::query()->where('code', 'manual')->firstOrFail();
    logisticsSettings(['default_courier' => 'manual']);
    [, $sellerOrder, $shipment] = logisticsOrder();

    $result = app(ShipmentService::class)->book($shipment);

    expect($result)
        ->status->toBe(Shipment::STATUS_CREATED)
        ->courier_id->toBe($manual->id)
        ->attention_reason->toBe(trans('logistics::shipment.attention.awb_pending'));

    $booked = app(ShipmentService::class)->recordManualAwb($result, 'MAN123456', 'Local Express', 'https://local.example.test/MAN123456');

    expect($booked)
        ->status->toBe(Shipment::STATUS_AWB_ASSIGNED)
        ->awb->toBe('MAN123456')
        ->carrier_name->toBe('Local Express')
        ->needs_attention->toBe(2)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP);
});

test('cancelling the seller order cancels its booked shipment with the courier', function () {
    [$order, $sellerOrder, $shipment] = logisticsOrder();
    app(ShipmentService::class)->book($shipment);

    app(OrderCancellationService::class)->cancelItems($sellerOrder->fresh(), null, 'admin', 'Out of stock');

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_CANCELLED)
        ->and(fakeCourier()->called('cancel'))->toBe(1)
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->value('status'))->toBe(SalesOrderItem::STATUS_CANCELLED);
});

test('labels, manifests and pickups go to the courier in one batch', function () {
    [, , $first] = logisticsOrder();
    [, , $second] = logisticsOrder();
    $service = app(ShipmentService::class);
    $shipments = [$service->book($first), $service->book($second)];

    $label = $service->generateLabels($shipments);
    $manifest = $service->generateManifest($shipments);
    $pickup = $service->schedulePickup($shipments, today()->addDay());

    expect($label)->toContain('labels.example.test')
        ->and($manifest)->toContain('manifests.example.test')
        ->and($pickup->scheduled)->toBeTrue()
        ->and($first->fresh())
        ->status->toBe(Shipment::STATUS_PICKUP_SCHEDULED)
        ->label_url->toBe($label)
        ->manifest_url->toBe($manifest)
        ->and($first->fresh()->pickup_scheduled_at)->not->toBeNull();
});
