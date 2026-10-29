<?php

use Illuminate\Support\Facades\DB;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Services\OrderCancellationService;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Services\PaymentService;
use Modules\Report\Models\ReportExport;
use Modules\Report\Services\KpiService;
use Modules\Report\Services\ProductAggregator;
use Modules\Report\Services\SalesAggregator;
use Modules\Report\Support\Grain;
use Modules\Report\Support\ReportFilters;
use Modules\Report\Support\ReportRange;
use Modules\Returns\Services\ReturnService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reportSetup();
});

function reportSalesAggregator(): SalesAggregator
{
    return app(SalesAggregator::class);
}

/**
 * Line value of an order's items after coupon shares, in rupees.
 */
function reportLineValue(SalesOrder $order): float
{
    return (float) SalesOrderItem::query()->where('sales_order_id', $order->id)->get()
        ->sum(fn (SalesOrderItem $item): float => (float) $item->price->toDecimal() * $item->quantity - (float) $item->discount_allocated->toDecimal());
}

/**
 * A prepaid order of one unit at $price, paid through the fake gateway when $pay.
 */
function reportPrepaidOrder(string $price, bool $pay, $seller = null): SalesOrder
{
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => $price, 'mrp' => $price, 'stock' => 5], $seller)]);
    $result = placeOrder($customer, orderCheckout($customer, CheckoutSession::METHOD_PREPAID));

    if ($pay) {
        $fake = app(FakeGateway::class);
        app(PaymentService::class)->markCaptured($result->payment, $fake->fetch($fake->simulate($result->payment->gateway_order_id)['razorpay_payment_id']), 'webhook');
    }

    return $result->order->refresh();
}

test('a day of orders is aggregated by the metric definitions in every grain', function () {
    $day = reportDay();
    [$root, $leaf] = reportCategories();
    $state = orderState('Kerala');
    $seller = orderSeller();

    // A: COD, 2 × ₹500 in the category, shipped to Kerala, delivered; one unit returned after QC.
    $buyer = orderCustomer(orderPincode($state));
    [$orderA, $sellerOrderA] = deliveredOrder(['price' => '500', 'mrp' => '500'], 2, CheckoutSession::METHOD_COD, $buyer, ['catalog_category_id' => $leaf->id], $seller);
    $itemA = SalesOrderItem::query()->where('seller_order_id', $sellerOrderA->id)->firstOrFail();
    $return = raiseReturn($sellerOrderA, $buyer, ['items' => [$itemA->id => ['quantity' => 1, 'exchange_variant_id' => null]]]);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    app(ReturnService::class)->qcPass($return->refresh());

    // B: prepaid ₹300, paid, then cancelled by the customer (a real cancellation).
    $orderB = reportPrepaidOrder('300', true, $seller);
    app(OrderCancellationService::class)->cancelOrder($orderB, OrderCancellationService::BY_CUSTOMER, 'Changed my mind');

    // C: prepaid, never paid, cancelled by the system: a payment failure, not a sale.
    $orderC = reportPrepaidOrder('900', false, $seller);
    app(OrderCancellationService::class)->cancelOrder($orderC, OrderCancellationService::BY_SYSTEM, 'Payment window expired');

    // D: shipped and returned to origin.
    $orderD = SalesOrder::query()->findOrFail(rtoDeliveredOrder($seller)->sales_order_id);

    foreach ([$orderA, $orderB, $orderC, $orderD] as $order) {
        moveOrderTo($order, $day);
    }

    expect(reportSalesAggregator()->aggregate($day))->toBeGreaterThan(0);

    $date = $day->toDateString();
    $platform = storedStat($date);
    $codOrders = collect([$orderA, $orderB, $orderD])->filter(fn (SalesOrder $order) => $order->payment_method === 'cod')->count();
    $gmv = reportLineValue($orderA) + reportLineValue($orderB) + reportLineValue($orderD);

    expect($platform)->not->toBeNull()
        ->and($platform['orders'])->toBe(3)
        ->and($platform['seller_orders'])->toBe(3)
        ->and((float) $platform['gmv'])->toBe($gmv)
        ->and($platform['cod_orders'])->toBe($codOrders)
        ->and($platform['new_customers'])->toBe(3)
        ->and($platform['cancelled_orders'])->toBe(1)
        ->and($platform['cancelled_by_customer'])->toBe(1)
        ->and((float) $platform['cancelled_gmv'])->toBe(300.0)
        ->and($platform['shipped_orders'])->toBe(2)
        ->and($platform['rto_orders'])->toBe(1)
        ->and($platform['status_rto'])->toBe(1)
        ->and($platform['delivered_orders'])->toBe(1)
        ->and($platform['delivered_units'])->toBe(2)
        ->and($platform['returned_units'])->toBe(1)
        ->and((float) $platform['return_value'])->toBe(500.0);

    // Category (L1 = the root), state, payment and seller grains.
    expect(storedStat($date, categoryId: $root->id))
        ->orders->toBe(1)->units->toBe(2)->gmv->toBe('1000.00')->returned_units->toBe(1)
        ->and(storedStat($date, categoryId: $leaf->id))->toBeNull()
        ->and(storedStat($date, stateId: $state->id))->orders->toBe(1)->delivered_units->toBe(2)
        ->and(storedStat($date, payment: Grain::COD)['orders'])->toBe($codOrders)
        ->and(storedStat($date, payment: Grain::PREPAID)['orders'])->toBe(3 - $codOrders)
        ->and(storedStat($date, sellerId: $seller->id))->orders->toBe(3)->rto_orders->toBe(1)->cancelled_orders->toBe(1);
});

