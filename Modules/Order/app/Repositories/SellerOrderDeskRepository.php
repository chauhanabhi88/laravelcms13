<?php

declare(strict_types=1);

namespace Modules\Order\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\BaseRepository;
use Modules\Order\Models\SellerOrder;

/**
 * Supplier order desk reads (plan M14 §5.1). Every method takes the seller id: the panel never
 * reads another seller's orders.
 *
 * @phpstan-type DeskFilters array{tab: string, sub: string|null, q: string, sku: string, payment: string|null, sla: string|null, from: string|null, to: string|null}
 */
interface SellerOrderDeskRepository extends BaseRepository
{
    public const TAB_PENDING = 'pending';

    public const TAB_READY = 'ready';

    public const TAB_TRANSIT = 'transit';

    public const TAB_DELIVERED = 'delivered';

    public const TAB_CANCELLED = 'cancelled';

    public const TAB_RETURNS = 'returns';

    /**
     * Desk tabs in display order and the seller-order statuses each shows (FR-FUL-001).
     */
    public const TABS = [
        self::TAB_PENDING => [SellerOrder::STATUS_PENDING_ACCEPTANCE],
        self::TAB_READY => [SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED],
        self::TAB_TRANSIT => [SellerOrder::STATUS_SHIPPED, SellerOrder::STATUS_OUT_FOR_DELIVERY, SellerOrder::STATUS_NDR],
        self::TAB_DELIVERED => [SellerOrder::STATUS_DELIVERED, SellerOrder::STATUS_COMPLETED],
        self::TAB_CANCELLED => [SellerOrder::STATUS_CANCELLED],
        self::TAB_RETURNS => [SellerOrder::STATUS_RETURN_REQUESTED, SellerOrder::STATUS_RETURNED, SellerOrder::STATUS_RTO_INITIATED, SellerOrder::STATUS_RTO_DELIVERED],
    ];

    /**
     * Ready to Ship sub-filters.
     */
    public const SUB_LABEL_PENDING = 'label_pending';

    public const SUB_AWAITING_PICKUP = 'awaiting_pickup';

    public const SLA_ON_TIME = 'on_time';

    public const SLA_AT_RISK = 'at_risk';

    public const SLA_BREACHED = 'breached';

    /**
     * @param  DeskFilters  $filters
     * @return LengthAwarePaginator<int, SellerOrder>
     */
    public function orders(int $sellerId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return array<string, int> tab => seller orders in it (cached briefly, see forgetCounts())
     */
    public function tabCounts(int $sellerId): array;

    public function forgetCounts(int $sellerId): void;

    /**
     * @param  DeskFilters  $filters
     * @return LazyCollection<int, SellerOrder>
     */
    public function export(int $sellerId, array $filters): LazyCollection;

    /**
     * The seller's own seller orders among $ids (others are silently dropped).
     *
     * @param  list<int>  $ids
     * @return Collection<int, SellerOrder>
     */
    public function ownedByIds(int $sellerId, array $ids): Collection;

    public function findBySubOrderNo(int $sellerId, string $subOrderNo): ?SellerOrder;

    /**
     * Supplier dashboard KPIs: orders today, pending acceptance, ready to ship, open returns/RTO,
     * SLA breached.
     *
     * @return array{orders_today: int, pending_acceptance: int, ready_to_ship: int, returns: int, sla_breached: int}
     */
    public function dashboardCounts(int $sellerId): array;

    /**
     * Rates over the last $days days of quality snapshots (FR-FUL-008), in percent.
     *
     * @return array{orders: int, cancellation_rate: float|null, late_dispatch_rate: float|null, return_rate: float|null, rto_rate: float|null, rating: float|null}
     */
    public function qualitySummary(int $sellerId, int $days = 30): array;
}
