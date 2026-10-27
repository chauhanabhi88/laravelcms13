<?php

declare(strict_types=1);

namespace Modules\Product\Repositories\Cache;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Product\Repositories\SellerCatalogRepository;

/**
 * Pass-through: stock, prices and upload progress change outside the repository (services,
 * queued jobs), and the seller expects to see their edit at once.
 */
class CacheSellerCatalogDecorator extends BaseCacheDecorator implements SellerCatalogRepository
{
    public function __construct(SellerCatalogRepository $sellerCatalog)
    {
        parent::__construct();
        $this->entityName = config('product.cache.seller_catalog');
        $this->repository = $sellerCatalog;
    }

    public function products(int $sellerId, string $tab, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->repository->products($sellerId, $tab, $search, $perPage);
    }

    public function tabCounts(int $sellerId): array
    {
        return $this->repository->tabCounts($sellerId);
    }

    public function inventory(int $sellerId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->repository->inventory($sellerId, $filters, $perPage);
    }

    public function inventoryExport(int $sellerId, array $filters): LazyCollection
    {
        return $this->repository->inventoryExport($sellerId, $filters);
    }

    public function lowStockCount(int $sellerId): int
    {
        return $this->repository->lowStockCount($sellerId);
    }

    public function uploads(int $sellerId, ?string $type, int $perPage): LengthAwarePaginator
    {
        return $this->repository->uploads($sellerId, $type, $perPage);
    }

    public function priceStockRows(int $sellerId): array
    {
        return $this->repository->priceStockRows($sellerId);
    }
}
