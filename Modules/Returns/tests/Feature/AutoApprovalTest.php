<?php

use Modules\Logistics\Models\Shipment;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
});

test('a small return with a fault reason is approved and its pickup booked', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();

    $return = raiseReturn($sellerOrder, $customer);
    $shipment = Shipment::query()->findOrFail($return->reverse_shipment_id);

    expect($return->status)->toBe(ReturnRequest::STATUS_PICKUP_SCHEDULED)
        ->and($return->isAutoApproved())->toBeTrue()
        ->and($return->decided_by)->toBe('system')
        ->and($shipment->direction)->toBe(Shipment::DIRECTION_REVERSE)
        ->and($shipment->return_request_id)->toBe($return->id)
        ->and($shipment->awb)->not->toBeNull()
        ->and($shipment->status)->toBe(Shipment::STATUS_PICKUP_SCHEDULED)
        ->and($shipment->pickup_address['pincode'] ?? null)->toBe($return->pickup_address['pincode']);
});

test('the review rules hold a return for CX', function (string $case) {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => $case === 'value' ? '2500' : '499']);
    $reason = 'wrong_size';

    match ($case) {
        'changed_mind' => $reason = 'changed_mind',
        'setting_off' => returnsSettings(['auto_approve' => 2]),
        'return_rate' => ReturnRequest::factory()->create(['customer_id' => $customer->id, 'status' => ReturnRequest::STATUS_REFUNDED]),
        default => null,
    };

    $return = raiseReturn($sellerOrder, $customer, ['reasonCode' => $reason]);

    expect($return->status)->toBe(ReturnRequest::STATUS_REQUESTED)
        ->and($return->reverse_shipment_id)->toBeNull();
})->with(['changed_mind', 'value', 'setting_off', 'return_rate']);

test('trusted customers are refunded at pickup', function () {
    $customer = orderCustomer();
    deliveredOrder(customer: $customer);
    deliveredOrder(customer: $customer);
    [, $sellerOrder] = deliveredOrder(customer: $customer);

    $return = raiseReturn($sellerOrder, $customer);

    expect($return->refund_on)->toBe(ReturnRequest::REFUND_ON_PICKUP);

    $newCustomer = orderCustomer();
    [, $other] = deliveredOrder(customer: $newCustomer);

    expect(raiseReturn($other, $newCustomer)->refund_on)->toBe(ReturnRequest::REFUND_ON_QC);
});

test('a courier failure keeps the approval and flags the return', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    fakeCourier()->failWith = 'Courier down';

    $return = raiseReturn($sellerOrder, $customer);

    expect($return->status)->toBe(ReturnRequest::STATUS_APPROVED)
        ->and($return->needsAttention())->toBeTrue();

    fakeCourier()->failWith = null;
    $return = app(ReturnService::class)->schedulePickup($return);

    expect($return->status)->toBe(ReturnRequest::STATUS_PICKUP_SCHEDULED)
        ->and($return->needsAttention())->toBeFalse();
});
