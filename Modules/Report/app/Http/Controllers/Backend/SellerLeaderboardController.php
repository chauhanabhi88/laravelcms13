<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\LeaderboardService;
use Modules\Report\Support\Format;

/**
 * Seller leaderboard (FR-RPT-002): the top sellers by GMV, orders or rating with their AOV, RTO,
 * return and cancellation rates.
 */
class SellerLeaderboardController extends ReportController
{
    protected const REPORT = 'sellers';

    protected const DIMENSIONS = [];

    public function index(Request $request): View
    {
        [$range, $filters] = $this->context($request);
        $sort = in_array($request->query('sort'), LeaderboardService::SELLER_SORTS, true) ? (string) $request->query('sort') : 'gmv';
        $table = $this->tables->table('sellers', $range, $filters, (int) config('report.leaderboard_limit', 20), ['sort' => $sort]);

        return $this->render('report::backend.page', $request, $range, $filters, [
            'sortOptions' => LeaderboardService::SELLER_SORTS,
            'sort' => $sort,
            'charts' => [
                ['chart' => $this->charts->ranked('chart-sellers', array_slice($table['rows'], 0, 10), 'name', 'gmv', Format::MONEY, trans('report::report.columns.gmv')), 'title' => 'sellers', 'wide' => true],
            ],
            'tables' => [
                ['title' => 'sellers', 'table' => $table],
            ],
        ]);
    }
}
