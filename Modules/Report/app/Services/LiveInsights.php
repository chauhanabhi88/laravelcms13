<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Illuminate\Support\Facades\DB;
use Modules\Report\Support\Metrics;
use Modules\Report\Support\ReportRange;
use Modules\Returns\Models\ReturnRequest;

/**
 * Report sections without an aggregate, read straight from their logs for the range: return
 * reasons (return_request) and search behaviour (search_query_log, M06).
 */
class LiveInsights
{
    /**
     * Returns raised in the range per reason (cancelled requests left out).
     *
     * @return list<array{reason: string, label: string, requests: int, units: int, share: float|null}>
     */
    public function returnReasons(ReportRange $range, ?int $sellerId = null): array
    {
        $rows = DB::table('return_request as r')
            ->join('return_request_item as ri', 'ri.return_request_id', '=', 'r.id')
            ->whereBetween('r.created_at', [$range->from->startOfDay(), $range->to->endOfDay()])
            ->where('r.status', '!=', ReturnRequest::STATUS_CANCELLED)
            ->when($sellerId !== null, fn ($query) => $query->where('r.seller_id', $sellerId))
            ->groupBy('r.reason_code')
            ->get(['r.reason_code', DB::raw('COUNT(DISTINCT r.id) as requests'), DB::raw('SUM(ri.quantity) as units')]);

        $total = (int) $rows->sum('requests');

        return $rows
            ->map(fn (object $row): array => [
                'reason' => (string) $row->reason_code,
                'label' => self::reasonLabel((string) $row->reason_code),
                'requests' => (int) $row->requests,
                'units' => (int) $row->units,
                'share' => Metrics::rate((int) $row->requests, $total),
            ])
            ->sortByDesc('requests')
            ->values()
            ->all();
    }

    /**
     * Searches in the range: totals, per day, top terms and terms that found nothing.
     *
     * @return array{searches: int, zero_results: int, zero_share: float|null, clicks: int, daily: array<string, int>, top: list<array<string, mixed>>, zero: list<array<string, mixed>>}
     */
    public function search(ReportRange $range, ?int $limit = null): array
    {
        $limit ??= (int) config('report.leaderboard_limit', 20);
        $base = fn () => DB::table('search_query_log')->whereBetween('created_at', [$range->from->startOfDay(), $range->to->endOfDay()]);

        $totals = $base()->selectRaw('COUNT(*) as searches, SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_results, SUM(CASE WHEN clicked_product_id IS NOT NULL THEN 1 ELSE 0 END) as clicks')->first();
        $perDay = $base()->groupBy(DB::raw('DATE(created_at)'))->get([DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as searches')])
            ->pluck('searches', 'day')->all();

        $daily = [];
        foreach ($range->dates() as $date) {
            $daily[$date] = (int) ($perDay[$date] ?? 0);
        }

        $top = $base()->groupBy('term')->orderByDesc(DB::raw('COUNT(*)'))->orderBy('term')->limit($limit)
            ->get(['term', DB::raw('COUNT(*) as searches'), DB::raw('ROUND(AVG(results_count)) as avg_results'), DB::raw('SUM(CASE WHEN clicked_product_id IS NOT NULL THEN 1 ELSE 0 END) as clicks')])
            ->map(fn (object $row): array => [
                'term' => (string) $row->term,
                'searches' => (int) $row->searches,
                'avg_results' => (int) $row->avg_results,
                'clicks' => (int) $row->clicks,
                'click_rate' => Metrics::rate((int) $row->clicks, (int) $row->searches),
            ])->all();

        $zero = $base()->where('results_count', 0)->groupBy('term')->orderByDesc(DB::raw('COUNT(*)'))->orderBy('term')->limit($limit)
            ->get(['term', DB::raw('COUNT(*) as searches')])
            ->map(fn (object $row): array => ['term' => (string) $row->term, 'searches' => (int) $row->searches])->all();

        $searches = (int) ($totals->searches ?? 0);
        $zeroResults = (int) ($totals->zero_results ?? 0);

        return [
            'searches' => $searches,
            'zero_results' => $zeroResults,
            'zero_share' => Metrics::rate($zeroResults, $searches),
            'clicks' => (int) ($totals->clicks ?? 0),
            'daily' => $daily,
            'top' => $top,
            'zero' => $zero,
        ];
    }

    public static function reasonLabel(string $code): string
    {
        $key = 'returns::returns.reasons.'.$code;
        $label = trans($key);

        return is_string($label) && $label !== $key ? $label : str_replace('_', ' ', ucfirst($code));
    }
}
