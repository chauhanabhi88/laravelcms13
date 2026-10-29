<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\KpiService;
use Modules\Report\Services\ReportCharts;
use Modules\Report\Services\ReportQuery;
use Modules\Report\Services\ReportTables;
use Modules\Report\Support\Format;
use Modules\Report\Support\Grain;
use Modules\Report\Support\Metrics;
use Modules\Report\Support\Table;

/**
 * Payment mix: COD share, COD vs prepaid orders per day, and how the two compare on AOV,
 * cancellations, RTO and returns.
 */
class PaymentMixReportController extends ReportController
{
    protected const REPORT = 'payments';

    private const TILES = [
        ['cod_share', Format::PERCENT, null],
        ['cod_orders', Format::COUNT, null],
        ['prepaid_orders', Format::COUNT, true],
        ['gmv', Format::MONEY, true],
        ['aov', Format::MONEY, true],
    ];

    public function __construct(
        ReportTables $tables,
        ReportCharts $charts,
        private readonly KpiService $kpis,
        private readonly ReportQuery $query,
    ) {
        parent::__construct($tables, $charts);
    }

    public function index(Request $request): View
    {
        [$range, $filters] = $this->context($request);
        $summary = $this->kpis->summary($range, $filters, self::TILES);
        $members = $this->query->byDimension($range, Grain::DIMENSION_PAYMENT);

        $rows = [];
        foreach (Grain::PAYMENTS as $payment) {
            $metrics = Metrics::derive($members[$payment] ?? Metrics::zero());
            $rows[] = ['payment' => trans('report::report.payments.'.$payment), ...$metrics];
        }

        return $this->render('report::backend.page', $request, $range, $filters, [
            'tiles' => $summary['tiles'],
            'charts' => [
                ['chart' => $this->charts->paymentTrend($summary['daily']), 'title' => 'payments'],
                ['chart' => $this->charts->trend('chart-cod-share', 'cod_share', Format::PERCENT, $summary['daily'], $summary['previousDaily']), 'title' => 'cod_share'],
            ],
            'tables' => [
                ['title' => 'payment_compare', 'table' => Table::make([
                    ['payment', Format::TEXT], ['orders', Format::COUNT], ['gmv', Format::MONEY], ['aov', Format::MONEY],
                    ['cancellation_rate', Format::PERCENT], ['rto_rate', Format::PERCENT], ['return_rate', Format::PERCENT],
                ], $rows)],
                ['title' => 'daily', 'table' => $this->tables->table('payments', $range, $filters)],
            ],
        ]);
    }
}
