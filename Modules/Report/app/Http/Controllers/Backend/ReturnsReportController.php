<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\KpiService;
use Modules\Report\Services\LeaderboardService;
use Modules\Report\Services\LiveInsights;
use Modules\Report\Services\ReportCharts;
use Modules\Report\Services\ReportTables;
use Modules\Report\Support\Format;
use Modules\Report\Support\Table;

/**
 * Returns report: return rate (returned / delivered units, cohort by delivery date), returned
 * value, reasons of the returns raised in the period and the return rate per category.
 */
class ReturnsReportController extends ReportController
{
    protected const REPORT = 'returns';

    private const TILES = [
        ['delivered_units', Format::COUNT, true],
        ['returned_units', Format::COUNT, false],
        ['return_rate', Format::PERCENT, false],
        ['return_value', Format::MONEY, false],
        ['net_gmv', Format::MONEY, true],
    ];

    public function __construct(
        ReportTables $tables,
        ReportCharts $charts,
        private readonly KpiService $kpis,
        private readonly LeaderboardService $leaderboards,
        private readonly LiveInsights $insights,
    ) {
        parent::__construct($tables, $charts);
    }

    public function index(Request $request): View
    {
        [$range, $filters] = $this->context($request);
        $summary = $this->kpis->summary($range, $filters, self::TILES);
        $reasons = $this->insights->returnReasons($range, $filters->sellerId);

        $reasonTable = Table::make([['label', Format::TEXT], ['requests', Format::COUNT], ['units', Format::COUNT], ['share', Format::PERCENT]], $reasons);
        $categoryTable = Table::make(
            [['name', Format::TEXT], ['units', Format::COUNT], ['return_rate', Format::PERCENT], ['gmv', Format::MONEY]],
            $this->leaderboards->categories($range),
        );

        return $this->render('report::backend.page', $request, $range, $filters, [
            'tiles' => $summary['tiles'],
            'notice' => $filters->dimension() !== null && $filters->sellerId === null ? 'reasons_unfiltered' : null,
            'charts' => [
                ['chart' => $this->charts->trend('chart-return-rate', 'return_rate', Format::PERCENT, $summary['daily'], $summary['previousDaily']), 'title' => 'return_trend'],
                ['chart' => $this->charts->ranked('chart-reasons', $reasons, 'label', 'requests', Format::COUNT, trans('report::report.columns.requests'), 1), 'title' => 'reasons'],
            ],
            'tables' => [
                ['title' => 'daily', 'table' => $this->tables->table('returns', $range, $filters)],
                ['title' => 'reasons', 'table' => $reasonTable],
                ['title' => 'category_returns', 'table' => $categoryTable],
            ],
        ]);
    }
}
