<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Report\Services\ReportTables;
use Modules\Report\Support\Grain;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Seller/tests/Helpers.php';

beforeEach(function () {
    reportSetup();
});

test('every report page renders with data for an analyst', function () {
    $admin = reportAdmin();
    $day = CarbonImmutable::today()->subDays(2)->toDateString();
    $seller = orderSeller();
    statRow($day, ['orders' => 4, 'seller_orders' => 4, 'units' => 6, 'gmv' => '2400.00', 'cod_orders' => 3, 'shipped_orders' => 4, 'rto_orders' => 1, 'delivered_orders' => 2, 'delivered_units' => 3, 'returned_units' => 1, 'status_delivered' => 2, 'status_open' => 2]);
    statRow($day, ['orders' => 4, 'seller_orders' => 4, 'gmv' => '2400.00', 'shipped_orders' => 4, 'rto_orders' => 1], sellerId: $seller->id);
    statRow($day, ['orders' => 3, 'gmv' => '1800.00'], payment: Grain::COD);
    deliveredOrder(['price' => '350', 'mrp' => '350']);

    foreach (ReportTables::REPORTS as $report) {
        $this->actingAs($admin)->get(route('admin.report.'.$report))
            ->assertOk()
            ->assertSee(trans('report::report.reports.'.$report))
            ->assertSee('data-report-filters', false);
    }

    $this->actingAs($admin)->get(route('admin.report.dashboard', ['preset' => 'last_7']))
        ->assertSee('data-kpi="gmv"', false)
        ->assertSee('data-report-chart', false)
        ->assertSee(trans('report::report.charts.show_table'));

    $this->actingAs($admin)->get(route('admin.report.sellers', ['preset' => 'last_7', 'sort' => 'orders']))->assertOk()->assertSee('aria-current="true"', false);
    $this->actingAs($admin)->get(route('admin.report.export'))->assertOk()->assertSee(trans('report::report.exports.title'));
});

test('invalid filters fall back with a warning instead of failing', function () {
    $this->actingAs(reportAdmin())->get(route('admin.report.sales', ['preset' => 'custom', 'from' => 'yesterday', 'to' => 'x']))
        ->assertOk()->assertSee('data-report-warning="invalid_range"', false);

    $this->actingAs(reportAdmin())->get(route('admin.report.sales', ['category_id' => 3, 'state_id' => 4]))
        ->assertOk()->assertSee('data-report-warning="one_filter"', false);
});

test('each report needs its own permission and tabs show only the allowed ones', function () {
    $finance = reportAdmin(['admin.report.payments']);

    $this->actingAs($finance)->get(route('admin.report.payments'))
        ->assertOk()
        ->assertDontSee(route('admin.report.fulfilment'))
        ->assertDontSee('data-report-export', false);

    foreach (['dashboard', 'sales', 'fulfilment', 'returns', 'sellers', 'products', 'geography', 'search'] as $report) {
        $this->actingAs($finance)->get(route('admin.report.'.$report))->assertForbidden();
    }

    $this->actingAs($finance)->get(route('admin.report.export'))->assertForbidden();
    $this->actingAs(reportAdmin(['admin.report.payments', 'admin.report.export']))->get(route('admin.report.export_start', ['report' => 'sales']))->assertForbidden();
});

test('the dashboard home shows the marketplace KPIs only to admins with the overview permission', function () {
    $this->actingAs(reportAdmin(['admin.dashboard.index', 'admin.report.dashboard']))->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertSee('data-report-widget', false)
        ->assertSee(trans('report::report.widget.title'));

    $this->actingAs(reportAdmin(['admin.dashboard.index']))->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertDontSee('data-report-widget', false);
});

test('a seller sees only their own analytics', function () {
    [$seller, $owner] = sellerWithOwner();
    $other = orderSeller();
    $day = CarbonImmutable::today()->subDays(2)->toDateString();
    statRow($day, ['orders' => 5, 'seller_orders' => 5, 'units' => 7, 'gmv' => '3500.00'], sellerId: $seller->id);
    statRow($day, ['orders' => 80, 'seller_orders' => 80, 'gmv' => '99999.00'], sellerId: $other->id);
    $mine = cartVariant(['price' => '500'], $seller);
    $theirs = cartVariant(['price' => '500'], $other);
    DB::table('daily_product_stat')->insert([
        ['date' => $day, 'product_id' => $mine->product_id, 'seller_id' => $seller->id, 'units' => 7, 'gmv' => '3500.00', 'views' => 10, 'returns' => 0],
        ['date' => $day, 'product_id' => $theirs->product_id, 'seller_id' => $other->id, 'units' => 80, 'gmv' => '40000.00', 'views' => 10, 'returns' => 0],
    ]);

    $this->actingAs($owner, 'seller')->get(frontRoute('seller.analytics.index', [['preset' => 'last_7']]))
        ->assertOk()
        ->assertSee(Money::of('3500')->format())
        ->assertDontSee(Money::of('99999')->format())
        ->assertSee('data-report-product="'.$mine->product_id.'"', false)
        ->assertDontSee('data-report-product="'.$theirs->product_id.'"', false);
});
