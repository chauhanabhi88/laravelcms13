<?php

use Laravel\Passport\Passport;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Order\Models\SalesOrderItem;
use Modules\Returns\Models\ReturnRequest;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
    returnsSettings(['auto_approve' => 2]);
});

test('the API lists the returnable items of an order', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->getJson(route('returns_api.returnable_items', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertJsonPath('data.packages.0.seller_order_id', $sellerOrder->id)
        ->assertJsonPath('data.packages.0.items.0.max_qty', 1)
        ->assertJsonPath('data.packages.0.items.0.allowed_types', ['return'])
        ->assertJsonPath('data.payment_method', 'cod');
});

test('a return is raised over the API with photos and UPI details', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '350']);
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $response = $this->post(route('returns_api.store'), [
        'seller_order_id' => $sellerOrder->id,
        'type' => 'return',
        'reason_code' => 'damaged',
        'items' => [['item_id' => $item->id, 'quantity' => 1]],
        'photos' => [returnPhoto()],
        'refund_mode' => 'upi',
        'vpa' => 'asha@okbank',
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.refund_amount', '350.00')
        ->assertJsonPath('data.refund_to', 'asha@okbank')
        ->assertJsonPath('data.photos_count', 1);

    $returnNo = $response->json('data.return_no');

    $this->getJson(route('returns_api.index'))->assertSuccessful()->assertJsonPath('data.0.return_no', $returnNo);
    $this->getJson(route('returns_api.show', ['returnNo' => $returnNo]))->assertSuccessful()->assertJsonPath('data.cancellable', true);
    $this->postJson(route('returns_api.cancel', ['returnNo' => $returnNo]))->assertSuccessful()->assertJsonPath('data.status', 'cancelled');
});

test('API validation and business errors use the envelope', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->postJson(route('returns_api.store'), ['type' => 'return'])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['seller_order_id', 'reason_code', 'items']]);

    $this->postJson(route('returns_api.store'), [
        'seller_order_id' => $sellerOrder->id,
        'type' => 'return',
        'reason_code' => 'defective',
        'items' => [['item_id' => $item->id, 'quantity' => 1]],
        'refund_mode' => 'upi',
        'vpa' => 'asha@okbank',
    ])->assertUnprocessable()->assertJsonPath('errors.reason', 'photos_required');

    expect(ReturnRequest::query()->count())->toBe(0);
});

test('the API needs a customer token', function () {
    $this->getJson(route('returns_api.index'))->assertUnauthorized();
});
