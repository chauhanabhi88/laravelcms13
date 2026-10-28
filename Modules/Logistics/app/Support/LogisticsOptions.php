<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\Shipment;

/**
 * Translated option lists and badge colours for the logistics admin screens.
 */
final class LogisticsOptions
{
    /**
     * @return array<string, string>
     */
    public static function shipmentStatuses(): array
    {
        return self::translate(Shipment::STATUSES, 'logistics::shipment.statuses.');
    }

    public static function shipmentStatus(string $status): string
    {
        return self::shipmentStatuses()[$status] ?? $status;
    }

    /**
     * Statuses Logistics Ops may record by hand for a manual-courier shipment.
     *
     * @return array<string, string>
     */
    public static function manualStatuses(): array
    {
        return self::translate([
            Shipment::STATUS_PICKED_UP,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_OUT_FOR_DELIVERY,
            Shipment::STATUS_NDR,
            Shipment::STATUS_DELIVERED,
            Shipment::STATUS_RTO_INITIATED,
            Shipment::STATUS_RTO_IN_TRANSIT,
            Shipment::STATUS_RTO_DELIVERED,
            Shipment::STATUS_LOST,
        ], 'logistics::shipment.statuses.');
    }

    public static function shipmentBadge(string $status): string
    {
        return match ($status) {
            Shipment::STATUS_DELIVERED => 'success',
            Shipment::STATUS_NDR, Shipment::STATUS_RTO_INITIATED, Shipment::STATUS_RTO_IN_TRANSIT => 'warning',
            Shipment::STATUS_RTO_DELIVERED, Shipment::STATUS_CANCELLED, Shipment::STATUS_LOST => 'danger',
            Shipment::STATUS_CREATED => 'secondary',
            default => 'info',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function drivers(): array
    {
        return self::translate((array) config('logistics.drivers'), 'logistics::courier.drivers.');
    }

    /**
     * @return array<string, string>
     */
    public static function strategies(): array
    {
        return self::translate((array) config('logistics.strategies'), 'logistics::courier_rule.strategies.');
    }

    /**
     * @return array<string, string>
     */
    public static function zones(): array
    {
        return self::translate((array) config('logistics.zones'), 'logistics::courier_rule.zones.');
    }

    /**
     * @return array<string, string>
     */
    public static function paymentModes(): array
    {
        return self::translate([Shipment::PAYMENT_COD, Shipment::PAYMENT_PREPAID], 'logistics::shipment.payment_modes.');
    }

    /**
     * Shipment grid quick filters.
     *
     * @return array<string, string>
     */
    public static function shipmentFlags(): array
    {
        return self::translate(['needs_attention', 'stuck', 'ndr', 'rto', 'reverse'], 'logistics::shipment.flags.');
    }

    /**
     * @return array<string, string>
     */
    public static function ndrStatuses(): array
    {
        return self::translate(NdrCase::STATUSES, 'logistics::ndr_case.statuses.');
    }

    /**
     * @return array<string, string>
     */
    public static function ndrActions(): array
    {
        return self::translate((array) config('logistics.ndr_actions'), 'logistics::ndr_case.actions.');
    }

    public static function ndrBadge(string $status): string
    {
        return match ($status) {
            NdrCase::STATUS_OPEN => 'warning',
            NdrCase::STATUS_ACTIONED => 'info',
            default => 'secondary',
        };
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function translate(array $values, string $prefix): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = trans($prefix.$value);
        }

        return $options;
    }
}
