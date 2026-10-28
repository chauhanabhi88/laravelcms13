<?php

declare(strict_types=1);

namespace Modules\Order\Repositories\Cache;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Repositories\SellerOrderDeskRepository;

/**
 * Pass-through: order statuses change outside the repository (services, courier webhooks) and
 * the seller expects to see them at once. The tab counts keep their own short cache, flushed
 * on every SellerOrderStatusChanged.
 */
class CacheSellerOrderDeskDecorator extends BaseCacheDecorator implements SellerOrderDeskRepository
{
    public function __construct(SellerOrderDeskRepository $desk)
    {
        parent::__construct();
        $this->entityName = config('order.cache.seller_order_desk');
        $this->repository = $desk;
    }

    public function orders(int $sellerId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->repository->orders($sellerId, $filters, $perPage);
    }

    public function tabCounts(int $sellerId): array
    {
        return $this->repository->tabCounts($sellerId);
    }

    public function forgetCounts(int $sellerId): void
    {
        $this->repository->forgetCounts($sellerId);
    }

    public function export(int $sellerId, array $filters): LazyCollection
    {
        return $this->repository->export($sellerId, $filters);
    }

    public function ownedByIds(int $sellerId, array $ids): Collection
    {
        return $this->repository->ownedByIds($sellerId, $ids);
    }

    public function findBySubOrderNo(int $sellerId, string $subOrderNo): ?SellerOrder
    {
        return $this->repository->findBySubOrderNo($sellerId, $subOrderNo);
    }

    public function dashboardCounts(int $sellerId): array
    {
        return $this->repository->dashboardCounts($sellerId);
    }

    public function qualitySummary(int $sellerId, int $days = 30): array
    {
        return $this->repository->qualitySummary($sellerId, $days);
    }
}
