<?php

use Modules\Logistics\Models\Shipment;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\PaymentService;
use Modules\Returns\Services\RtoRefundService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
});

/**
 * A packed order of the given method that comes back to the seller undelivered.
 *
 * @return array{0: SalesOrder, 1: SellerOrder}
 */
function rtoOrder(string $method): array
{
    $variant = cartVariant(['price' => '450', 'stock' => 5, 'weight_grams' => 400], orderSeller());
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 1]]);
    $result = placeOrder($customer, orderCheckout($customer, $method));

    if ($method === CheckoutSession::METHOD_PREPAID) {
        $fake = app(FakeGateway::class);
        app(PaymentService::class)->markCaptured($result->payment, $fake->fetch($fake->simulate($result->payment->gateway_order_id)['razorpay_payment_id']), 'webhook');
    }

    $sellerOrder = SellerOrder::query()->where('sales_order_id', $result->order->id)->firstOrFail();
    fulfilment()->accept($sellerOrder);
    fulfilmentScans($sellerOrder, ['picked_up', 'in_transit', 'rto_initiated', 'rto_in_transit', 'rto_delivered']);

    return [$result->order, $sellerOrder->refresh()];
}

test('a prepaid package returned to the seller is refunded automatically, once', function () {
    [$order, $sellerOrder] = rtoOrder(CheckoutSession::METHOD_PREPAID);
    $refund = PaymentRefund::query()->whereHas('payment', fn ($query) => $query->where('sales_order_id', $order->id))->firstOrFail();

    expect($sellerOrder->status)->toBe(SellerOrder::STATUS_RTO_DELIVERED)
        ->and($refund->mode)->toBe(PaymentRefund::MODE_SOURCE)
        ->and($refund->amount->toDecimal())->toBe('450.00')
        ->and($refund->return_request_id)->toBeNull();

    app(RtoRefundService::class)->refund(Shipment::query()->forward()->where('seller_order_id', $sellerOrder->id)->firstOrFail());

    expect(PaymentRefund::query()->whereHas('payment', fn ($query) => $query->where('sales_order_id', $order->id))->count())->toBe(1);
});

test('a COD package returned to the seller refunds nothing', function () {
    [$order] = rtoOrder(CheckoutSession::METHOD_COD);

    expect(PaymentRefund::query()->whereHas('payment', fn ($query) => $query->where('sales_order_id', $order->id))->exists())->toBeFalse();
});

test('the customer sees the package came back', function () {
    [$order, $sellerOrder] = rtoOrder(CheckoutSession::METHOD_PREPAID);

    $this->actingAs($order->customer()->firstOrFail(), 'customer')
        ->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertSee(trans('order::front.orders.rto_returned'))
        ->assertSee(trans('order::front.orders.rto_refund_note'));
});
