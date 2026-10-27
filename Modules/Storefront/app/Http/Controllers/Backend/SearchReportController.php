<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Storefront\Models\SearchTermStat;

/**
 * Search report (FR-DSC-012): top terms, terms that found nothing, and click-through, from
 * the nightly search_term_stat roll-up.
 */
class SearchReportController extends BackendController
{
    protected const LIMIT = 50;

    public function index(Request $request): View
    {
        $updatedAt = SearchTermStat::query()->max('updated_at');

        return view('storefront::backend.search_report.index', [
            'topTerms' => SearchTermStat::query()->orderByDesc('searches_7d')->orderByDesc('searches_30d')->limit(self::LIMIT)->get(),
            'zeroResultTerms' => SearchTermStat::query()->where('zero_result_count', '>', 0)->orderByDesc('zero_result_count')->limit(self::LIMIT)->get(),
            'clickThrough' => SearchTermStat::query()->where('searches_30d', '>', 0)->orderByDesc('searches_30d')->limit(self::LIMIT)->get()
                ->sortBy(fn (SearchTermStat $stat): float => $stat->clickThroughRate())
                ->values(),
            'updatedAt' => $updatedAt,
            'activeMenuId' => getActiveMenuId($request),
        ]);
    }
}
