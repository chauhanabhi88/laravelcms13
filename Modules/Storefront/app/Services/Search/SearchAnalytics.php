<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

use Illuminate\Support\Facades\DB;
use Modules\Storefront\Models\SearchQueryLog;
use Modules\Storefront\Models\SearchTermStat;
use Modules\Storefront\Support\StorefrontSettings;

/**
 * Search terms (FR-DSC-011/012): the query log, the nightly roll-up into search_term_stat,
 * trending suggestions and the "Did you mean" spelling hint.
 */
class SearchAnalytics
{
    public function log(string $term, int $results, ?int $customerId, ?string $sessionId): void
    {
        $term = SearchQueryLog::normaliseTerm($term);

        if ($term === '') {
            return;
        }

        SearchQueryLog::query()->create([
            'term' => $term,
            'results_count' => $results,
            'customer_id' => $customerId,
            'session_id' => $sessionId !== null ? mb_substr($sessionId, 0, 64) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Attach a product click to the session's latest search for the term.
     */
    public function recordClick(string $term, int $productId, ?string $sessionId): bool
    {
        $term = SearchQueryLog::normaliseTerm($term);

        if ($term === '' || $sessionId === null) {
            return false;
        }

        $id = SearchQueryLog::query()
            ->where('session_id', mb_substr($sessionId, 0, 64))
            ->where('term', $term)
            ->whereNull('clicked_product_id')
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->value('id');

        return $id !== null && SearchQueryLog::query()->whereKey($id)->update(['clicked_product_id' => $productId]) > 0;
    }

    /**
     * Trending terms (manual ones first), optionally only those starting with $prefix.
     *
     * @return list<string>
     */
    public function trending(int $limit, ?string $prefix = null): array
    {
        $prefix = $prefix !== null ? SearchQueryLog::normaliseTerm($prefix) : null;
        $terms = array_values(array_filter(
            StorefrontSettings::manualTrendingTerms(),
            fn (string $term): bool => $prefix === null || str_starts_with($term, $prefix),
        ));

        if (count($terms) < $limit) {
            $computed = SearchTermStat::query()
                ->where('avg_results', '>', 0)
                ->where('searches_7d', '>', 0)
                ->when($prefix !== null && $prefix !== '', fn ($query) => $query->where('term', 'like', TextQuery::prefixLike($prefix)))
                ->orderByDesc('searches_7d')
                ->orderByDesc('searches_30d')
                ->limit($limit * 2)
                ->pluck('term')
                ->all();

            $terms = array_values(array_unique(array_merge($terms, $computed)));
        }

        return array_slice($terms, 0, $limit);
    }

    /**
     * The closest popular term within the configured edit distance, if any.
     */
    public function didYouMean(string $term): ?string
    {
        $term = SearchQueryLog::normaliseTerm($term);
        $maxDistance = (int) config('storefront.did_you_mean_distance', 2);

        if (mb_strlen($term) < 3) {
            return null;
        }

        $candidates = array_unique(array_merge(
            StorefrontSettings::manualTrendingTerms(),
            SearchTermStat::query()->where('avg_results', '>', 0)->orderByDesc('searches_30d')->limit((int) config('storefront.trending_pool', 500))->pluck('term')->all(),
        ));

        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            if ($candidate === $term || abs(strlen($candidate) - strlen($term)) > $maxDistance) {
                continue;
            }

            $distance = levenshtein($term, $candidate);

            if ($distance <= $maxDistance && $distance < $bestDistance) {
                [$best, $bestDistance] = [$candidate, $distance];
            }
        }

        return $best;
    }

    /**
     * Roll the last 30 days of the log up into search_term_stat (terms not searched in that
     * window are dropped) and prune log rows past the retention period.
     *
     * @return array{terms: int, pruned: int}
     */
    public function aggregate(): array
    {
        $now = now();
        $since30 = $now->copy()->subDays(30);
        $since7 = $now->copy()->subDays(7);

        $rows = DB::table('search_query_log')
            ->where('created_at', '>=', $since30)
            ->groupBy('term')
            ->get([
                'term',
                DB::raw('COUNT(*) as searches_30d'),
                DB::raw('SUM(CASE WHEN created_at >= '.DB::getPdo()->quote($since7->toDateTimeString()).' THEN 1 ELSE 0 END) as searches_7d'),
                DB::raw('ROUND(AVG(results_count)) as avg_results'),
                DB::raw('SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_result_count'),
                DB::raw('SUM(CASE WHEN clicked_product_id IS NOT NULL THEN 1 ELSE 0 END) as clicks_30d'),
            ]);

        DB::transaction(function () use ($rows, $now): void {
            DB::table('search_term_stat')->delete();

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('search_term_stat')->insert($chunk->map(fn (object $row): array => [
                    'term' => (string) $row->term,
                    'searches_7d' => (int) $row->searches_7d,
                    'searches_30d' => (int) $row->searches_30d,
                    'avg_results' => (int) $row->avg_results,
                    'zero_result_count' => (int) $row->zero_result_count,
                    'clicks_30d' => (int) $row->clicks_30d,
                    'updated_at' => $now,
                ])->values()->all());
            }
        });

        $pruned = DB::table('search_query_log')
            ->where('created_at', '<', $now->copy()->subDays((int) config('storefront.search_log_retention_days', 90)))
            ->delete();

        return ['terms' => $rows->count(), 'pruned' => $pruned];
    }
}
