<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Exceptions\PriceChanged;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\CheckoutService;
use Modules\Order\Services\OrderCancellationService;
use Modules\Product\Services\PricingService;
use Modules\Promotion\Exceptions\CouponException;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\CouponUsage;
use Modules\Promotion\Services\PromotionOrderHooks;
use Modules\Returns\Services\ReturnRefundService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    promotionSetup();
});

test('FIRST50 on a first order is stored on the order and spread over its items', function () {
    $customer = orderCustomer();
    $a = cartVariant(['price' => '300', 'mrp' => '600']);
    $b = cartVariant(['price' => '100', 'mrp' => '200']);
    Coupon::factory()->firstOrderOnly()->create(['code' => 'FIRST50', 'value' => '50', 'min_cart_value' => '199', 'usage_limit_per_customer' => null]);

    $order = placeWithCoupon($customer, [$a, $b], 'FIRST50')->order->fresh();
    $items = SalesOrderItem::query()->where('sales_order_id', $order->id)->get()->keyBy('variant_id');

    expect($order->coupon_code)->toBe('FIRST50')
        ->and($order->coupon_discount->toDecimal())->toBe('50.00')
        ->and($order->grand_total->toDecimal())->toBe('350.00')
        ->and($items[$a->id]->discount_allocated->toDecimal())->toBe('37.50')
        ->and($items[$b->id]->discount_allocated->toDecimal())->toBe('12.50')
        ->and($items[$a->id]->discount_funded_by)->toBe(SalesOrderItem::FUNDED_BY_PLATFORM)
        ->and($items[$a->id]->seller_discount->toDecimal())->toBe('0.00');

    $usage = CouponUsage::query()->where('sales_order_id', $order->id)->sole();

    expect($usage->status)->toBe(CouponUsage::STATUS_APPLIED)
        ->and($usage->customer_id)->toBe($customer->id)
        ->and($usage->discount_amount->toDecimal())->toBe('50.00')
        ->and(Coupon::query()->where('code', 'FIRST50')->value('used_count'))->toBe(1);

    // The second order is no longer a first order.
    orderCart($customer, [$a]);
    expect(fn () => applyCoupon($customer, 'FIRST50'))->toThrow(CouponException::class, 'FIRST50 is for your first order only.');
});

test('GST is charged on the price after the coupon share', function () {
    $customer = orderCustomer();
    $variant = cartVariant(['price' => '1050', 'mrp' => '1050'], null, ['gst_percent' => '5.00', 'hsn_code' => '6204']);
    promotionCoupon(['code' => 'TAX50', 'value' => '50']);

    $order = placeWithCoupon($customer, [$variant], 'TAX50')->order;
    $item = SalesOrderItem::query()->where('sales_order_id', $order->id)->sole();
    $expected = app(PricingService::class)->taxBreakup(Money::of('1000'), '5', $item->igst->isPositive());

    expect($item->discount_allocated->toDecimal())->toBe('50.00')
        ->and($item->taxable_value->toDecimal())->toBe($expected['taxable']->toDecimal())
        ->and($item->cgst->plus($item->sgst)->plus($item->igst)->toDecimal())->toBe($expected['tax']->toDecimal());
});

test('a seller-scoped coupon discounts only that seller\'s items and records who funds it', function () {
    $customer = orderCustomer();
    $seller = orderSeller();
    $mine = cartVariant(['price' => '400', 'mrp' => '400'], $seller);
    $other = cartVariant(['price' => '600', 'mrp' => '600']);
    Coupon::factory()->forSeller($seller)->percent('10')->create(['code' => 'SHOP10']);

    $order = placeWithCoupon($customer, [$mine, $other], 'SHOP10')->order;
    $items = SalesOrderItem::query()->where('sales_order_id', $order->id)->get()->keyBy('variant_id');

    expect($items[$mine->id]->discount_allocated->toDecimal())->toBe('40.00')
        ->and($items[$mine->id]->discount_funded_by)->toBe(SalesOrderItem::FUNDED_BY_SELLER)
        ->and($items[$mine->id]->seller_discount->toDecimal())->toBe('40.00')
        ->and($items[$other->id]->discount_allocated->toDecimal())->toBe('0.00')
        ->and($items[$other->id]->discount_funded_by)->toBeNull();
});

test('a free-delivery coupon is recorded with the delivery charge it waived', function () {
    promotionPaidDelivery('40', '999');
    $customer = orderCustomer();
    Coupon::factory()->freeDelivery()->create(['code' => 'SHIPFREE']);

    $order = placeWithCoupon($customer, [cartVariant(['price' => '300', 'mrp' => '300'])], 'SHIPFREE')->order->fresh();

    expect($order->coupon_code)->toBe('SHIPFREE')
        ->and($order->coupon_discount->toDecimal())->toBe('0.00')
        ->and($order->delivery_charge->toDecimal())->toBe('0.00')
        ->and($order->grand_total->toDecimal())->toBe('300.00')
        ->and(CouponUsage::query()->where('sales_order_id', $order->id)->value('discount_amount'))->toEqual(Money::of('40'));
});

