<?php

use Carbon\CarbonImmutable;
use Modules\Core\Support\Money;
use Modules\Report\Services\KpiService;
use Modules\Report\Support\Format;
use Modules\Report\Support\Grain;
use Modules\Report\Support\ReportFilters;
use Modules\Report\Support\ReportRange;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reportSetup();
});

/**
 * @return array<string, array<string, mixed>> tiles by metric
 */
function reportKpiTiles(ReportRange $range, ?ReportFilters $filters = null): array
{
    return array_column(app(KpiService::class)->summary($range, $filters ?? ReportFilters::none())['tiles'], null, 'key');
}

test('KPIs sum the stored days and compare with the previous period', function () {
    $today = CarbonImmutable::today();
    $range = ReportRange::between($today->subDays(7), $today->subDays(1));

    // This period: 2 days. Previous period (the 7 days before): 1 day.
    statRow($today->subDays(2)->toDateString(), ['orders' => 6, 'seller_orders' => 8, 'gmv' => '3000.00', 'cod_orders' => 3, 'shipped_orders' => 10, 'rto_orders' => 2, 'delivered_units' => 10, 'returned_units' => 1, 'cancelled_orders' => 2, 'net_revenue' => '120.00']);
    statRow($today->subDays(5)->toDateString(), ['orders' => 4, 'seller_orders' => 4, 'gmv' => '2000.00', 'cod_orders' => 1, 'shipped_orders' => 10, 'rto_orders' => 1, 'delivered_units' => 10, 'returned_units' => 1]);
    statRow($today->subDays(10)->toDateString(), ['orders' => 5, 'seller_orders' => 5, 'gmv' => '4000.00', 'cod_orders' => 5, 'shipped_orders' => 10, 'rto_orders' => 1, 'delivered_units' => 5, 'returned_units' => 0]);
    // Other grains and days outside both periods do not count.
    statRow($today->subDays(2)->toDateString(), ['orders' => 99, 'gmv' => '9999.00'], payment: Grain::COD);
    statRow($today->subDays(30)->toDateString(), ['orders' => 99, 'gmv' => '9999.00']);

    $tiles = reportKpiTiles($range);

    expect($tiles['gmv']['value'])->toEqual(Money::of('5000'))
        ->and($tiles['gmv']['delta'])->toBe(25.0)
        ->and($tiles['gmv']['trend'])->toBe('good')
        ->and($tiles['orders']['value'])->toBe(10)
        ->and($tiles['aov']['display'])->toBe(Money::of('500')->format())
        ->and($tiles['aov']['delta'])->toBe(-37.5)
        ->and($tiles['net_revenue']['value'])->toEqual(Money::of('120'))
        ->and($tiles['rto_rate']['value'])->toBe(15.0)
        ->and($tiles['rto_rate']['delta'])->toBe(5.0)
        ->and($tiles['rto_rate']['delta_display'])->toBe('+5 pp')
        ->and($tiles['rto_rate']['trend'])->toBe('bad')
        ->and($tiles['return_rate']['value'])->toBe(10.0)
        ->and($tiles['cancellation_rate']['value'])->toBe(16.7)
        ->and($tiles['cod_share']['value'])->toBe(40.0)
        ->and($tiles['cod_share']['trend'])->toBe('neutral')
        ->and($tiles['gmv']['sparkline'])->toHaveCount(7)
        ->and($tiles['gmv']['sparkline'][5])->toBe(3000.0);

    expect(reportKpiTiles(ReportRange::between($today->subDays(7), $today->subDays(1), ReportRange::COMPARE_NONE))['gmv']['delta'])->toBeNull();
});

