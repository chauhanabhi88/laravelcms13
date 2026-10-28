<?php

use Modules\Customer\Models\Customer;
use Modules\Logistics\Models\Shipment;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\PaymentRefund;
use Modules\Product\Models\ProductVariant;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
});

/**
 * A delivered order plus another in-stock variant of the same product: [order, seller order, variant, customer, other variant].
 *
 * @return array{0: SalesOrder, 1: SellerOrder, 2: ProductVariant, 3: Customer, 4: ProductVariant}
 */
function exchangeOrder(string $method = CheckoutSession::METHOD_PREPAID, int $otherStock = 5): array
{
    [$order, $sellerOrder, $variant, $customer] = deliveredOrder(['price' => '599'], 1, $method);
    $other = ProductVariant::factory()->create([
        'product_id' => $variant->product_id,
        'seller_id' => $variant->seller_id,
        'combination_key' => 'factory:2',
        'price' => '599',
        'stock' => $otherStock,
    ]);

    return [$order, $sellerOrder, $variant, $customer, $other];
}

function raiseExchange(SellerOrder $sellerOrder, Customer $customer, ProductVariant $other): ReturnRequest
{
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    return raiseReturn($sellerOrder, $customer, [
        'type' => ReturnRequest::TYPE_EXCHANGE,
        'items' => [$item->id => ['quantity' => 1, 'exchange_variant_id' => $other->id]],
        'refundMode' => null,
    ]);
}

test('a passed QC creates a zero-value seller order for the new size with stock reserved', function () {
    [$order, $sellerOrder, , $customer, $other] = exchangeOrder();
    $return = raiseExchange($sellerOrder, $customer, $other);

    expect($return->refund_amount->isZero())->toBeTrue()
        ->and($return->status)->toBe(ReturnRequest::STATUS_PICKUP_SCHEDULED);

    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    app(ReturnService::class)->qcPass($return->refresh());

    $exchange = SellerOrder::query()->findOrFail($return->refresh()->exchange_seller_order_id);
    $line = SalesOrderItem::query()->where('seller_order_id', $exchange->id)->firstOrFail();
    $shipment = Shipment::query()->forward()->where('seller_order_id', $exchange->id)->firstOrFail();

    expect($return->status)->toBe(ReturnRequest::STATUS_EXCHANGE_ORDER_CREATED)
        ->and($exchange->sales_order_id)->toBe($order->id)
        ->and($exchange->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE)
        ->and($exchange->sub_order_no)->toBe($order->order_no.'-2')
        ->and($exchange->items_subtotal->isZero())->toBeTrue()
        ->and($line->variant_id)->toBe($other->id)
        ->and($line->price->isZero())->toBeTrue()
        ->and($line->return_type)->toBe('exchange_only')
        ->and(orderVariant($other)->reserved)->toBe(1)
        ->and($shipment->payment_mode)->toBe(Shipment::PAYMENT_PREPAID)
        ->and($shipment->cod_amount->isZero())->toBeTrue()
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->value('status'))->toBe(SalesOrderItem::STATUS_EXCHANGED)
        ->and(PaymentRefund::query()->where('return_request_id', $return->id)->exists())->toBeFalse();
});

test('a COD order\'s replacement ships without cash to collect', function () {
    [, $sellerOrder, , $customer, $other] = exchangeOrder(CheckoutSession::METHOD_COD);
    $return = raiseExchange($sellerOrder, $customer, $other);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);

    app(ReturnService::class)->qcPass($return->refresh());
    $exchange = SellerOrder::query()->findOrFail($return->refresh()->exchange_seller_order_id);

    expect(Shipment::query()->forward()->where('seller_order_id', $exchange->id)->firstOrFail())
        ->payment_mode->toBe(Shipment::PAYMENT_PREPAID);
});

test('an out-of-stock replacement falls back to a refund', function () {
    [, $sellerOrder, , $customer, $other] = exchangeOrder();
    $return = raiseExchange($sellerOrder, $customer, $other);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    ProductVariant::query()->whereKey($other->id)->update(['stock' => 0]);

    app(ReturnService::class)->qcPass($return->refresh());

    expect($return->refresh()->exchange_seller_order_id)->toBeNull()
        ->and($return->status)->toBe(ReturnRequest::STATUS_REFUND_INITIATED)
        ->and(PaymentRefund::query()->where('return_request_id', $return->id)->value('amount')->toDecimal())->toBe('599.00')
        ->and(SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->value('status'))->toBe(SalesOrderItem::STATUS_RETURNED);
});

test('the new variant must be another in-stock variant of the same product', function () {
    [, $sellerOrder, , $customer] = exchangeOrder();
    $foreign = cartVariant(['stock' => 5]);

    expect(fn () => raiseExchange($sellerOrder, $customer, $foreign))
        ->toThrow(fn (ReturnException $e) => expect($e->reasonCode)->toBe('exchange_variant_invalid'));
});
