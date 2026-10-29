<?php

declare(strict_types=1);

namespace Modules\Report\Support;

use Modules\Dashboard\Contracts\DashboardWidget;
use Modules\Report\Services\KpiService;
use Modules\Report\Services\ReportCharts;

/**
 * Marketplace KPIs of the last 30 days (vs the 30 before) and the GMV trend on the backend
 * dashboard home, for admins allowed to see the analytics overview.
 */
class ReportDashboardWidget implements DashboardWidget
{
    public function __construct(
        private readonly KpiService $kpis,
        private readonly ReportCharts $charts,
    ) {}

    public function permission(): string
    {
        return 'admin.report.dashboard';
    }

    public function view(): string
    {
        return 'report::backend.widgets.dashboard';
    }

    public function data(): array
    {
        $range = ReportRange::fromInput(['preset' => 'last_30']);
        $summary = $this->kpis->summary($range, ReportFilters::none());

        return [
            'reportRange' => $range,
            'reportTiles' => $summary['tiles'],
            'reportGmvChart' => $this->charts->trend('chart-dashboard-gmv', 'gmv', Format::MONEY, $summary['daily'], $summary['previousDaily']),
        ];
    }

    public function assets(): array
    {
        return [
            'modules/report/css/backend/report.css',
            'modules/report/js/backend/report.js',
        ];
    }
}
