<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\LeaderboardService;
use Modules\Report\Support\Format;
use Modules\Report\Support\Table;

/**
 * Products & categories (FR-RPT-002/003): top products by units or GMV (with page views and
 * returns; optionally for one seller), the category mix and the category table.
 */
class ProductReportController extends ReportController
{
    protected const REPORT = 'products';

    protected const DIMENSIONS = ['seller'];

    public function index(Request $request, LeaderboardService $leaderboards): View
    {
        [$range, $filters] = $this->context($request);
        $sort = in_array($request->query('sort'), LeaderboardService::PRODUCT_SORTS, true) ? (string) $request->query('sort') : 'units';

        $categories = Table::make(
            [['name', Format::TEXT], ['gmv', Format::MONEY], ['share', Format::PERCENT], ['orders', Format::COUNT], ['units', Format::COUNT], ['return_rate', Format::PERCENT], ['rto_rate', Format::PERCENT]],
            $leaderboards->categories($range),
        );

        return $this->render('report::backend.page', $request, $range, $filters, [
            'sortOptions' => LeaderboardService::PRODUCT_SORTS,
            'sort' => $sort,
            'charts' => [
                ['chart' => $this->charts->categoryMix($range), 'title' => 'categories', 'wide' => true],
            ],
            'tables' => [
                ['title' => 'products', 'table' => $this->tables->table('products', $range, $filters, (int) config('report.leaderboard_limit', 20), ['sort' => $sort])],
                ['title' => 'categories', 'table' => $categories],
            ],
        ]);
    }
}
