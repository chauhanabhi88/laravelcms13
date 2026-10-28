<?php

use Modules\Core\Support\Money;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrderItem;
use Modules\Payment\Models\PaymentRefund;
use Modules\Returns\Services\ReturnRefundService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
    returnsSettings(['auto_approve' => 2]);
});

test('the delivery charge is refunded only when the seller is at fault', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '500']);
    $sellerOrder->forceFill(['delivery_charge_share' => Money::of('40')])->save();

    $refunds = app(ReturnRefundService::class);
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    expect($refunds->quote($sellerOrder->fresh(), [$item->id => 1], 'wrong_size')->total()->toDecimal())->toBe('500.00')
        ->and($refunds->quote($sellerOrder->fresh(), [$item->id => 1], 'damaged')->total()->toDecimal())->toBe('540.00');

    returnsSettings(['refund_delivery_fee_on_seller_fault' => 2]);

    expect($refunds->quote($sellerOrder->fresh(), [$item->id => 1], 'damaged')->total()->toDecimal())->toBe('500.00');
});

test('the delivery charge is refunded once per package', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '500'], 2);
    $sellerOrder->forceFill(['delivery_charge_share' => Money::of('40')])->save();
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    $first = raiseReturn($sellerOrder->fresh(), $customer, ['reasonCode' => 'damaged', 'items' => [$item->id => ['quantity' => 1, 'exchange_variant_id' => null]]]);

    expect($first->refund_amount->toDecimal())->toBe('540.00')
        ->and(app(ReturnRefundService::class)->quote($sellerOrder->fresh(), [$item->id => 1], 'damaged', null)->delivery->toDecimal())->toBe('0.00');
});

test('the coupon share of the returned units is deducted', function () {
    [, $sellerOrder] = deliveredOrder(['price' => '300'], 3);
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();
    $item->forceFill(['discount_allocated' => Money::of('90')])->save();

    $quote = app(ReturnRefundService::class)->quote($sellerOrder->fresh(), [$item->id => 2], 'wrong_size');

    expect($quote->lines[$item->id]->toDecimal())->toBe('540.00')
        ->and($quote->units[$item->id]->toDecimal())->toBe('270.00');
});

test('starting the same refund twice creates one refund', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '700'], 1, CheckoutSession::METHOD_PREPAID);
    $return = raiseReturn($sellerOrder, $customer);
    $refunds = app(ReturnRefundService::class);

    $first = $refunds->initiate($return);
    $second = $refunds->initiate($return->refresh());

    expect($second->id)->toBe($first->id)
        ->and(PaymentRefund::query()->where('return_request_id', $return->id)->count())->toBe(1)
        ->and($first->amount->toDecimal())->toBe('700.00');
});

test('a refund never exceeds what is left on the payment', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '700'], 1, CheckoutSession::METHOD_PREPAID);
    $return = raiseReturn($sellerOrder, $customer);
    $return->forceFill(['refund_amount' => Money::of('5000')])->save();

    expect(app(ReturnRefundService::class)->initiate($return)->amount->toDecimal())->toBe('700.00');
});
