<?php

use Illuminate\Support\Facades\DB;
use Modules\Cart\Models\CartItem;
use Modules\Core\Support\Money;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\DTO\PlaceOrderResult;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Exceptions\PriceChanged;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderAddress;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\PlaceOrderService;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\InventoryMovement;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
});

test('a cart from two sellers becomes one sales order with one seller order per seller', function () {
    $state = orderState('Karnataka');
    $sellerA = orderSeller($state);
    $sellerB = orderSeller(orderState('Maharashtra'));
    $a = cartVariant(['price' => '499', 'mrp' => '999', 'stock' => 10], $sellerA);
    $b = cartVariant(['price' => '250', 'mrp' => '300', 'stock' => 5], $sellerB);
    $customer = orderCustomer(orderPincode($state));
    orderCart($customer, [[$a, 2], $b]);

    $result = placeOrder($customer);
    $order = $result->order->fresh();

    expect($order)
        ->status->toBe(SalesOrder::STATUS_CONFIRMED)
        ->payment_method->toBe(Payment::METHOD_COD)
        ->items_count->toBe(3)
        ->and($order->total_mrp->toDecimal())->toBe('2298.00')
        ->and($order->total_discount->toDecimal())->toBe('1050.00')
        ->and($order->grand_total->toDecimal())->toBe('1248.00')
        ->and($order->order_no)->toMatch('/^ORD-\d{4}-\d{6}$/')
        ->and($result->nextAction())->toBe(PlaceOrderResult::NEXT_SUCCESS);

    $sellerOrders = SellerOrder::query()->where('sales_order_id', $order->id)->orderBy('id')->get();

    expect($sellerOrders)->toHaveCount(2)
        ->and($sellerOrders->pluck('seller_id')->all())->toBe([$sellerA->id, $sellerB->id])
        ->and($sellerOrders->pluck('status')->unique()->all())->toBe([SellerOrder::STATUS_PENDING_ACCEPTANCE])
        ->and($sellerOrders[0]->sub_order_no)->toBe($order->order_no.'-1')
        ->and($sellerOrders[0]->items_subtotal->toDecimal())->toBe('998.00')
        ->and($sellerOrders[0]->sla_dispatch_by)->not->toBeNull()
        ->and($sellerOrders[0]->warehouse_id)->not->toBeNull();

    expect(SalesOrderAddress::query()->where('sales_order_id', $order->id)->pluck('type')->sort()->values()->all())
        ->toBe([SalesOrderAddress::TYPE_BILLING, SalesOrderAddress::TYPE_SHIPPING]);
});

test('items snapshot price, MRP, tax split and commission', function () {
    $state = orderState('Karnataka');
    $local = cartVariant(['price' => '1050', 'mrp' => '1500'], orderSeller($state), ['gst_percent' => '5.00', 'hsn_code' => '6204']);
    $remote = cartVariant(['price' => '1180', 'mrp' => '1500'], orderSeller(orderState('Gujarat')), ['gst_percent' => '18.00']);
    $customer = orderCustomer(orderPincode($state));
    orderCart($customer, [$local, $remote]);

    $order = placeOrder($customer)->order;
    $items = SalesOrderItem::query()->where('sales_order_id', $order->id)->get()->keyBy('variant_id');

    $intra = $items[$local->id];
    expect($intra->price->toDecimal())->toBe('1050.00')
        ->and($intra->mrp->toDecimal())->toBe('1500.00')
        ->and($intra->hsn_code)->toBe('6204')
        ->and($intra->taxable_value->toDecimal())->toBe('1000.00')
        ->and($intra->cgst->toDecimal())->toBe('25.00')
        ->and($intra->sgst->toDecimal())->toBe('25.00')
        ->and($intra->igst->isZero())->toBeTrue()
        ->and($intra->commission_amount->isZero())->toBeTrue()
        ->and($intra->status)->toBe(SalesOrderItem::STATUS_ACTIVE);

    $inter = $items[$remote->id];
    expect($inter->taxable_value->toDecimal())->toBe('1000.00')
        ->and($inter->igst->toDecimal())->toBe('180.00')
        ->and($inter->cgst->isZero())->toBeTrue();
});

test('placing reserves stock and writes reserve movements against the order', function () {
    $variant = cartVariant(['stock' => 10]);
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 3]]);

    $order = placeOrder($customer)->order;

    expect(orderVariant($variant))->stock->toBe(10)->reserved->toBe(3)
        ->and(InventoryMovement::query()->where('variant_id', $variant->id)->where('type', InventoryMovement::TYPE_RESERVE)->value('reference_id'))->toBe($order->id);
});