test('re-running a day is idempotent and picks up late changes such as an RTO', function () {
    $day = reportDay();
    $sellerOrder = packedFulfilmentOrder();
    fulfilmentScans($sellerOrder, ['picked_up', 'in_transit']);
    $order = SalesOrder::query()->findOrFail($sellerOrder->sales_order_id);
    moveOrderTo($order, $day);

    $first = reportSalesAggregator()->aggregate($day);
    $snapshot = DB::table('daily_sales_stat')->where('date', $day->toDateString())->orderBy('seller_id')->orderBy('payment_method')->get(['seller_id', 'payment_method', 'orders', 'gmv', 'shipped_orders', 'rto_orders'])->toArray();

    expect(reportSalesAggregator()->aggregate($day))->toBe($first)
        ->and(DB::table('daily_sales_stat')->where('date', $day->toDateString())->orderBy('seller_id')->orderBy('payment_method')->get(['seller_id', 'payment_method', 'orders', 'gmv', 'shipped_orders', 'rto_orders'])->toArray())->toEqual($snapshot)
        ->and(storedStat($day->toDateString()))->shipped_orders->toBe(1)->rto_orders->toBe(0);

    fulfilmentScans($sellerOrder->refresh(), ['rto_initiated']);
    moveOrderTo($order, $day);
    reportSalesAggregator()->aggregate($day);

    expect(storedStat($day->toDateString()))->shipped_orders->toBe(1)->rto_orders->toBe(1);
});

test('net revenue is commission and platform fees less reversals, without GST', function () {
    $day = reportDay();
    [$order, $sellerOrder] = deliveredOrder(['price' => '800', 'mrp' => '800']);
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();
    moveOrderTo($order, $day);

    $entry = fn (string $type, string $direction, string $amount, ?int $itemId) => DB::table('seller_ledger_entry')->insert([
        'seller_id' => $sellerOrder->seller_id, 'seller_order_id' => $sellerOrder->id, 'sales_order_item_id' => $itemId,
        'type' => $type, 'direction' => $direction, 'amount' => $amount, 'tax_amount' => '9.00', 'description' => 'Test',
        'status' => 'pending', 'posted_at' => now(), 'idempotency_key' => uniqid('report-test-'), 'created_at' => now(),
    ]);
    $entry('commission', 'debit', '50.00', $item->id);
    $entry('platform_fee', 'debit', '20.00', null);
    $entry('commission_reversal', 'credit', '10.00', $item->id);
    $entry('logistics_forward', 'debit', '45.00', null);

    reportSalesAggregator()->aggregate($day);

    expect(storedStat($day->toDateString())['net_revenue'])->toBe('60.00')
        ->and(storedStat($day->toDateString(), sellerId: $sellerOrder->seller_id)['net_revenue'])->toBe('60.00');
});

