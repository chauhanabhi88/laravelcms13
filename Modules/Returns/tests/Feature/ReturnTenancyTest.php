<?php

use Laravel\Passport\Passport;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Returns\Models\ReturnRequest;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
    returnsSettings(['auto_approve' => 2]);
});

test('customers see only their own returns and orders', function () {
    [$order, $sellerOrder, , $owner] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $owner);
    $stranger = orderCustomer();

    $this->actingAs($stranger, 'customer')
        ->get(frontRoute('customer.returns.show', ['returnNo' => $return->return_no]))
        ->assertNotFound();

    $this->post(frontRoute('customer.returns.cancel', ['returnNo' => $return->return_no]))->assertNotFound();
    $this->get(frontRoute('customer.returns.create', ['orderNo' => $order->order_no]))->assertNotFound();
    $this->get(frontRoute('customer.returns.index'))->assertSuccessful()->assertDontSee($return->return_no);

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_REQUESTED);
});

test('sellers see only returns of their own packages', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);
    $owner = fulfilmentOwner($sellerOrder->seller()->firstOrFail());
    $other = fulfilmentOwner(orderSeller());

    $this->actingAs($other, 'seller')
        ->get(frontRoute('seller.return.show', ['returnNo' => $return->return_no]))
        ->assertNotFound();
    $this->post(frontRoute('seller.return.qc', ['returnNo' => $return->return_no]), ['result' => 'passed'])->assertNotFound();
    $this->get(frontRoute('seller.return.index', [['tab' => 'incoming']]))->assertSuccessful()->assertDontSee($return->return_no);

    $this->actingAs($owner, 'seller')
        ->get(frontRoute('seller.return.index', [['tab' => 'incoming']]))
        ->assertSuccessful()
        ->assertSee($return->return_no);
});

test('API users cannot read another customer\'s return', function () {
    [, $sellerOrder, , $owner] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $owner);

    Passport::actingAs(orderCustomer(), [CustomerAuthService::API_SCOPE], 'api');

    $this->getJson(route('returns_api.show', ['returnNo' => $return->return_no]))->assertNotFound();
});