test('a coupon used up between the summary and Place Order is refused under the lock', function () {
    promotionCoupon(['code' => 'LASTONE', 'value' => '30', 'usage_limit_total' => 1]);
    $late = orderCustomer();
    orderCart($late, [cartVariant()]);
    applyCoupon($late, 'LASTONE');
    $staleSummary = app(CheckoutService::class)->summary($late, orderCheckout($late));

    placeWithCoupon(orderCustomer(), [cartVariant()], 'LASTONE');

    // The hook itself refuses the stale summary (the race inside place-order)…
    $lateOrder = placeWithCoupon(orderCustomer(), [cartVariant()], null)->order;
    expect(fn () => DB::transaction(fn () => app(PromotionOrderHooks::class)->onPlacing($lateOrder, $staleSummary)))
        ->toThrow(CheckoutException::class, 'LASTONE has been fully redeemed.');

    // …and placing with the total the buyer saw is refused as a price change.
    expect(fn () => placeOrder($late, $staleSummary->session, $staleSummary->grandTotal()))->toThrow(PriceChanged::class)
        ->and(CouponUsage::query()->whereHas('coupon', fn ($q) => $q->where('code', 'LASTONE'))->count())->toBe(1);
});

test('cancelling the whole order reverses its coupon usage and frees the coupon', function () {
    $customer = orderCustomer();
    promotionCoupon(['code' => 'COMEBACK', 'value' => '25', 'usage_limit_per_customer' => 1]);
    $order = placeWithCoupon($customer, [cartVariant()], 'COMEBACK')->order;

    app(OrderCancellationService::class)->cancelOrder($order->fresh(), OrderCancellationService::BY_CUSTOMER, 'Changed my mind');

    $usage = CouponUsage::query()->where('sales_order_id', $order->id)->sole();

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED)
        ->and($usage->status)->toBe(CouponUsage::STATUS_REVERSED)
        ->and($usage->reversed_at)->not->toBeNull()
        ->and(Coupon::query()->where('code', 'COMEBACK')->value('used_count'))->toBe(0);

    orderCart($customer, [cartVariant()]);
    expect(applyCoupon($customer, 'COMEBACK')->valid)->toBeTrue();
});

test('cancelling part of an order keeps the coupon usage', function () {
    $customer = orderCustomer();
    promotionCoupon(['code' => 'PARTIAL', 'value' => '20']);
    $order = placeWithCoupon($customer, [cartVariant(), cartVariant()], 'PARTIAL')->order;
    $sellerOrder = SellerOrder::query()->where('sales_order_id', $order->id)->orderBy('id')->firstOrFail();

    app(OrderCancellationService::class)->cancelItems($sellerOrder, null, OrderCancellationService::BY_CUSTOMER, 'One package only');

    expect($order->fresh()->status)->not->toBe(SalesOrder::STATUS_CANCELLED)
        ->and(CouponUsage::query()->where('sales_order_id', $order->id)->value('status'))->toBe(CouponUsage::STATUS_APPLIED);
});

test('refunds give back the item\'s paid value (price × qty − its coupon share)', function () {
    $customer = orderCustomer();
    $variant = cartVariant(['price' => '200', 'mrp' => '200']);
    promotionCoupon(['code' => 'REFUND30', 'value' => '30']);
    $order = placeWithCoupon($customer, [[$variant, 3]], 'REFUND30')->order;
    $item = SalesOrderItem::query()->where('sales_order_id', $order->id)->sole();

    [$unit, $line] = app(ReturnRefundService::class)->itemRefund($order->fresh(), $item, 1);

    expect($item->discount_allocated->toDecimal())->toBe('30.00')
        ->and($line->toDecimal())->toBe('190.00')
        ->and($unit->toDecimal())->toBe('190.00');
});

test('a prepaid-only coupon stops applying when checkout switches to COD', function () {
    $customer = orderCustomer();
    promotionCoupon(['code' => 'PAYONLINE', 'value' => '40', 'payment_methods' => ['prepaid']]);
    orderCart($customer, [cartVariant(['price' => '400', 'mrp' => '400'])]);
    applyCoupon($customer, 'PAYONLINE');

    $prepaid = app(CheckoutService::class)->summary($customer, orderCheckout($customer, CheckoutSession::METHOD_PREPAID));
    $cod = app(CheckoutService::class)->summary($customer, orderCheckout($customer, CheckoutSession::METHOD_COD));

    expect($prepaid->grandTotal()->toDecimal())->toBe('360.00')
        ->and($cod->grandTotal()->toDecimal())->toBe('400.00');
});