test('the product aggregate counts units, GMV and returns, and page views from snapshots', function () {
    $day = reportDay();
    [$order, $sellerOrder, $variant] = deliveredOrder(['price' => '250', 'mrp' => '250'], 3);
    moveOrderTo($order, $day);
    $productId = $variant->product_id;

    app(ProductAggregator::class)->aggregate($day);
    $row = DB::table('daily_product_stat')->where(['date' => $day->toDateString(), 'product_id' => $productId])->first();

    expect($row)->units->toBe(3)->gmv->toBe('750.00')->returns->toBe(0)->views->toBe(0)->seller_id->toBe($sellerOrder->seller_id);

    // Views: the first snapshot is a baseline, the next day counts the difference.
    DB::table('product')->where('id', $productId)->update(['view_count' => 40]);
    app(ProductAggregator::class)->captureViews($day->toDateString());
    DB::table('product')->where('id', $productId)->update(['view_count' => 55]);
    app(ProductAggregator::class)->aggregate($day->addDay(), true);

    expect(DB::table('daily_product_stat')->where(['date' => $day->toDateString(), 'product_id' => $productId])->first())->view_total->toBe(40)->units->toBe(3)
        ->and(DB::table('daily_product_stat')->where(['date' => $day->addDay()->toDateString(), 'product_id' => $productId])->first())->views->toBe(15)->units->toBe(0);

    // Re-aggregating the sales of a past day keeps its views.
    app(ProductAggregator::class)->aggregate($day->addDay());
    expect(DB::table('daily_product_stat')->where(['date' => $day->addDay()->toDateString(), 'product_id' => $productId])->value('views'))->toBe(15);
});

/*
| Reconciliation (plan M19 §12): for a sample period the KPIs read from the aggregates equal raw
| SQL on the order tables. Raw GMV = Σ(price × qty − discount_allocated) of items of orders placed
| in the period; orders = distinct orders; COD = distinct COD orders.
*/
test('the KPIs of a period reconcile with raw SQL on the order tables', function () {
    $first = reportDay()->subDay();
    $second = reportDay();
    $orders = [
        [deliveredOrder(['price' => '349', 'mrp' => '499'], 2)[0], $first],
        [deliveredOrder(['price' => '1299', 'mrp' => '1299'], 1, CheckoutSession::METHOD_PREPAID)[0], $first],
        [deliveredOrder(['price' => '99', 'mrp' => '199'], 5)[0], $second],
    ];

    foreach ($orders as [$order, $day]) {
        moveOrderTo($order, $day);
    }

    $this->artisan('report:rebuild', ['--from' => $first->toDateString(), '--to' => $second->toDateString()])->assertSuccessful();

    $raw = DB::table('sales_order_item as i')->join('sales_order as o', 'o.id', '=', 'i.sales_order_id')
        ->whereBetween('o.placed_at', [$first->startOfDay(), $second->endOfDay()])
        ->selectRaw("SUM(i.price * i.quantity - i.discount_allocated) as gmv, COUNT(DISTINCT o.id) as orders, COUNT(DISTINCT CASE WHEN o.payment_method = 'cod' THEN o.id END) as cod, SUM(i.quantity) as units")
        ->first();

    $tiles = array_column(app(KpiService::class)->summary(ReportRange::between($first, $second), ReportFilters::none(), [
        ['gmv', 'money', true], ['orders', 'count', true], ['cod_orders', 'count', null], ['units', 'count', true],
    ])['tiles'], 'value', 'key');

    expect($tiles['gmv']->toDecimal())->toBe(number_format((float) $raw->gmv, 2, '.', ''))
        ->and($tiles['orders'])->toBe((int) $raw->orders)
        ->and($tiles['cod_orders'])->toBe((int) $raw->cod)
        ->and($tiles['units'])->toBe((int) $raw->units);
});

test('report:rebuild and the nightly command aggregate past days; nightly prunes old exports', function () {
    $day = reportDay();
    [$order] = deliveredOrder(['price' => '450', 'mrp' => '450']);
    moveOrderTo($order, $day);

    $this->artisan('report:rebuild', ['--from' => $day->toDateString(), '--to' => $day->toDateString()])->assertSuccessful();
    expect(storedStat($day->toDateString()))->orders->toBe(1)->gmv->toBe('450.00');

    DB::table('daily_sales_stat')->where('date', $day->toDateString())->delete();
    $old = ReportExport::query()->create(['user_id' => 1, 'report' => 'sales', 'format' => 'csv', 'filters' => [], 'status' => ReportExport::STATUS_READY, 'path' => 'report-exports/old.csv']);
    DB::table('report_export')->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);

    $this->artisan('report:aggregate-nightly')->assertSuccessful();

    expect(storedStat($day->toDateString()))->orders->toBe(1)
        ->and(ReportExport::query()->whereKey($old->id)->exists())->toBeFalse();

    $this->artisan('report:rebuild', ['--from' => 'not-a-date'])->assertFailed();
});
