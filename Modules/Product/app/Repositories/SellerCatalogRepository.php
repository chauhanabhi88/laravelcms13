<?php

declare(strict_types=1);

namespace Modules\Product\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\BaseRepository;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

/**
 * Supplier panel catalog reads (M05). Every method takes the seller id: the panel never
 * reads another seller's rows.
 *
 * @phpstan-type InventoryFilters array{q?: string|null, stock?: string|null, category?: int|string|null}
 */
interface SellerCatalogRepository extends BaseRepository
{
    public const TAB_ALL = 'all';

    public const TAB_OUT_OF_STOCK = 'out_of_stock';

    /**
     * Product list tabs, in display order.
     */
    public const TABS = ['all', 'live', 'pending_qc', 'rejected', 'paused', 'draft', 'out_of_stock', 'blocked'];

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(int $sellerId, string $tab, ?string $search, int $perPage): LengthAwarePaginator;

    /**
     * @return array<string, int> tab => products in it
     */
    public function tabCounts(int $sellerId): array;

    /**
     * @param  InventoryFilters  $filters
     * @return LengthAwarePaginator<int, ProductVariant>
     */
    public function inventory(int $sellerId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Every variant matching the filters, for the CSV export.
     *
     * @param  InventoryFilters  $filters
     * @return LazyCollection<int, ProductVariant>
     */
    public function inventoryExport(int $sellerId, array $filters): LazyCollection;

    /**
     * Enabled variants with available stock at or below their threshold.
     */
    public function lowStockCount(int $sellerId): int;

    /**
     * @return LengthAwarePaginator<int, CatalogUpload>
     */
    public function uploads(int $sellerId, ?string $type, int $perPage): LengthAwarePaginator;

    /**
     * The seller's variants for the price & stock template.
     *
     * @return list<array{seller_sku: string, mrp: string, price: string, stock: int}>
     */
    public function priceStockRows(int $sellerId): array;
}
