<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Illuminate\Support\Carbon;
use Modules\Order\Models\SellerOrder;

/**
 * The order desk's dispatch countdown chip (plan M14 §5.6): green when more than
 * order.fulfilment.sla_at_risk_hours (12) remain, amber below, red once breached. Only orders
 * not yet handed to the courier have one.
 */
final class SlaClock
{
    public const ON_TIME = 'on_time';

    public const AT_RISK = 'at_risk';

    public const BREACHED = 'breached';

    /**
     * @return array{state: string, label: string, variant: string}|null
     */
    public static function for(SellerOrder $sellerOrder, ?Carbon $now = null): ?array
    {
        if (! in_array($sellerOrder->status, [SellerOrder::STATUS_PENDING_ACCEPTANCE, SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED], true)
            || $sellerOrder->sla_dispatch_by === null) {
            return null;
        }

        $now ??= Carbon::now();
        $minutes = (int) floor($now->diffInMinutes($sellerOrder->sla_dispatch_by, false));
        $duration = self::duration(abs($minutes));

        if ($minutes < 0) {
            return ['state' => self::BREACHED, 'label' => trans('order::fulfilment.sla.overdue', ['time' => $duration]), 'variant' => 'danger'];
        }

        $atRisk = $minutes <= (int) config('order.fulfilment.sla_at_risk_hours', 12) * 60;

        return [
            'state' => $atRisk ? self::AT_RISK : self::ON_TIME,
            'label' => trans('order::fulfilment.sla.left', ['time' => $duration]),
            'variant' => $atRisk ? 'deal' : 'success',
        ];
    }

    private static function duration(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        return match (true) {
            $days > 0 => $days.'d '.$hours.'h',
            $hours > 0 => $hours.'h '.($minutes % 60).'m',
            default => max(1, $minutes).'m',
        };
    }
}
