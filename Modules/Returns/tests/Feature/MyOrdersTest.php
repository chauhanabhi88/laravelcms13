<?php

use Illuminate\Support\Facades\Storage;
use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Logistics\Models\Shipment;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
    returnsSettings(['auto_approve' => 2]);
});

test('the order list shows the return window and the return link', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.orders.index'))
        ->assertSuccessful()
        ->assertSee('data-return-link', false)
        ->assertSee(frontRoute('customer.returns.create', ['orderNo' => $order->order_no, ['seller_order' => $sellerOrder->id]]), false)
        ->assertSee(trans('order::front.orders.invoice'));
});

test('the order page shows the timeline, courier, invoice and help', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();
    $awb = Shipment::query()->forward()->where('seller_order_id', $sellerOrder->id)->value('awb');

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertSee('data-timeline', false)
        ->assertSee($awb)
        ->assertSee(trans('logistics::front.timeline.steps.delivered'))
        ->assertSee('data-help', false)
        ->assertSee('data-return-link', false);
});

test('raised returns show on the order page', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertSee($return->return_no)
        ->assertDontSee('data-return-link', false);
});

test('an order not yet shipped offers no return', function () {
    [$order] = pendingFulfilmentOrder();

    $this->actingAs($order->customer()->firstOrFail(), 'customer')
        ->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertDontSee('data-return-link', false);
});

test('the owner downloads the package invoice, nobody else', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder(method: CheckoutSession::METHOD_PREPAID);
    $url = frontRoute('customer.orders.invoice', ['orderNo' => $order->order_no, 'sellerOrder' => $sellerOrder->id]);

    $this->actingAs(orderCustomer(), 'customer')->get($url)->assertNotFound();

    $response = $this->actingAs($customer, 'customer')->get($url)->assertSuccessful();

    expect($response->headers->get('content-disposition'))->toContain(str_replace('/', '-', (string) $sellerOrder->fresh()->invoice_no))
        ->and(Storage::disk('private')->allFiles())->not->toBeEmpty();
});

test('a cancelled package has no invoice', function () {
    [$order, $sellerOrder] = pendingFulfilmentOrder();
    app(OrderCancellationService::class)->cancelItems($sellerOrder, null, 'customer', 'Changed my mind');

    $this->actingAs($order->customer()->firstOrFail(), 'customer')
        ->get(frontRoute('customer.orders.invoice', ['orderNo' => $order->order_no, 'sellerOrder' => $sellerOrder->id]))
        ->assertNotFound();

    expect($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_CANCELLED);
});

test('an open return holds an account deletion', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    raiseReturn($sellerOrder, $customer);

    expect(app(AccountDeletionGuard::class)->blockingReason($customer))->not->toBeNull();
});
