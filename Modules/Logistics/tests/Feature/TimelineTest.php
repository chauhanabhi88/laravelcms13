<?php

use Modules\Logistics\Services\ShipmentService;
use Modules\Logistics\Services\TrackingService;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    logisticsSetup();
});

/**
 * @param  list<string>  $statuses
 * @return array<string, mixed>
 */
function timelineAfter(array $statuses): array
{
    [, $sellerOrder, $shipment] = logisticsOrder();
    $shipment = app(ShipmentService::class)->book($shipment);
    $minutes = count($statuses) * 10;

    $events = [];
    foreach ($statuses as $status) {
        $events[] = scan($status, '-'.$minutes.' minutes');
        $minutes -= 10;
    }

    app(TrackingService::class)->apply($shipment, $events);

    return app(TrackingService::class)->timeline($sellerOrder->fresh());
}

/**
 * @param  array<string, mixed>  $timeline
 * @return array<string, string>
 */
function stepStates(array $timeline): array
{
    return collect($timeline['steps'])->pluck('state', 'key')->all();
}

test('a delivered order has every step done', function () {
    $timeline = timelineAfter(['picked_up', 'out_for_delivery', 'delivered']);

    expect(stepStates($timeline))->toBe(['ordered' => 'done', 'packed' => 'done', 'shipped' => 'done', 'out_for_delivery' => 'done', 'delivered' => 'done'])
        ->and($timeline['branch'])->toBeNull()
        ->and($timeline['edd'])->toBeNull()
        ->and($timeline['scans'])->toHaveCount(3)
        ->and($timeline['scans'][0]['label'])->toBe('Delivered')
        ->and($timeline['steps'][4]['at'])->not->toBeNull();
});

test('an order in transit is current at shipped', function () {
    $timeline = timelineAfter(['picked_up', 'in_transit']);

    expect(stepStates($timeline))->toBe(['ordered' => 'done', 'packed' => 'done', 'shipped' => 'current', 'out_for_delivery' => 'pending', 'delivered' => 'pending'])
        ->and($timeline['awb'])->toStartWith('FAKE')
        ->and($timeline['courier'])->toBe('Fake Fast')
        ->and($timeline['edd'])->not->toBeNull();
});

test('a cancelled order ends with the cancelled branch', function () {
    [, $sellerOrder] = logisticsOrder();
    app(OrderCancellationService::class)->cancelItems($sellerOrder, null, 'customer', 'Changed my mind');

    $timeline = app(TrackingService::class)->timeline($sellerOrder->fresh());

    expect($timeline['status'])->toBe(SellerOrder::STATUS_CANCELLED)
        ->and($timeline['branch']['key'])->toBe('cancelled')
        ->and($timeline['branch']['note'])->toBe('Changed my mind')
        ->and(collect($timeline['events'])->pluck('label')->all())->toBe(['Ordered', 'Cancelled']);
});

test('a failed attempt and a return to origin show their branches', function () {
    $ndr = timelineAfter(['picked_up', 'out_for_delivery', 'ndr']);
    $rto = timelineAfter(['picked_up', 'rto_initiated', 'rto_delivered']);

    expect($ndr['branch']['key'])->toBe('delivery_failed')
        ->and($ndr['branch']['note'])->toBe('1 delivery attempt')
        ->and(collect($ndr['events'])->last()['state'])->toBe('current')
        ->and($rto['branch']['key'])->toBe('returned')
        ->and(collect($rto['events'])->pluck('label')->all())->toBe(['Ordered', 'Packed', 'Shipped', 'Returned to seller']);
});
