<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\KpiService;
use Modules\Report\Services\LeaderboardService;
use Modules\Report\Services\ReportCharts;
use Modules\Report\Services\ReportTables;
use Modules\Report\Support\Chart;
use Modules\Report\Support\Format;
use Modules\Report\Support\Table;

/**
 * Fulfilment & RTO report: shipments, deliveries, RTO rate (cohort by ship date),
 * cancellations split by who cancelled, RTO % by state and the pincodes with the most RTO.
 */
class FulfilmentReportController extends ReportController
{
    protected const REPORT = 'fulfilment';

    private const TILES = [
        ['seller_orders', Format::COUNT, true],
        ['shipped_orders', Format::COUNT, true],
        ['delivered_orders', Format::COUNT, true],
        ['rto_orders', Format::COUNT, false],
        ['rto_rate', Format::PERCENT, false],
        ['cancellation_rate', Format::PERCENT, false],
    ];

    public function __construct(
        ReportTables $tables,
        ReportCharts $charts,
        private readonly KpiService $kpis,
        private readonly LeaderboardService $leaderboards,
    ) {
        parent::__construct($tables, $charts);
    }

    public function index(Request $request): View
    {
        [$range, $filters] = $this->context($request);
        $summary = $this->kpis->summary($range, $filters, self::TILES);
        $daily = $summary['daily'];
        $limit = (int) config('report.leaderboard_limit', 20);

        $cancellations = Chart::make('chart-cancellations', 'bar', Format::COUNT, Chart::dayLabels(array_keys($daily)), [
            Chart::series(trans('report::report.columns.cancelled_by_customer'), array_values(array_column($daily, 'cancelled_by_customer')), 0),
            Chart::series(trans('report::report.columns.cancelled_by_seller'), array_values(array_column($daily, 'cancelled_by_seller')), 1),
            Chart::series(trans('report::report.columns.cancelled_by_platform'), array_values(array_column($daily, 'cancelled_by_platform')), 2),
        ], true);

        return $this->render('report::backend.page', $request, $range, $filters, [
            'tiles' => $summary['tiles'],
            'charts' => [
                ['chart' => $this->charts->trend('chart-rto', 'rto_rate', Format::PERCENT, $daily, $summary['previousDaily']), 'title' => 'rto_trend'],
                ['chart' => $cancellations, 'title' => 'cancellations'],
                ['chart' => $this->charts->rtoByState($range), 'title' => 'rto_states', 'wide' => true],
            ],
            'tables' => [
                ['title' => 'daily', 'table' => $this->tables->table('fulfilment', $range, $filters)],
                ['title' => 'rto_pincodes', 'table' => Table::make(Table::PINCODE_COLUMNS, $this->leaderboards->pincodes($range, 'rto', $limit))],
            ],
        ]);
    }
}
