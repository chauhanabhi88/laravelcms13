<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Logistics\Drivers\ShiprocketDriver;
use Modules\Logistics\DTO\ServiceabilityRequest;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\CourierDriverManager;
use Modules\Logistics\Support\Shiprocket\ShiprocketClient;
use Modules\Seller\Models\SellerWarehouse;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    logisticsSetup();
    config(['core.integrations.retries' => 0]);
    $this->courier = Courier::factory()->shiprocket('api@example.test', 'secret')->create();
    $this->driver = app(CourierDriverManager::class)->for($this->courier);
});

function shiprocketHttp(array $routes): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/v1/external/auth/login' => Http::response(shiprocketFixture('auth'))] + $routes);
}

test('the token is cached and a 401 logs in again once', function () {
    Http::swap(new Factory);
    Http::fake([
        '*/v1/external/auth/login' => Http::sequence()->push(['token' => 'token-one'])->push(['token' => 'token-two']),
        '*/v1/external/courier/serviceability*' => Http::sequence()
            ->push(shiprocketFixture('serviceability'))
            ->push(['message' => 'Token has expired'], 401)
            ->push(shiprocketFixture('serviceability')),
    ]);
    $request = new ServiceabilityRequest('560001', '110001', 450, false);

    $this->driver->serviceability($request);
    $this->driver->serviceability($request);

    $logins = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'auth/login'));
    $last = collect(Http::recorded())->last()[0];

    expect($logins)->toHaveCount(2)
        ->and($last->header('Authorization')[0])->toBe('Bearer token-two')
        ->and(Cache::get('logistics:shiprocket:token:'.sha1('api@example.test|https://apiv2.shiprocket.in')))->toBe('token-two');
});

test('serviceability sends the route, bucketed weight and COD flag, and reads the carriers', function () {
    shiprocketHttp(['*/v1/external/courier/serviceability*' => Http::response(shiprocketFixture('serviceability'))]);

    $result = $this->driver->serviceability(new ServiceabilityRequest('560001', '110001', 620, true));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'serviceability')
        && $request['pickup_postcode'] === '560001'
        && $request['delivery_postcode'] === '110001'
        && (float) $request['weight'] === 1.0
        && (int) $request['cod'] === 1);

    expect($result->carriers)->toHaveCount(3)
        ->and($result->recommendedId)->toBe('43')
        ->and($result->find('51')?->rate->toDecimal())->toBe('58.00')
        ->and($result->find('77')?->cod)->toBeFalse()
        ->and($result->find('43')?->etaDays)->toBe(4);
});

test('an order is created, its AWB assigned, then label and pickup requested with the provider ids', function () {
    shiprocketHttp([
        '*/v1/external/orders/create/adhoc' => Http::response(shiprocketFixture('order_created')),
        '*/v1/external/courier/assign/awb' => Http::response(shiprocketFixture('awb_assigned')),
        '*/v1/external/courier/generate/label' => Http::response(['label_created' => 1, 'label_url' => 'https://labels.example.test/a.pdf']),
        '*/v1/external/courier/generate/pickup' => Http::response(['pickup_status' => 1, 'response' => ['pickup_scheduled_date' => '2026-10-30 10:00:00', 'pickup_token_number' => 'TOKEN 1']]),
    ]);
    [$order, $sellerOrder, $shipment] = logisticsOrder(['weight_grams' => 450], 2);
    $shipment->forceFill(['pickup_location_code' => 'WH-TEST-1'])->save();

    $provider = $this->driver->createShipment($shipment);
    $shipment->forceFill(['provider_order_id' => $provider->orderId, 'provider_shipment_id' => $provider->shipmentId])->save();
    $awb = $this->driver->assignAwb($shipment, '43');
    $shipment->forceFill(['awb' => $awb->awb])->save();
    $label = $this->driver->generateLabel([$shipment]);
    $pickup = $this->driver->schedulePickup([$shipment], now()->addDay());

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'orders/create/adhoc')
        && $request['order_id'] === $sellerOrder->sub_order_no
        && $request['pickup_location'] === 'WH-TEST-1'
        && $request['payment_method'] === 'COD'
        && $request['sub_total'] === $order->grand_total->toDecimal()
        && (float) $request['weight'] === 0.9
        && $request['order_items'][0]['units'] === 2
        && strlen($request['shipping_phone']) === 10);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'assign/awb')
        && $request['shipment_id'] === '449876543' && $request['courier_id'] === '43');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'generate/label') && $request['shipment_id'] === [449876543]);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'generate/pickup') && $request['shipment_id'] === [449876543]);

    expect($provider->orderId)->toBe('451234567')
        ->and($awb->awb)->toBe('SR141123456789')
        ->and($awb->carrierName)->toBe('Swift Express Surface')
        ->and($awb->chargedWeightGrams)->toBe(500)
        ->and($label)->toBe('https://labels.example.test/a.pdf')
        ->and($pickup->reference)->toBe('TOKEN 1');
});

test('tracking maps the scans oldest first and keeps unknown statuses unmapped', function () {
    shiprocketHttp(['*/v1/external/courier/track/awb/*' => Http::response(shiprocketFixture('track'))]);
    $shipment = Shipment::factory()->booked($this->courier)->create(['awb' => 'SR141123456789']);

    $events = $this->driver->track($shipment);

    expect($events)->toHaveCount(3)
        ->and($events[0]->status)->toBeNull()
        ->and($events[1]->status)->toBe(Shipment::STATUS_PICKED_UP)
        ->and($events[1]->location)->toBe('Bengaluru')
        ->and($events[2]->status)->toBe(Shipment::STATUS_IN_TRANSIT)
        ->and($events[2]->providerStatusCode)->toBe('18');
});

test('a warehouse is registered as a pickup location', function () {
    shiprocketHttp(['*/v1/external/settings/company/addpickup' => Http::response(['success' => true])]);
    $warehouse = SellerWarehouse::query()->where('seller_id', orderSeller()->id)->firstOrFail();

    $code = $this->driver->syncPickupLocation($warehouse);

    expect($code)->toBe(ShiprocketDriver::pickupLocationName($warehouse))
        ->and(strlen($code))->toBeLessThanOrEqual(36);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'addpickup') && $request['pin_code'] === $warehouse->pincode && $request['pickup_location'] === $code);
});

test('API errors become courier exceptions and missing credentials are refused', function () {
    shiprocketHttp(['*/v1/external/courier/serviceability*' => Http::response(['message' => 'Delivery postcode not serviceable'], 422)]);

    expect(fn () => $this->driver->serviceability(new ServiceabilityRequest('560001', '110001', 450, false)))
        ->toThrow(CourierException::class, 'Delivery postcode not serviceable');

    $unconfigured = new ShiprocketDriver(new ShiprocketClient(null, null, 'https://apiv2.shiprocket.in'));

    expect(fn () => $unconfigured->serviceability(new ServiceabilityRequest('560001', '110001', 450, false)))
        ->toThrow(CourierException::class);
});
