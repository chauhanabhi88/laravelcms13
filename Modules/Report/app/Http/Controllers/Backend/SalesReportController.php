<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\KpiService;
use Modules\Report\Services\ReportCharts;
use Modules\Report\Services\ReportTables;
use Modules\Report\Support\Format;

/**
 * Sales report: GMV, net GMV, orders, units, AOV, discount, net revenue and new customers, the
 * GMV trend, the status mix of the orders placed and the daily table.
 */
class SalesReportController extends ReportController
{
    protected const REPORT = 'sales';

    private const TILES = [
        ['gmv', Format::MONEY, true],
        ['net_gmv', Format::MONEY, true],
        ['orders', Format::COUNT, true],
        ['units', Format::COUNT, true],
        ['aov', Format::MONEY, true],
        ['discount', Format::MONEY, null],
        ['net_revenue', Format::MONEY, true],
        ['new_customers', Format::COUNT, true],
    ];

    public function __construct(ReportTables $tables, ReportCharts $charts, private readonly KpiService $kpis)
    {
        parent::__construct($tables, $charts);
    }

    public function index(Request $request): View
    {
        [$range, $filters] = $this->context($request);
        $summary = $this->kpis->summary($range, $filters, self::TILES);

        return $this->render('report::backend.page', $request, $range, $filters, [
            'tiles' => $summary['tiles'],
            'charts' => [
                ['chart' => $this->charts->trend('chart-gmv', 'gmv', Format::MONEY, $summary['daily'], $summary['previousDaily']), 'title' => 'gmv'],
                ['chart' => $this->charts->trend('chart-orders', 'orders', Format::COUNT, $summary['daily'], $summary['previousDaily']), 'title' => 'orders'],
                ['chart' => $this->charts->statusMix($summary['daily']), 'title' => 'status', 'wide' => true],
            ],
            'tables' => [
                ['title' => 'daily', 'table' => $this->tables->table('sales', $range, $filters)],
            ],
        ]);
    }
}