test('the order totals equal the cart totals', function () {
    cartSettings(['free_delivery' => 'threshold', 'delivery_fee' => '49', 'free_delivery_min' => '1000', 'cod_fee' => '25']);
    $variant = cartVariant(['price' => '300', 'mrp' => '600']);
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 2]]);

    $order = placeOrder($customer)->order;
    $payment = Payment::query()->forOrder($order->id)->firstOrFail();

    expect($order->delivery_charge->toDecimal())->toBe('49.00')
        ->and($order->cod_fee->toDecimal())->toBe('25.00')
        ->and($order->grand_total->toDecimal())->toBe('674.00')
        ->and($payment->amount->toDecimal())->toBe('674.00')
        ->and(SellerOrder::query()->where('sales_order_id', $order->id)->firstOrFail()->delivery_charge_share->toDecimal())->toBe('49.00');
});

test('the purchased lines leave the cart', function () {
    $bought = cartVariant();
    $customer = orderCustomer();
    orderCart($customer, [$bought]);

    placeOrder($customer);

    expect(CartItem::query()->where('variant_id', $bought->id)->exists())->toBeFalse();
});

test('a repeated submit of the same checkout returns the same order', function () {
    $variant = cartVariant(['stock' => 10]);
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 2]]);
    $session = orderCheckout($customer);

    $first = placeOrder($customer, $session);
    $second = placeOrder($customer, $session);

    expect($second->alreadyPlaced)->toBeTrue()
        ->and($second->order->id)->toBe($first->order->id)
        ->and(SalesOrder::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(orderVariant($variant)->reserved)->toBe(2);
});

test('a total other than the one on the summary aborts with nothing written', function () {
    $variant = cartVariant(['price' => '499']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);

    expect(fn () => placeOrder($customer, null, Money::of('450')))->toThrow(PriceChanged::class);

    expect(SalesOrder::query()->where('customer_id', $customer->id)->exists())->toBeFalse()
        ->and(orderVariant($variant)->reserved)->toBe(0);
});

test('a price change since the cart was revalidated aborts the order', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    cartUpdateVariant($variant, ['price' => '549.00']);

    expect(fn () => placeOrder($customer))->toThrow(PriceChanged::class);
    expect(SalesOrder::query()->where('customer_id', $customer->id)->exists())->toBeFalse();

    // The buyer saw the new price on the summary: the next attempt goes through at it.
    $order = placeOrder($customer)->order;
    expect($order->grand_total->toDecimal())->toBe('549.00');
});

test('stock that ran out inside the transaction aborts with nothing persisted', function () {
    $first = cartVariant(['stock' => 5]);
    $second = cartVariant(['stock' => 1]);
    $customer = orderCustomer();
    orderCart($customer, [[$first, 2], $second]);
    // Another buyer took the last unit after this cart was revalidated.
    DB::table('product_variant')->where('id', $second->id)->update(['reserved' => 1]);

    $placing = fn () => app(PlaceOrderService::class)->place($customer, orderCheckout($customer));

    expect($placing)->toThrow(CheckoutException::class);
    expect(SalesOrder::query()->where('customer_id', $customer->id)->exists())->toBeFalse()
        ->and(SellerOrder::query()->whereHas('salesOrder', fn ($q) => $q->where('customer_id', $customer->id))->exists())->toBeFalse()
        ->and(orderVariant($first)->reserved)->toBe(0);
});

test('prepaid orders wait for payment with their seller orders held', function () {
    $variant = cartVariant(['price' => '799']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);

    $result = placeOrder($customer, orderCheckout($customer, CheckoutSession::METHOD_PREPAID));
    $order = $result->order->fresh();

    expect($order->status)->toBe(SalesOrder::STATUS_PENDING_PAYMENT)
        ->and($order->payment_method)->toBe(Payment::METHOD_FAKE)
        ->and(SellerOrder::query()->where('sales_order_id', $order->id)->value('status'))->toBe(SellerOrder::STATUS_PENDING_PAYMENT)
        ->and($result->nextAction())->toBe(PlaceOrderResult::NEXT_PAY)
        ->and($result->nextUrl())->toBe(frontRoute('payment.pay', ['orderNo' => $order->order_no]))
        ->and($result->payment->amount->toDecimal())->toBe('799.00')
        ->and($result->payment->gateway_order_id)->not->toBeNull();
});

test('an unserviceable address cannot be ordered to', function () {
    $variant = cartVariant();
    $customer = orderCustomer(orderPincode(serviceable: false));
    orderCart($customer, [$variant]);

    expect(fn () => placeOrder($customer))->toThrow(CheckoutException::class, trans('order::front.errors.unserviceable'));
});

test('COD is refused when the address does not allow it', function () {
    $variant = cartVariant();
    $customer = orderCustomer(orderPincode(codAllowed: false));
    orderCart($customer, [$variant]);

    try {
        placeOrder($customer);
        $this->fail('COD should have been refused.');
    } catch (CheckoutException $e) {
        expect($e->reasonCode)->toBe('cod_pincode')->and($e->step)->toBe(CheckoutException::STEP_PAYMENT);
    }
});
