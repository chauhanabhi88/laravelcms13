<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Order\Models\SalesOrder;
use Modules\Report\Services\LeaderboardService;
use Modules\Report\Support\ReportRange;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reportSetup();
});

function reportLeaderboards(): LeaderboardService
{
    return app(LeaderboardService::class);
}

function reportPastRange(): ReportRange
{
    $today = CarbonImmutable::today();

    return ReportRange::between($today->subDays(7), $today->subDay());
}

test('sellers rank by GMV, orders or rating with their quality rates', function () {
    $day = CarbonImmutable::today()->subDays(2)->toDateString();
    $big = orderSeller();
    $busy = orderSeller();
    $rated = orderSeller();
    DB::table('seller')->where('id', $big->id)->update(['store_name' => 'Big Basket Co', 'rating_avg' => 3.9]);
    DB::table('seller')->where('id', $rated->id)->update(['rating_avg' => 4.8]);

    statRow($day, ['orders' => 2, 'seller_orders' => 2, 'gmv' => '9000.00', 'shipped_orders' => 4, 'rto_orders' => 1, 'delivered_units' => 4, 'returned_units' => 2], sellerId: $big->id);
    statRow($day, ['orders' => 9, 'seller_orders' => 9, 'gmv' => '3000.00'], sellerId: $busy->id);
    statRow($day, ['orders' => 1, 'seller_orders' => 1, 'gmv' => '500.00'], sellerId: $rated->id);
    statRow($day, ['orders' => 12, 'gmv' => '12500.00']);

    $byGmv = reportLeaderboards()->sellers(reportPastRange());
    expect(array_column($byGmv, 'seller_id'))->toBe([$big->id, $busy->id, $rated->id])
        ->and($byGmv[0])->name->toBe('Big Basket Co')->rto_rate->toBe(25.0)->return_rate->toBe(50.0)->rating->toBe(3.9)
        ->and($byGmv[0]['aov'])->toEqual(Money::of('4500'))
        ->and(array_column(reportLeaderboards()->sellers(reportPastRange(), 'orders'), 'seller_id'))->toBe([$busy->id, $big->id, $rated->id])
        ->and(array_column(reportLeaderboards()->sellers(reportPastRange(), 'rating', 2), 'seller_id'))->toBe([$rated->id, $big->id]);
});

test('categories carry their share of GMV and states rank by GMV or RTO', function () {
    $day = CarbonImmutable::today()->subDays(2)->toDateString();
    [$fashion] = reportCategories();
    [$home] = reportCategories();
    $north = orderState('Punjab');
    $south = orderState('Kerala');

    statRow($day, ['orders' => 3, 'gmv' => '750.00'], categoryId: $fashion->id);
    statRow($day, ['orders' => 1, 'gmv' => '250.00'], categoryId: $home->id);
    statRow($day, ['orders' => 3, 'gmv' => '600.00'], payment: 'cod');
    statRow($day, ['orders' => 1, 'gmv' => '400.00'], payment: 'prepaid');
    statRow($day, ['orders' => 3, 'gmv' => '800.00', 'shipped_orders' => 10, 'rto_orders' => 1], stateId: $north->id);
    statRow($day, ['orders' => 1, 'gmv' => '200.00', 'shipped_orders' => 4, 'rto_orders' => 2], stateId: $south->id);

    $categories = reportLeaderboards()->categories(reportPastRange());
    expect(array_column($categories, 'category_id'))->toBe([$fashion->id, $home->id])
        ->and($categories[0]['share'])->toBe(75.0)
        ->and($categories[0]['name'])->toBe(DB::table('catalog_category_translation')->where('catalog_category_id', $fashion->id)->value('name'));

    expect(array_column(reportLeaderboards()->states(reportPastRange()), 'name'))->toBe(['Punjab', 'Kerala'])
        ->and(array_column(reportLeaderboards()->states(reportPastRange(), 'rto'), 'name'))->toBe(['Kerala', 'Punjab']);

    config(['report.rto_rank_min_shipped' => 5]);
    expect(array_column(reportLeaderboards()->states(reportPastRange(), 'rto'), 'name'))->toBe(['Punjab']);
});

test('products rank by units or GMV with names, sellers, views and return rate', function () {
    $day = CarbonImmutable::today()->subDays(2)->toDateString();
    $seller = orderSeller();
    $slow = cartVariant(['price' => '900'], $seller);
    $fast = cartVariant(['price' => '100'], $seller);

    DB::table('daily_product_stat')->insert([
        ['date' => $day, 'product_id' => $slow->product_id, 'seller_id' => $seller->id, 'units' => 2, 'gmv' => '1800.00', 'views' => 40, 'returns' => 1],
        ['date' => $day, 'product_id' => $fast->product_id, 'seller_id' => $seller->id, 'units' => 10, 'gmv' => '1000.00', 'views' => 90, 'returns' => 0],
    ]);

    $byUnits = reportLeaderboards()->products(reportPastRange());
    expect(array_column($byUnits, 'product_id'))->toBe([$fast->product_id, $slow->product_id])
        ->and($byUnits[1])->views->toBe(40)->return_rate->toBe(50.0)
        ->and($byUnits[1]['name'])->toBe(DB::table('product_translation')->where('product_id', $slow->product_id)->value('name'))
        ->and(array_column(reportLeaderboards()->products(reportPastRange(), 'gmv'), 'product_id'))->toBe([$slow->product_id, $fast->product_id])
        ->and(reportLeaderboards()->products(reportPastRange(), 'units', null, orderSeller()->id))->toBe([]);
});

test('pincodes are ranked live from the orders by GMV and RTO', function () {
    $day = reportDay();
    $kochi = orderPincode(orderState('Kerala'));
    [$delivered] = deliveredOrder(['price' => '1200', 'mrp' => '1200'], 1, 'cod', orderCustomer($kochi));
    $rto = rtoDeliveredOrder();
    moveOrderTo($delivered, $day);
    moveOrderTo(SalesOrder::query()->findOrFail($rto->sales_order_id), $day);
    $range = ReportRange::between($day, $day);

    $byGmv = reportLeaderboards()->pincodes($range);
    expect($byGmv[0])->pincode->toBe($kochi->pincode)->orders->toBe(1)->shipped->toBe(1)->rto->toBe(0)
        ->and($byGmv[0]['gmv'])->toEqual(Money::of('1200'));

    $byRto = reportLeaderboards()->pincodes($range, 'rto');
    expect($byRto)->toHaveCount(1)
        ->and($byRto[0])->rto->toBe(1)->rto_rate->toBe(100.0)
        ->and($byRto[0]['pincode'])->not->toBe($kochi->pincode);
});
