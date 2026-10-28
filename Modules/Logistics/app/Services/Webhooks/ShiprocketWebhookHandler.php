<?php

declare(strict_types=1);

namespace Modules\Logistics\Services\Webhooks;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Logistics\Drivers\ShiprocketDriver;
use Modules\Logistics\DTO\TrackingEvent;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\LogisticsChargeService;
use Modules\Logistics\Services\TrackingService;
use Modules\Logistics\Support\Shiprocket\ShiprocketStatusMap;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\WebhookEvent;

/**
 * A stored Shiprocket tracking webhook → TrackingService::apply() (plan M13 §5.4). The
 * shipment is found by AWB, else by our order id (the sub-order number sent as order_id).
 * Returns the webhook row status: processed, or ignored when the shipment is unknown.
 */
class ShiprocketWebhookHandler
{
    public function __construct(
        private readonly TrackingService $tracking,
        private readonly LogisticsChargeService $charges,
    ) {}

    public function handle(WebhookEvent $event): string
    {
        $payload = (array) ($event->payload ?? []);
        $shipment = $this->findShipment($payload);

        if ($shipment === null) {
            Log::channel('integrations')->info('shiprocket webhook for an unknown shipment', ['awb' => $payload['awb'] ?? null, 'order_id' => $payload['order_id'] ?? null]);

            return WebhookEvent::STATUS_IGNORED;
        }

        $this->tracking->apply($shipment, self::events($payload));

        $weight = $payload['charged_weight'] ?? $payload['applied_weight'] ?? null;
        if (is_numeric($weight) && (float) $weight > 0) {
            $shipment->refresh();
            $this->charges->recordChargedWeight($shipment, (int) round((float) $weight * 1000));
            $shipment->save();
        }

        return WebhookEvent::STATUS_PROCESSED;
    }

    /**
     * The scans of a webhook payload: its "scans" list when present, plus the current status.
     *
     * @param  array<string, mixed>  $payload
     * @return list<TrackingEvent>
     */
    public static function events(array $payload): array
    {
        $events = [];

        foreach ((array) ($payload['scans'] ?? []) as $scan) {
            if (is_array($scan) && ($tracking = ShiprocketDriver::eventFromScan($scan)) !== null) {
                $events[] = $tracking;
            }
        }

        $label = ShiprocketStatusMap::label(
            is_string($payload['current_status'] ?? null) ? $payload['current_status'] : (is_string($payload['shipment_status'] ?? null) ? $payload['shipment_status'] : null),
            $payload['current_status_id'] ?? $payload['shipment_status_id'] ?? null,
        );
        $at = $payload['current_timestamp'] ?? null;

        if ($label !== null) {
            $status = ShiprocketStatusMap::map($label);

            if ($status === null) {
                Log::channel('integrations')->warning('shiprocket unknown tracking status', ['label' => $label, 'code' => $payload['current_status_id'] ?? null]);
            }

            $code = $payload['current_status_id'] ?? $payload['shipment_status_id'] ?? '';
            $occurredAt = is_string($at) && trim($at) !== '' ? Carbon::parse($at) : Carbon::now();

            $alreadyListed = array_filter($events, fn (TrackingEvent $scan): bool => $scan->status === $status && $scan->occurredAt->equalTo($occurredAt)) !== [];

            if (! $alreadyListed) {
                $events[] = new TrackingEvent(
                    $status,
                    mb_substr($label, 0, 60),
                    $occurredAt,
                    is_scalar($code) ? mb_substr((string) $code, 0, 20) : '',
                    null,
                    null,
                    array_diff_key($payload, ['scans' => true]),
                );
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findShipment(array $payload): ?Shipment
    {
        $awb = is_scalar($payload['awb'] ?? null) ? trim((string) $payload['awb']) : '';

        if ($awb !== '' && ($shipment = Shipment::query()->where('awb', $awb)->first()) !== null) {
            return $shipment;
        }

        $orderId = is_scalar($payload['order_id'] ?? null) ? trim((string) $payload['order_id']) : '';

        if ($orderId === '') {
            return null;
        }

        $sellerOrderId = SellerOrder::query()->where('sub_order_no', $orderId)->value('id');

        if ($sellerOrderId !== null) {
            return Shipment::query()->forward()->where('seller_order_id', $sellerOrderId)->where('status', '!=', Shipment::STATUS_CANCELLED)->orderByDesc('id')->first();
        }

        $srOrderId = is_scalar($payload['sr_order_id'] ?? null) ? (string) $payload['sr_order_id'] : '';

        return $srOrderId !== '' ? Shipment::query()->where('provider_order_id', $srOrderId)->orderByDesc('id')->first() : null;
    }
}
