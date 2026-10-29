<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Report\Services\LiveInsights;
use Modules\Report\Support\Chart;
use Modules\Report\Support\Format;
use Modules\Report\Support\Table;

/**
 * Search (M06 data) for any date range: searches per day, the share that found nothing, the
 * top terms with their click-through and the terms without results. The Storefront search
 * report keeps its rolling 7/30-day view.
 */
class SearchReportController extends ReportController
{
    protected const REPORT = 'search';

    protected const DIMENSIONS = [];

    public function index(Request $request, LiveInsights $insights): View
    {
        [$range, $filters] = $this->context($request);
        $search = $insights->search($range);

        $tile = fn (string $key, mixed $value, string $format): array => [
            'key' => $key, 'format' => $format, 'value' => $value, 'display' => Format::display($value, $format),
            'delta' => null, 'delta_display' => null, 'trend' => 'neutral', 'sparkline' => [],
        ];

        return $this->render('report::backend.page', $request, $range, $filters, [
            'tiles' => [
                [...$tile('searches', $search['searches'], Format::COUNT), 'sparkline' => array_values($search['daily'])],
                $tile('zero_share', $search['zero_share'], Format::PERCENT),
                $tile('clicks', $search['clicks'], Format::COUNT),
            ],
            'charts' => [
                ['chart' => Chart::make('chart-searches', 'line', Format::COUNT, Chart::dayLabels(array_keys($search['daily'])), [
                    Chart::series(trans('report::report.columns.searches'), array_values($search['daily'])),
                ]), 'title' => 'searches', 'wide' => true],
            ],
            'tables' => [
                ['title' => 'top_terms', 'table' => Table::make([['term', Format::TEXT], ['searches', Format::COUNT], ['avg_results', Format::COUNT], ['clicks', Format::COUNT], ['click_rate', Format::PERCENT]], $search['top'])],
                ['title' => 'zero_terms', 'table' => Table::make([['term', Format::TEXT], ['searches', Format::COUNT]], $search['zero'])],
            ],
        ]);
    }
}
