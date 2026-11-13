<?php

use Illuminate\Support\Facades\Event;
use Modules\Customer\Models\Customer;
use Modules\Logistics\Events\NdrRaised;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Services\ShipmentService;
use Modules\Logistics\Services\TrackingService;
use Modules\Order\Models\SellerOrder;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    logisticsSetup();
    Event::fake([NdrRaised::class]);

    [$this->order, $this->sellerOrder, $shipment] = logisticsOrder(['stock' => 10]);
    $this->shipment = app(ShipmentService::class)->book($shipment);
    app(TrackingService::class)->apply($this->shipment, [scan('picked_up', '-2 days'), scan('out_for_delivery', '-1 day'), scan('ndr', '-20 hours', 'UNDELIVERED')]);

    $this->case = NdrCase::query()->where('shipment_id', $this->shipment->id)->firstOrFail();
    $this->customer = Customer::query()->findOrFail($this->order->customer_id);
    $this->actionUrl = frontRoute('customer.orders.delivery_issue', ['orderNo' => $this->order->order_no, 'sellerOrder' => $this->sellerOrder->id]);
    $this->orderUrl = frontRoute('customer.orders.show', ['orderNo' => $this->order->order_no]);
});

test('the order page asks the customer what to do after a failed delivery attempt', function () {
    expect($this->sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_NDR);

    $this->actingAs($this->customer, 'customer')->get($this->orderUrl)
        ->assertOk()
        ->assertSee('data-delivery-issue="'.$this->sellerOrder->id.'"', false)
        ->assertSee(trans('logistics::front.delivery_issue.actions.reattempt'))
        ->assertSee($this->actionUrl, false);
});

test('the customer can ask for a re-attempt at a new address', function () {
    $this->actingAs($this->customer, 'customer')
        ->post($this->actionUrl, ['action' => NdrCase::ACTION_CHANGE_ADDRESS, 'address1' => '12 MG Road, Indiranagar', 'phone' => '9876543210'])
        ->assertRedirect($this->orderUrl)
        ->assertSessionHas('success');

    $case = $this->case->fresh();

    expect($case)->status->toBe(NdrCase::STATUS_ACTIONED)
        ->customer_action->toBe(NdrCase::ACTION_CHANGE_ADDRESS)
        ->actioned_by->toBe('customer:'.$this->customer->id)
        ->and($case->action_payload)->toMatchArray(['address1' => '12 MG Road, Indiranagar', 'phone' => '9876543210'])
        ->and(fakeCourier()->called('ndrAction'))->toBe(1);

    // The page now shows the answer instead of the form.
    $this->actingAs($this->customer, 'customer')->get($this->orderUrl)
        ->assertSee('data-delivery-issue-answered="'.$this->sellerOrder->id.'"', false)
        ->assertDontSee('data-delivery-issue="'.$this->sellerOrder->id.'"', false);
});

test('a new address needs the address line and a valid phone', function () {
    $this->actingAs($this->customer, 'customer')
        ->from($this->orderUrl)
        ->post($this->actionUrl, ['action' => NdrCase::ACTION_CHANGE_ADDRESS, 'phone' => '12345'])
        ->assertRedirect($this->orderUrl)
        ->assertSessionHasErrors(['address1', 'phone']);

    expect($this->case->fresh()->status)->toBe(NdrCase::STATUS_OPEN);
});

test('an answered case cannot be answered twice', function () {
    $this->actingAs($this->customer, 'customer')->post($this->actionUrl, ['action' => NdrCase::ACTION_REATTEMPT]);

    $this->actingAs($this->customer, 'customer')
        ->post($this->actionUrl, ['action' => NdrCase::ACTION_CANCEL])
        ->assertNotFound();

    expect($this->case->fresh()->customer_action)->toBe(NdrCase::ACTION_REATTEMPT)
        ->and(fakeCourier()->called('ndrAction'))->toBe(1);
});

test('another customer gets a 404', function () {
    $other = Customer::factory()->create();

    $this->actingAs($other, 'customer')
        ->post($this->actionUrl, ['action' => NdrCase::ACTION_CANCEL])
        ->assertNotFound();

    expect($this->case->fresh()->status)->toBe(NdrCase::STATUS_OPEN);
});

test('guests are sent to sign in', function () {
    $this->post($this->actionUrl, ['action' => NdrCase::ACTION_CANCEL])->assertRedirect();

    expect($this->case->fresh()->status)->toBe(NdrCase::STATUS_OPEN);
});
