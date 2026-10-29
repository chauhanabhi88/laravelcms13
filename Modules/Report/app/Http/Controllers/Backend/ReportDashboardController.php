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
 * Analytics overview (FR-RPT-001/003): the eight KPI tiles with change and sparkline, GMV
 * against the comparison period, seller orders by status, category mix, COD vs prepaid, RTO %
 * by state and the top sellers and products.
 */
class ReportDashboardController extends ReportController
{
    protected const REPORT = 'dashboard';

    public function __construct(ReportTables $tables, ReportCharts $charts, private readonly KpiService $kpis)
    {
        parent::__construct($tables, $charts);
    }

    public function index(Request $request): View
    {
        [$range, $filters] = $this->context($request);
        $summary = $this->kpis->summary($range, $filters);
        $top = 5;

        return $this->render('report::backend.page', $request, $range, $filters, [
            'tiles' => $summary['tiles'],
            'charts' => [
                ['chart' => $this->charts->trend('chart-gmv', 'gmv', Format::MONEY, $summary['daily'], $summary['previousDaily']), 'title' => 'gmv', 'wide' => true],
                ['chart' => $this->charts->statusMix($summary['daily']), 'title' => 'status'],
                ['chart' => $this->charts->categoryMix($range), 'title' => 'categories'],
                ['chart' => $this->charts->paymentTrend($summary['daily']), 'title' => 'payments'],
                ['chart' => $this->charts->rtoByState($range), 'title' => 'rto_states'],
            ],
            'tables' => [
                ['title' => 'top_sellers', 'table' => $this->tables->table('sellers', $range, $filters, $top), 'link' => 'admin.report.sellers'],
                ['title' => 'top_products', 'table' => $this->tables->table('products', $range, $filters, $top), 'link' => 'admin.report.products'],
            ],
        ]);
    }
}
