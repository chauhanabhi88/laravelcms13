<?php

use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\ShipmentEvent;
use Modules\Logistics\Services\ShipmentService;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\WebhookEvent;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    logisticsSetup();
});

function postShiprocketWebhook(array $payload, ?string $token = 'test-webhook-token', string $route = 'webhook.shiprocket')
{
    $server = $token === null ? [] : ['HTTP_X_API_KEY' => $token];

    return test()->call('POST', route($route), [], [], [], $server + ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
}

/**
 * The delivered fixture for a booked shipment of a new order.
 *
 * @return array{0: array<string, mixed>, 1: Shipment, 2: SellerOrder}
 */
function deliveredWebhook(): array
{
    [, $sellerOrder, $shipment] = logisticsOrder();
    $shipment = app(ShipmentService::class)->book($shipment);
    $payload = array_merge(shiprocketFixture('webhook_delivered'), ['awb' => $shipment->awb, 'order_id' => $sellerOrder->sub_order_no]);

    return [$payload, $shipment, $sellerOrder];
}

test('a webhook with a wrong or missing token is rejected and logged without its payload', function (?string $token) {
    [$payload, $shipment] = deliveredWebhook();

    postShiprocketWebhook($payload, $token)->assertUnauthorized();

    expect(WebhookEvent::query()->where('provider', 'shiprocket')->latest('id')->first())
        ->signature_valid->toBe(2)
        ->payload->toBeNull()
        ->and($shipment->fresh()->status)->toBe(Shipment::STATUS_AWB_ASSIGNED);
})->with(['wrong' => 'nope', 'missing' => null]);

test('a delivered webhook moves the shipment and the seller order', function () {
    [$payload, $shipment, $sellerOrder] = deliveredWebhook();

    postShiprocketWebhook($payload)->assertSuccessful()->assertJsonPath('success', true);

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_DELIVERED)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_DELIVERED)
        ->and(ShipmentEvent::query()->where('shipment_id', $shipment->id)->count())->toBe(3)
        ->and(WebhookEvent::query()->where('provider', 'shiprocket')->latest('id')->value('status'))->toBe(WebhookEvent::STATUS_PROCESSED);
});

test('a redelivered webhook is ignored', function () {
    [$payload, $shipment] = deliveredWebhook();

    postShiprocketWebhook($payload)->assertSuccessful();
    postShiprocketWebhook($payload)->assertSuccessful()->assertJsonPath('message', 'Duplicate event.');

    expect(WebhookEvent::query()->where('provider', 'shiprocket')->where('signature_valid', 1)->count())->toBe(1)
        ->and(ShipmentEvent::query()->where('shipment_id', $shipment->id)->count())->toBe(3);
});

test('an unknown courier status is kept as a scan without a transition', function () {
    [$payload, $shipment] = deliveredWebhook();
    $payload = array_merge($payload, ['current_status' => 'CUSTOMS HOLD', 'current_status_id' => 99, 'current_timestamp' => '2026-10-30 09:00:00', 'scans' => []]);

    postShiprocketWebhook($payload, route: 'webhook.courier_tracking')->assertSuccessful();

    expect(ShipmentEvent::query()->where('shipment_id', $shipment->id)->first())
        ->status->toBeNull()
        ->provider_status->toBe('CUSTOMS HOLD')
        ->and($shipment->fresh()->status)->toBe(Shipment::STATUS_AWB_ASSIGNED);
});

test('a webhook for an unknown shipment is ignored', function () {
    postShiprocketWebhook(array_merge(shiprocketFixture('webhook_delivered'), ['awb' => 'NOPE', 'order_id' => 'NOPE', 'sr_order_id' => 1]))->assertSuccessful();

    expect(WebhookEvent::query()->where('provider', 'shiprocket')->latest('id')->value('status'))->toBe(WebhookEvent::STATUS_IGNORED);
});
