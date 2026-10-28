<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnRequest;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
    returnsSettings(['auto_approve' => 2]);
});

test('a return gets a number, its items and the refund amount', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '650'], 2);

    $return = raiseReturn($sellerOrder, $customer);

    expect($return->return_no)->toMatch('/^RET-\d{4}-\d{6}$/')
        ->and($return->status)->toBe(ReturnRequest::STATUS_REQUESTED)
        ->and($return->refund_amount->toDecimal())->toBe('650.00')
        ->and($return->items()->firstOrFail()->unit_refund->toDecimal())->toBe('650.00')
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_RETURN_REQUESTED)
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->value('status'))->toBe(SalesOrderItem::STATUS_RETURN_REQUESTED)
        ->and($return->pickup_address['pincode'] ?? null)->not->toBeNull();
});

test('photos are required for a defective item', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();

    expect(fn () => raiseReturn($sellerOrder, $customer, ['reasonCode' => 'defective', 'photos' => []]))
        ->toThrow(fn (ReturnException $e) => expect($e->reasonCode)->toBe('photos_required'));
});

test('photos are optional for a size issue and stored privately next to the return', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $photo = draftPhoto($customer);

    $return = raiseReturn($sellerOrder, $customer, ['reasonCode' => 'damaged', 'photos' => [$photo]]);
    $stored = $return->images()->firstOrFail();

    expect($stored->path)->toStartWith('returns/'.$return->return_no.'/')
        ->and(Storage::disk('private')->exists($stored->path))->toBeTrue()
        ->and(Storage::disk('private')->exists($photo['path']))->toBeFalse()
        ->and(raiseReturn(deliveredOrder(customer: $customer)[1], $customer, ['reasonCode' => 'wrong_size', 'photos' => []])->images()->count())->toBe(0);
});

test('another customer\'s draft photos are refused', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $foreign = draftPhoto(orderCustomer());

    expect(fn () => raiseReturn($sellerOrder, $customer, ['reasonCode' => 'damaged', 'photos' => [$foreign]]))
        ->toThrow(ReturnException::class);
});

test('COD refund details are required and stored encrypted', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();

    expect(fn () => raiseReturn($sellerOrder, $customer, ['refundMode' => null, 'beneficiary' => null]))
        ->toThrow(fn (ReturnException $e) => expect($e->reasonCode)->toBe('refund_details_missing'));

    $return = raiseReturn($sellerOrder, $customer, ['refundMode' => 'bank', 'beneficiary' => ['account_holder' => 'Asha', 'account_number' => '123456789012', 'ifsc' => 'hdfc0001234']]);
    $raw = (string) DB::table('return_request')->where('id', $return->id)->value('refund_beneficiary');

    expect($raw)->not->toContain('123456789012')
        ->and($return->fresh()->refund_beneficiary['ifsc'])->toBe('HDFC0001234')
        ->and($return->fresh()->maskedBeneficiary())->toBe('XXXXXXXX9012 / HDFC0001234')
        ->and($return->toArray())->not->toHaveKey('refund_beneficiary');
});

test('a prepaid return refunds to source without details', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(method: CheckoutSession::METHOD_PREPAID);

    $return = raiseReturn($sellerOrder, $customer);

    expect($return->refund_mode)->toBe('source')->and($return->refund_beneficiary)->toBeNull();
});

test('more than the remaining quantity is refused', function () {
    [, $sellerOrder, , $customer] = deliveredOrder([], 2);
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    expect(fn () => raiseReturn($sellerOrder, $customer, ['items' => [$item->id => ['quantity' => 3, 'exchange_variant_id' => null]]]))
        ->toThrow(fn (ReturnException $e) => expect($e->reasonCode)->toBe('qty_invalid'));
});

test('a saved address in another pincode cannot be the pickup address', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $elsewhere = orderAddress($customer, orderPincode());

    expect(fn () => raiseReturn($sellerOrder, $customer, ['pickupAddressId' => $elsewhere->id]))
        ->toThrow(fn (ReturnException $e) => expect($e->reasonCode)->toBe('pickup_address_invalid'));
});
