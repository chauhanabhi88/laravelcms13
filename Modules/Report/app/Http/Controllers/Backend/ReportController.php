<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Report\Services\ReportCharts;
use Modules\Report\Services\ReportTables;
use Modules\Report\Support\ReportFilters;
use Modules\Report\Support\ReportOptions;
use Modules\Report\Support\ReportRange;

/**
 * Base of the report pages (plan M19 §7): the filter bar (date preset or custom range,
 * comparison, one dimension filter), the report tabs the admin may open, the chart assets and
 * the export links.
 */
abstract class ReportController extends BackendController
{
    /**
     * The report key (ReportTables::REPORTS).
     */
    protected const REPORT = 'dashboard';

    /**
     * Dimension filters the page honours (Grain::DIMENSIONS); the others are hidden.
     *
     * @var list<string>
     */
    protected const DIMENSIONS = ['seller', 'category', 'state', 'payment'];

    public function __construct(
        protected readonly ReportTables $tables,
        protected readonly ReportCharts $charts,
    ) {
        parent::__construct();

        $this->getAssetManager()->addAssets([
            'modules/report/css/backend/report.css',
            'modules/theme/backend/staradmin/vendors/chart.js/Chart.min.js',
            'modules/report/js/backend/report.js',
        ]);
    }

    /**
     * @return array{0: ReportRange, 1: ReportFilters}
     */
    protected function context(Request $request): array
    {
        $range = ReportRange::fromInput($request->query());
        $input = array_intersect_key($request->query(), array_flip(array_map(fn (string $dimension): string => $dimension === 'payment' ? 'payment' : $dimension.'_id', static::DIMENSIONS)));

        return [$range, ReportFilters::fromInput($input)];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function render(string $view, Request $request, ReportRange $range, ReportFilters $filters, array $data = []): View
    {
        $query = [...$range->toQuery(), ...$filters->toQuery()];
        if ($request->query('sort') !== null) {
            $query['sort'] = (string) $request->query('sort');
        }

        return view($view, [
            'report' => static::REPORT,
            'range' => $range,
            'filters' => $filters,
            'query' => $query,
            'warnings' => array_values(array_unique([...$range->warnings, ...$filters->warnings])),
            'dimensions' => static::DIMENSIONS,
            'filterOptions' => [
                'seller' => in_array('seller', static::DIMENSIONS, true) ? ReportOptions::sellers() : [],
                'category' => in_array('category', static::DIMENSIONS, true) ? ReportOptions::categories() : [],
                'state' => in_array('state', static::DIMENSIONS, true) ? ReportOptions::states() : [],
            ],
            'tabs' => array_values(array_filter(ReportTables::REPORTS, fn (string $report): bool => Gate::allows('admin.report.'.$report))),
            'canExport' => Gate::allows('admin.report.export'),
            ...$data,
        ]);
    }
}
