<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;

/**
 * Builds the daily_product_stat rows of one day (plan M19 §3.2): units, GMV and returned units
 * of the items placed that day. Page views are a separate snapshot of product.view_count
 * (captureViews), taken only for today: re-aggregating a past day keeps its views.
 */
class ProductAggregator
{
    public function __construct(private readonly OrderFacts $facts) {}

    /**
     * @return array<int, array{product_id: int, seller_id: int, units: int, gmv: string, returns: int}>
     */
    public function compute(CarbonInterface $date): array
    {
        $from = CarbonImmutable::parse($date)->startOfDay();

        return $this->facts->placedItems($from, $from->endOfDay())
            ->groupBy('i.product_id')
            ->get([
                'i.product_id',
                DB::raw('MAX(so.seller_id) as seller_id'),
                DB::raw('SUM(i.quantity) as units'),
                DB::raw('SUM('.OrderFacts::LINE_PAISE.') as gmv_paise'),
                DB::raw('SUM(i.returned_qty) as returns'),
            ])
            ->mapWithKeys(fn (object $row): array => [(int) $row->product_id => [
                'product_id' => (int) $row->product_id,
                'seller_id' => (int) $row->seller_id,
                'units' => (int) $row->units,
                'gmv' => Money::paise((int) $row->gmv_paise)->toDecimal(),
                'returns' => (int) $row->returns,
            ]])
            ->all();
    }

    /**
     * Recompute the day's sales columns (views untouched) and drop rows left with nothing.
     * With $captureViews the view snapshot is taken too. Returns the number of products sold.
     */
    public function aggregate(CarbonInterface $date, bool $captureViews = false): int
    {
        $day = CarbonImmutable::parse($date)->toDateString();
        $rows = $this->compute(CarbonImmutable::parse($day));

        DB::transaction(function () use ($day, $rows, $captureViews): void {
            DB::table('daily_product_stat')->where('date', $day)->update(['units' => 0, 'gmv' => 0, 'returns' => 0]);

            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                DB::table('daily_product_stat')->upsert(
                    array_map(fn (array $row): array => ['date' => $day, ...$row], $chunk),
                    ['date', 'product_id'],
                    ['seller_id', 'units', 'gmv', 'returns'],
                );
            }

            if ($captureViews) {
                $this->captureViews($day);
            }

            DB::table('daily_product_stat')->where('date', $day)
                ->where('units', 0)->where('returns', 0)->where('views', 0)->whereNull('view_total')
                ->delete();
        });

        return count($rows);
    }

    /**
     * Views of the day = product.view_count − the latest snapshot before the day. A product
     * seen for the first time gets a baseline row (views 0) so the next delta starts there.
     */
    public function captureViews(string $day): int
    {
        $previous = DB::table('daily_product_stat')
            ->where('date', '<', $day)
            ->whereNotNull('view_total')
            ->groupBy('product_id')
            ->select('product_id', DB::raw('MAX(view_total) as view_total'));

        $products = DB::table('product as p')
            ->leftJoinSub($previous, 's', 's.product_id', '=', 'p.id')
            ->where('p.view_count', '>', 0)
            ->where(fn ($query) => $query->whereNull('s.view_total')->orWhereColumn('p.view_count', '>', 's.view_total'))
            ->get(['p.id', 'p.seller_id', 'p.view_count', 's.view_total']);

        foreach ($products->chunk(500) as $chunk) {
            DB::table('daily_product_stat')->upsert(
                $chunk->map(fn (object $product): array => [
                    'date' => $day,
                    'product_id' => (int) $product->id,
                    'seller_id' => (int) $product->seller_id,
                    'views' => $product->view_total === null ? 0 : (int) $product->view_count - (int) $product->view_total,
                    'view_total' => (int) $product->view_count,
                ])->values()->all(),
                ['date', 'product_id'],
                ['views', 'view_total'],
            );
        }

        return $products->count();
    }
}
