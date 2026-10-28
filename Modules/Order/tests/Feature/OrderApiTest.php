<?php

use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
});

test('the summary returns totals, COD eligibility and serviceability', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999']);
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 2]]);
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->postJson(route('order_api.checkout.summary', []), [
        'address_id' => $customer->address()->value('id'),
        'payment_method' => 'cod',
    ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.serviceable', true)
        ->assertJsonPath('data.can_place', true)
        ->assertJsonPath('data.grand_total', '998.00')
        ->assertJsonPath('data.cod.allowed', true)
        ->assertJsonCount(1, 'data.groups');
})->skip(fn () => ! in_array('v1', (array) config('core.api_versions'), true), 'API v1 is not enabled');

test('placing over the API is idempotent per token and says what comes next', function () {
    $variant = cartVariant(['price' => '499']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');
    $payload = ['address_id' => $customer->address()->value('id'), 'payment_method' => 'prepaid', 'token' => (string) Str::uuid(), 'channel' => 'android'];
    $url = route('order_api.checkout.place', []);

    $this->postJson($url, $payload)
        ->assertCreated()
        ->assertJsonPath('data.next_action', 'pay')
        ->assertJsonPath('data.payment.amount', '499.00')
        ->assertJsonPath('data.order.status', SalesOrder::STATUS_PENDING_PAYMENT);

    $this->postJson($url, $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.already_placed', true);

    expect(SalesOrder::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(SalesOrder::query()->where('customer_id', $customer->id)->value('channel'))->toBe('android');
})->skip(fn () => ! in_array('v1', (array) config('core.api_versions'), true), 'API v1 is not enabled');

test('checkout errors come back with the reason and the step', function () {
    $customer = orderCustomer();
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->postJson(route('order_api.checkout.summary', []), ['address_id' => $customer->address()->value('id')])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.reason', 'cart_not_ready')
        ->assertJsonPath('errors.step', 'cart');
})->skip(fn () => ! in_array('v1', (array) config('core.api_versions'), true), 'API v1 is not enabled');

test('customers list, read and cancel only their own orders', function () {
    $customer = Customer::factory()->create();
    $order = SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create();
    $foreign = SalesOrder::factory()->withSellerOrders(1)->create();
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->getJson(route('order_api.orders.index', []))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_no', $order->order_no)
        ->assertJsonPath('meta.total', 1);

    $this->getJson(route('order_api.orders.show', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertJsonPath('data.order.seller_orders.0.sub_order_no', $order->order_no.'-1')
        ->assertJsonMissingPath('data.order.seller_orders.0.commission_total');

    $this->getJson(route('order_api.orders.show', ['orderNo' => $foreign->order_no]))->assertNotFound();
    $this->postJson(route('order_api.orders.cancel', ['orderNo' => $foreign->order_no]), ['reason' => 'other'])->assertNotFound();
})->skip(fn () => ! in_array('v1', (array) config('core.api_versions'), true), 'API v1 is not enabled');

test('the API cancels a real order before shipment', function () {
    $variant = cartVariant(['stock' => 4]);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $order = placeOrder($customer)->order;
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->postJson(route('order_api.orders.cancel', ['orderNo' => $order->order_no]), ['reason' => 'ordered_by_mistake'])
        ->assertSuccessful()
        ->assertJsonPath('data.order.status', SalesOrder::STATUS_CANCELLED)
        ->assertJsonPath('data.cancellable_item_ids', []);

    expect(SellerOrder::query()->where('sales_order_id', $order->id)->value('status'))->toBe(SellerOrder::STATUS_CANCELLED)
        ->and(orderVariant($variant)->reserved)->toBe(0);
})->skip(fn () => ! in_array('v1', (array) config('core.api_versions'), true), 'API v1 is not enabled');

test('the API needs a customer token', function () {
    $this->getJson(route('order_api.orders.index', []))->assertUnauthorized();
})->skip(fn () => ! in_array('v1', (array) config('core.api_versions'), true), 'API v1 is not enabled');
