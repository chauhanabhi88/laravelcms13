<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SellerOrder;

/**
 * Shipment status → seller-order status (plan M13 §5.4), and the shortest chain of allowed
 * seller-order transitions to get there. A courier may skip scans (a first scan "in transit"
 * while the seller never marked the order packed), so the seller order walks every step in
 * between, each with its history row and timestamps.
 */
final class SellerOrderPath
{
    /**
     * @var array<string, string>
     */
    public const TARGETS = [
        Shipment::STATUS_PICKED_UP => SellerOrder::STATUS_SHIPPED,
        Shipment::STATUS_IN_TRANSIT => SellerOrder::STATUS_SHIPPED,
        Shipment::STATUS_OUT_FOR_DELIVERY => SellerOrder::STATUS_OUT_FOR_DELIVERY,
        Shipment::STATUS_NDR => SellerOrder::STATUS_NDR,
        Shipment::STATUS_DELIVERED => SellerOrder::STATUS_DELIVERED,
        Shipment::STATUS_RTO_INITIATED => SellerOrder::STATUS_RTO_INITIATED,
        Shipment::STATUS_RTO_IN_TRANSIT => SellerOrder::STATUS_RTO_INITIATED,
        Shipment::STATUS_RTO_DELIVERED => SellerOrder::STATUS_RTO_DELIVERED,
    ];

    public static function targetFor(string $shipmentStatus): ?string
    {
        return self::TARGETS[$shipmentStatus] ?? null;
    }

    /**
     * The statuses to pass through after $from to reach $to (excluding $from), never through
     * a cancellation; null when $to cannot be reached.
     *
     * @return list<string>|null
     */
    public static function between(string $from, string $to): ?array
    {
        if ($from === $to) {
            return [];
        }

        $graph = SellerOrder::transitions();
        $queue = [[$from]];
        $seen = [$from => true];

        while ($queue !== []) {
            $path = array_shift($queue);
            $last = $path[count($path) - 1];

            // Leaving return_requested is the Returns module's call (M15), never a courier scan's.
            if ($last === SellerOrder::STATUS_RETURN_REQUESTED) {
                continue;
            }

            foreach ($graph[$last] ?? [] as $next) {
                if ($next === SellerOrder::STATUS_CANCELLED || isset($seen[$next])) {
                    continue;
                }

                $candidate = [...$path, $next];

                if ($next === $to) {
                    return array_slice($candidate, 1);
                }

                $seen[$next] = true;
                $queue[] = $candidate;
            }
        }

        return null;
    }
}
