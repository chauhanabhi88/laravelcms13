<?php

declare(strict_types=1);

namespace Modules\Storefront\Services;

use Illuminate\Support\Facades\DB;
use Modules\Storefront\Models\CustomerRecentView;
use Modules\Storefront\Services\Search\SearchIndexer;

/**
 * Recently viewed products (FR-DSC-010). The PDP (M07) calls record() for signed-in
 * customers; guests keep up to storefront.recent_views_guest_limit ids in localStorage
 * ("rv") and send them back to render the row.
 */
class RecentlyViewedService
{
    public function __construct(protected ProductCardPresenter $cards) {}

    public function record(int $customerId, int $productId): void
    {
        DB::table('customer_recent_view')->upsert(
            [['customer_id' => $customerId, 'product_id' => $productId, 'viewed_at' => now()]],
            ['customer_id', 'product_id'],
            ['viewed_at'],
        );

        $keep = $this->ids($customerId, (int) config('storefront.recent_views_keep', 50));

        CustomerRecentView::query()->where('customer_id', $customerId)->whereNotIn('product_id', $keep)->delete();
    }

    /**
     * The customer's latest viewed product ids, newest first.
     *
     * @return list<int>
     */
    public function ids(int $customerId, int $limit = 20): array
    {
        return CustomerRecentView::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('viewed_at')
            ->orderByDesc('product_id')
            ->limit($limit)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Cards for the given ids that are still visible on the storefront, in the given order.
     *
     * @param  list<int>  $productIds
     * @return list<array<string, mixed>>
     */
    public function cards(array $productIds): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $productIds)))), 0, (int) config('storefront.recent_views_guest_limit', 20));

        if ($ids === []) {
            return [];
        }

        $visible = DB::table('product_search_index')
            ->where('locale', SearchIndexer::searchLocale())
            ->where('is_visible', config('core.yes'))
            ->whereIn('product_id', $ids)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->cards->present(array_values(array_intersect($ids, $visible)));
    }
}