test('a filter reads its own grain', function () {
    $day = CarbonImmutable::today()->subDays(2)->toDateString();
    $range = ReportRange::between($day, $day);

    statRow($day, ['orders' => 10, 'gmv' => '1000.00']);
    statRow($day, ['orders' => 4, 'gmv' => '400.00'], payment: Grain::COD);
    statRow($day, ['orders' => 3, 'gmv' => '900.00'], categoryId: 77);
    statRow($day, ['orders' => 2, 'gmv' => '250.00'], sellerId: 55);

    expect(reportKpiTiles($range, ReportFilters::fromInput(['payment' => 'cod']))['orders']['value'])->toBe(4)
        ->and(reportKpiTiles($range, ReportFilters::fromInput(['category_id' => 77]))['gmv']['value'])->toEqual(Money::of('900'))
        ->and(reportKpiTiles($range, ReportFilters::forSeller(55))['orders']['value'])->toBe(2);
});

test('today is computed live and its stored hourly row is not counted twice', function () {
    $today = CarbonImmutable::today();
    $yesterday = $today->subDay()->toDateString();

    statRow($yesterday, ['orders' => 2, 'seller_orders' => 2, 'gmv' => '1000.00']);
    statRow($today->toDateString(), ['orders' => 50, 'seller_orders' => 50, 'gmv' => '50000.00']);

    deliveredOrder(['price' => '700', 'mrp' => '700']);

    $tiles = reportKpiTiles(ReportRange::between($yesterday, $today));

    expect($tiles['orders']['value'])->toBe(3)
        ->and($tiles['gmv']['value'])->toEqual(Money::of('1700'))
        ->and(reportKpiTiles(ReportRange::fromInput(['preset' => 'today']))['orders']['value'])->toBe(1);
});

test('ranges come from presets or valid custom dates; anything else falls back with a warning', function () {
    $today = CarbonImmutable::today();

    $last7 = ReportRange::fromInput(['preset' => 'last_7']);
    expect($last7->from->toDateString())->toBe($today->subDays(6)->toDateString())
        ->and($last7->days())->toBe(7)
        ->and($last7->includesToday())->toBeTrue()
        ->and($last7->compare()->to->toDateString())->toBe($today->subDays(7)->toDateString())
        ->and($last7->compare()->days())->toBe(7);

    $custom = ReportRange::fromInput(['from' => '2026-01-01', 'to' => '2026-01-10', 'compare' => 'last_year']);
    expect($custom->preset)->toBe('custom')
        ->and($custom->compare()->from->toDateString())->toBe('2025-01-01')
        ->and($custom->toQuery())->toBe(['preset' => 'custom', 'compare' => 'last_year', 'from' => '2026-01-01', 'to' => '2026-01-10']);

    expect(ReportRange::fromInput(['preset' => 'custom', 'from' => '2026-02-30', 'to' => '2026-03-01']))
        ->preset->toBe('last_30')->warnings->toBe(['invalid_range'])
        ->and(ReportRange::fromInput(['preset' => 'custom', 'from' => '2026-03-10', 'to' => '2026-03-01'])->warnings)->toBe(['invalid_range'])
        ->and(ReportRange::fromInput(['preset' => 'everything'])->warnings)->toBe(['invalid_range'])
        ->and(ReportRange::fromInput(['preset' => 'custom', 'from' => '2020-01-01', 'to' => '2026-01-01']))->warnings->toBe(['range_clamped'])
        ->and(ReportRange::fromInput(['preset' => 'custom', 'from' => '2020-01-01', 'to' => '2026-01-01'])->days())->toBe((int) config('report.max_range_days'));

    $filters = ReportFilters::fromInput(['category_id' => '4', 'state_id' => '9']);
    expect($filters->categoryId)->toBe(4)->and($filters->stateId)->toBeNull()->and($filters->warnings)->toBe(['one_filter'])
        ->and(ReportFilters::fromInput(['payment' => 'upi'])->isEmpty())->toBeTrue();

    expect(Format::delta(-2.5, Format::MONEY))->toBe('−2.5%')
        ->and(Format::display(null, Format::PERCENT))->toBe('—')
        ->and(Format::display(12.0, Format::PERCENT))->toBe('12%');
});
