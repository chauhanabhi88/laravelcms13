<?php

declare(strict_types=1);

namespace Modules\Promotion\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Product\Contracts\PriceResolver;
use Modules\Product\DTO\PriceOverride;
use Modules\Promotion\Models\FlashDeal;
use Modules\Promotion\Models\FlashDealItem;

/**
 * Flash-deal prices (plan M18 §5): while a deal runs, its variants sell at the deal price,
 * provided it is below the variant's own price and deal units are left (sold < stock_cap); after
 * the window or the cap the price falls back. The lowest running deal wins when a variant is in
 * several. One query per call, no caching, so the price changes exactly at the window edges.
 */
class FlashDealPricing implements PriceResolver
{
    public function forVariants(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $overrides = [];

        foreach ($this->rows(fn (Builder $query) => $query->whereIn('i.variant_id', array_values(array_unique($variantIds)))) as $row) {
            $overrides[(int) $row->variant_id] ??= $this->override($row);
        }

        return $overrides;
    }

    public function forProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $overrides = [];

        foreach ($this->rows(fn (Builder $query) => $query->whereIn('i.product_id', array_values(array_unique($productIds)))->where('v.status', config('core.enabled'))) as $row) {
            $overrides[(int) $row->product_id] ??= $this->override($row);
        }

        return $overrides;
    }

    /**
     * Lock the running deal rows of these variants (place-order, after the variant locks), id
     * order so concurrent orders take them in the same order.
     *
     * @param  list<int>  $variantIds
     * @return EloquentCollection<int, FlashDealItem>
     */
    public function lockRunning(array $variantIds): EloquentCollection
    {
        $dealIds = FlashDeal::query()->running()->pluck('id')->all();

        if ($variantIds === [] || $dealIds === []) {
            return new EloquentCollection;
        }

        return FlashDealItem::query()
            ->whereIn('flash_deal_id', $dealIds)
            ->whereIn('variant_id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Running, enabled deal rows below the variant's own price with units left, cheapest first.
     *
     * @param  callable(Builder): mixed  $filter
     * @return iterable<object>
     */
    protected function rows(callable $filter): iterable
    {
        $now = Carbon::now();

        $query = DB::table('flash_deal_item as i')
            ->join('flash_deal as d', 'd.id', '=', 'i.flash_deal_id')
            ->join('product_variant as v', 'v.id', '=', 'i.variant_id')
            ->where('d.status', config('core.enabled'))
            ->where('d.starts_at', '<=', $now)
            ->where('d.ends_at', '>', $now)
            ->where(fn (Builder $query) => $query->whereNull('i.stock_cap')->orWhereColumn('i.sold', '<', 'i.stock_cap'))
            ->whereColumn('i.deal_price', '<', 'v.price')
            ->orderBy('i.deal_price')
            ->orderBy('i.id')
            ->select(['i.id', 'i.variant_id', 'i.product_id', 'i.deal_price', 'i.stock_cap', 'i.sold', 'd.ends_at']);

        $filter($query);

        return $query->get();
    }

    protected function override(object $row): PriceOverride
    {
        return new PriceOverride(
            Money::of((string) $row->deal_price),
            $row->ends_at !== null ? Carbon::parse($row->ends_at) : null,
            'flash_deal',
            (int) $row->id,
            $row->stock_cap !== null ? max(0, (int) $row->stock_cap - (int) $row->sold) : null,
        );
    }
}
