<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Models\Shipment;
use Modules\Notification\Services\NotificationHub;
use Throwable;

/**
 * Flags a shipment for Logistics Ops (needs_attention + reason) and alerts the admins whose
 * role grants admin.shipment.index (catalogue event admin.shipment.attention), after commit.
 * A notification failure never breaks the logistics action.
 */
class ShipmentAlerts
{
    public function flag(Shipment $shipment, string $reason): void
    {
        $reason = mb_substr($reason, 0, 255);
        $shipment->forceFill(['needs_attention' => (int) config('core.yes'), 'attention_reason' => $reason])->save();

        $shipmentId = $shipment->id;
        $orderNo = (string) DB::table('seller_order')->where('id', $shipment->seller_order_id)->value('sub_order_no');

        DB::afterCommit(function () use ($shipmentId, $orderNo, $reason): void {
            try {
                app(NotificationHub::class)->eventForAdmins('admin.shipment.attention', [
                    'order_no' => $orderNo,
                    'reason' => $reason,
                    'url' => route('admin.shipment.show', updateUrlParams([$shipmentId])),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function clear(Shipment $shipment): void
    {
        $shipment->needs_attention = (int) config('core.no');
        $shipment->attention_reason = null;
    }
}
