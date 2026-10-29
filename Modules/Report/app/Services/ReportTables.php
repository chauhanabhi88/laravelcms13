<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Report\Support\Format;
use Modules\Report\Support\Grain;
use Modules\Report\Support\Metrics;
use Modules\Report\Support\ReportFilters;
use Modules\Report\Support\ReportRange;
use Modules\Report\Support\Table;

/**
 * The table of every report: what the page shows under its charts and what Export writes
 * (FR-RPT-005), so the two never disagree. Columns carry a format (see Format); rows hold raw
 * values (Money, int, float, string).
 */
class ReportTables
{
    public const REPORTS = ['dashboard', 'sales', 'fulfilment', 'returns', 'sellers', 'products', 'geography', 'payments', 'search'];

    /**
     * Daily tables: [metric, format] per column after the date.
     */
    private const DAILY = [
        'sales' => [
            ['orders', Format::COUNT], ['units', Format::COUNT], ['gmv', Format::MONEY], ['discount', Format::MONEY],
            ['cancelled_gmv', Format::MONEY], ['return_value', Format::MONEY], ['net_gmv', Format::MONEY],
            ['net_revenue', Format::MONEY], ['aov', Format::MONEY], ['new_customers', Format::COUNT],
        ],
        'fulfilment' => [
            ['seller_orders', Format::COUNT], ['cancelled_orders', Format::COUNT], ['cancellation_rate', Format::PERCENT],
            ['cancelled_by_customer', Format::COUNT], ['cancelled_by_seller', Format::COUNT], ['cancelled_by_platform', Format::COUNT],
            ['shipped_orders', Format::COUNT], ['rto_orders', Format::COUNT], ['rto_rate', Format::PERCENT], ['delivered_orders', Format::COUNT],
        ],
        'returns' => [
            ['delivered_orders', Format::COUNT], ['delivered_units', Format::COUNT], ['returned_units', Format::COUNT],
            ['return_rate', Format::PERCENT], ['return_value', Format::MONEY],
        ],
        'payments' => [
            ['orders', Format::COUNT], ['cod_orders', Format::COUNT], ['prepaid_orders', Format::COUNT],
            ['cod_share', Format::PERCENT], ['gmv', Format::MONEY],
        ],
    ];

    private const SELLER_COLUMNS = [
        ['name', Format::TEXT], ['gmv', Format::MONEY], ['orders', Format::COUNT], ['units', Format::COUNT], ['aov', Format::MONEY],
        ['rating', Format::TEXT], ['rto_rate', Format::PERCENT], ['return_rate', Format::PERCENT], ['cancellation_rate', Format::PERCENT],
    ];

    private const PRODUCT_COLUMNS = [
        ['name', Format::TEXT], ['seller', Format::TEXT], ['units', Format::COUNT], ['gmv', Format::MONEY],
        ['views', Format::COUNT], ['returns', Format::COUNT], ['return_rate', Format::PERCENT],
    ];

    private const STATE_COLUMNS = [
        ['name', Format::TEXT], ['gmv', Format::MONEY], ['orders', Format::COUNT], ['shipped', Format::COUNT],
        ['rto', Format::COUNT], ['rto_rate', Format::PERCENT], ['return_rate', Format::PERCENT],
    ];

    private const SEARCH_COLUMNS = [
        ['term', Format::TEXT], ['searches', Format::COUNT], ['avg_results', Format::COUNT], ['clicks', Format::COUNT], ['click_rate', Format::PERCENT],
    ];

    public function __construct(
        private readonly ReportQuery $query,
        private readonly LeaderboardService $leaderboards,
        private readonly LiveInsights $insights,
    ) {}

    /**
     * @param  array<string, mixed>  $options  sort (leaderboards)
     * @return array{columns: list<array{key: string, label: string, format: string}>, rows: list<array<string, mixed>>}
     */
    public function table(string $report, ReportRange $range, ReportFilters $filters, ?int $limit = null, array $options = []): array
    {
        $sort = (string) ($options['sort'] ?? '');

        return match ($report) {
            'dashboard', 'sales' => $this->daily('sales', $range, $filters),
            'fulfilment', 'returns', 'payments' => $this->daily($report, $range, $filters),
            'sellers' => $this->make(self::SELLER_COLUMNS, $this->leaderboards->sellers($range, $sort ?: 'gmv', $limit)),
            'products' => $this->make(self::PRODUCT_COLUMNS, $this->leaderboards->products($range, $sort ?: 'units', $limit, $filters->sellerId)),
            'geography' => $this->make(self::STATE_COLUMNS, $this->leaderboards->states($range, $sort ?: 'gmv', $limit)),
            'search' => $this->make(self::SEARCH_COLUMNS, $this->insights->search($range, $limit ?? PHP_INT_MAX)['top']),
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };
    }

    /**
     * Rows an unlimited export of the report would write (decides streamed vs queued).
     */
    public function count(string $report, ReportRange $range, ReportFilters $filters): int
    {
        return match ($report) {
            'dashboard', 'sales', 'fulfilment', 'returns', 'payments' => $range->days(),
            'sellers' => count($this->query->byDimension($range, Grain::DIMENSION_SELLER)),
            'products' => count($this->query->products($range, $filters->sellerId)),
            'geography' => count($this->query->byDimension($range, Grain::DIMENSION_STATE)),
            'search' => (int) DB::table('search_query_log')
                ->whereBetween('created_at', [$range->from->startOfDay(), $range->to->endOfDay()])
                ->distinct()->count('term'),
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };
    }

    /**
     * Newest day first.
     *
     * @return array{columns: list<array{key: string, label: string, format: string}>, rows: list<array<string, mixed>>}
     */
    private function daily(string $table, ReportRange $range, ReportFilters $filters): array
    {
        $columns = [['date', Format::DATE], ...self::DAILY[$table]];
        $rows = [];

        foreach (array_reverse($this->query->daily($range, $filters), true) as $date => $sums) {
            $rows[] = ['date' => CarbonImmutable::parse($date)->toDateString(), ...Metrics::derive($sums)];
        }

        return $this->make($columns, $rows);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array{columns: list<array{key: string, label: string, format: string}>, rows: list<array<string, mixed>>}
     */
    private function make(array $columns, array $rows): array
    {
        return Table::make($columns, $rows);
    }
}
