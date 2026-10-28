<?php

use Illuminate\Support\Carbon;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SellerOrder;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
});

test('the seller records a passed QC from the return page', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(method: CheckoutSession::METHOD_PREPAID);
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    $owner = fulfilmentOwner($sellerOrder->seller()->firstOrFail());

    $this->actingAs($owner, 'seller')
        ->get(frontRoute('seller.return.index'))
        ->assertSuccessful()
        ->assertSee($return->return_no);

    $this->get(frontRoute('seller.return.show', ['returnNo' => $return->return_no]))
        ->assertSuccessful()
        ->assertSee('data-qc-form', false);

    $this->post(frontRoute('seller.return.qc', ['returnNo' => $return->return_no]), ['result' => 'passed'])
        ->assertRedirect(frontRoute('seller.return.show', ['returnNo' => $return->return_no]))
        ->assertSessionHas('success');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_REFUND_INITIATED);
});

test('a failed QC needs a note and may raise a claim with photos', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    $owner = fulfilmentOwner($sellerOrder->seller()->firstOrFail());

    $this->actingAs($owner, 'seller')
        ->post(frontRoute('seller.return.qc', ['returnNo' => $return->return_no]), ['result' => 'failed'])
        ->assertSessionHasErrors('note');

    $this->post(frontRoute('seller.return.qc', ['returnNo' => $return->return_no]), [
        'result' => 'failed',
        'note' => 'Different item inside',
        'raise_claim' => '1',
        'claim_reason' => 'wrong_item_returned',
        'photos' => [returnPhoto()],
    ])->assertSessionHas('success');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_QC_FAILED)
        ->and(ReturnClaim::query()->where('return_request_id', $return->id)->value('reason'))->toBe('wrong_item_returned');
});

test('a seller claims within the window after a passed QC, not after it', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(method: CheckoutSession::METHOD_PREPAID);
    $return = raiseReturn($sellerOrder, $customer);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    app(ReturnService::class)->qcPass($return->refresh());
    $owner = fulfilmentOwner($sellerOrder->seller()->firstOrFail());
    $claim = ['reason' => 'missing_parts', 'description' => 'Belt missing', 'photos' => [returnPhoto()]];

    $this->actingAs($owner, 'seller')
        ->get(frontRoute('seller.return.show', ['returnNo' => $return->return_no]))
        ->assertSuccessful()
        ->assertSee('data-claim-form', false);

    Carbon::setTestNow(now()->addHours(49));
    $this->actingAs($owner, 'seller')
        ->post(frontRoute('seller.return_claim.store', ['returnNo' => $return->return_no]), $claim)
        ->assertSessionHas('error');
    Carbon::setTestNow();

    $this->post(frontRoute('seller.return_claim.store', ['returnNo' => $return->return_no]), $claim)
        ->assertSessionHas('success');

    expect(ReturnClaim::query()->where('return_request_id', $return->id)->count())->toBe(1);
});

test('an RTO package received damaged in the order desk becomes a claim', function () {
    $sellerOrder = rtoDeliveredOrder();
    $owner = fulfilmentOwner($sellerOrder->seller()->firstOrFail());

    $this->actingAs($owner, 'seller')
        ->post(frontRoute('seller.order.return_receipt', [$sellerOrder->id]), [
            'condition' => 'damaged',
            'note' => 'Box crushed',
            'photos' => [returnPhoto()],
        ])
        ->assertSessionHas('success');

    expect(ReturnClaim::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail())
        ->source->toBe(ReturnClaim::SOURCE_RTO)
        ->return_request_id->toBeNull()
        ->reason->toBe('damaged_return')
        ->description->toBe('Box crushed');
});

test('the desk keeps a customer return out of the RTO receipt', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    raiseReturn($sellerOrder, $customer);

    expect($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_RETURN_REQUESTED)
        ->and(fn () => fulfilment()->confirmReturnReceipt($sellerOrder->fresh(), 'good'))
        ->toThrow(OrderException::class);
});
