<?php

use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Logistics\Services\ShipmentService;
use Modules\Logistics\Services\TrackingService;
use Modules\Order\Models\SalesOrderAddress;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    logisticsSetup();
    [$this->order, $this->sellerOrder, $shipment] = logisticsOrder();
    $this->shipment = app(ShipmentService::class)->book($shipment);
    app(TrackingService::class)->apply($this->shipment, [scan('picked_up', '-1 hour', location: 'Bengaluru Hub')]);
});

test('the tracking page finds a shipment by AWB, sub-order or order number', function (string $by) {
    $reference = match ($by) {
        'awb' => $this->shipment->awb,
        'sub-order' => $this->sellerOrder->sub_order_no,
        'order' => $this->order->order_no,
    };

    $this->get(frontRoute('tracking.show', ['awbOrOrderNo' => $reference]))
        ->assertSuccessful()
        ->assertSee($this->sellerOrder->sub_order_no)
        ->assertSee($this->shipment->awb)
        ->assertSee('Bengaluru Hub')
        ->assertSee('Shipped');
})->with(['awb', 'sub-order', 'order']);

test('the tracking page shows only safe fields', function () {
    $address = SalesOrderAddress::query()->where('sales_order_id', $this->order->id)->where('type', 'shipping')->firstOrFail();

    $this->get(frontRoute('tracking.show', ['awbOrOrderNo' => $this->shipment->awb]))
        ->assertSuccessful()
        ->assertDontSee($address->name)
        ->assertDontSee($address->phone)
        ->assertDontSee($address->address_line1)
        ->assertDontSee($this->order->grand_total->format());
});

test('an unknown number is a 404 and the search form redirects', function () {
    $this->get(frontRoute('tracking.show', ['awbOrOrderNo' => 'NOPE-123']))->assertNotFound();

    $this->get(frontRoute('tracking.index'))->assertSuccessful()->assertSee(trans('logistics::front.tracking.intro'));
    $this->get(frontRoute('tracking.index').'?q='.$this->shipment->awb)
        ->assertRedirect(frontRoute('tracking.show', ['awbOrOrderNo' => $this->shipment->awb]));
});

test('the tracking page is throttled', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->get(frontRoute('tracking.show', ['awbOrOrderNo' => 'NOPE-'.$i]));
    }

    $this->get(frontRoute('tracking.show', ['awbOrOrderNo' => $this->shipment->awb]))->assertStatus(429);
});

test('the API returns the tracking of the customer\'s own order only', function () {
    $owner = $this->order->customer()->first();
    $stranger = orderCustomer();

    Passport::actingAs($stranger, [CustomerAuthService::API_SCOPE], 'api');
    $this->getJson(route('logistics_api.orders.tracking', ['orderNo' => $this->order->order_no]))->assertNotFound();

    Passport::actingAs($owner, [CustomerAuthService::API_SCOPE], 'api');
    $this->getJson(route('logistics_api.orders.tracking', ['orderNo' => $this->order->order_no]))
        ->assertSuccessful()
        ->assertJsonPath('data.order_no', $this->order->order_no)
        ->assertJsonPath('data.packages.0.awb', $this->shipment->awb)
        ->assertJsonPath('data.packages.0.steps.2.state', 'current')
        ->assertJsonPath('data.packages.0.scans.0.location', 'Bengaluru Hub');

    expect(DB::table('shipment_event')->count())->toBeGreaterThan(0);
});
