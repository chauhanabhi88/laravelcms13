<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\LeaderboardService;
use Modules\Report\Support\Format;
use Modules\Report\Support\Table;

/**
 * Geography (FR-RPT-002): shipping states by GMV or by RTO rate, and the delivery pincodes with
 * the most GMV and the highest RTO (fraud hotspots).
 */
class GeographyReportController extends ReportController
{
    protected const REPORT = 'geography';

    protected const DIMENSIONS = [];

    public function index(Request $request, LeaderboardService $leaderboards): View
    {
        [$range, $filters] = $this->context($request);
        $sort = in_array($request->query('sort'), LeaderboardService::PLACE_SORTS, true) ? (string) $request->query('sort') : 'gmv';
        $limit = (int) config('report.leaderboard_limit', 20);
        $states = $this->tables->table('geography', $range, $filters, $limit, ['sort' => $sort]);

        return $this->render('report::backend.page', $request, $range, $filters, [
            'sortOptions' => LeaderboardService::PLACE_SORTS,
            'sort' => $sort,
            'charts' => [
                ['chart' => $this->charts->ranked('chart-states', $leaderboards->states($range, 'gmv', 10), 'name', 'gmv', Format::MONEY, trans('report::report.columns.gmv')), 'title' => 'states'],
                ['chart' => $this->charts->rtoByState($range), 'title' => 'rto_states'],
            ],
            'tables' => [
                ['title' => 'states', 'table' => $states],
                ['title' => 'gmv_pincodes', 'table' => Table::make(Table::PINCODE_COLUMNS, $leaderboards->pincodes($range, 'gmv', $limit))],
                ['title' => 'rto_pincodes', 'table' => Table::make(Table::PINCODE_COLUMNS, $leaderboards->pincodes($range, 'rto', $limit))],
            ],
        ]);
    }
}
