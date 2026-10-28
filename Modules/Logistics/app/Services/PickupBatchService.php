<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Services\SequenceService;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\PickupBatch;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SellerOrder;

/**
 * Pickup batches (FR-FUL-005, plan M14 §5.5): the booked, not yet picked up shipments of packed
 * seller orders (one warehouse, one courier) are scheduled for pickup and manifested with the
 * courier, then grouped under our manifest number. Provider calls run before anything is
 * written, so a courier error leaves no half-made batch. Tracking (M13) moves the shipments on;
 * syncStatus() follows them to picked_up / partially_picked.
 */
class PickupBatchService
{
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly SequenceService $sequences,
    ) {}

    /**
     * @param  list<SellerOrder>  $sellerOrders
     *
     * @throws ShipmentException
     */
    public function create(array $sellerOrders, Carbon $pickupDate, ?Model $actor = null): PickupBatch
    {
        if ($sellerOrders === []) {
            throw new ShipmentException(trans('logistics::shipment.messages.nothing_selected'), 'empty');
        }

        $sellerIds = array_unique(array_map(fn (SellerOrder $sellerOrder): int => $sellerOrder->seller_id, $sellerOrders));
        $warehouseIds = array_unique(array_map(fn (SellerOrder $sellerOrder): ?int => $sellerOrder->warehouse_id, $sellerOrders));

        if (count($sellerIds) !== 1 || count($warehouseIds) !== 1) {
            throw new ShipmentException(trans('logistics::pickup_batch.messages.one_warehouse'), 'mixed_warehouses');
        }

        $shipments = $this->forwardShipments($sellerOrders);

        // Pickup first (it moves the shipments to pickup_scheduled), then the courier's manifest.
        $pickup = $this->shipments->schedulePickup($shipments, $pickupDate, $actor);
        $manifestUrl = $this->shipments->generateManifest($shipments);
        $first = $shipments[0];

        return DB::transaction(function () use ($shipments, $first, $sellerIds, $warehouseIds, $pickupDate, $pickup, $manifestUrl, $actor): PickupBatch {
            $batch = PickupBatch::query()->create([
                'seller_id' => reset($sellerIds),
                'warehouse_id' => reset($warehouseIds),
                'courier_id' => $first->courier_id,
                'carrier_name' => $first->carrier_name,
                'pickup_date' => ($pickup->scheduledAt ?? $pickupDate)->toDateString(),
                'manifest_no' => $this->sequences->next('manifest:'.Carbon::now()->format('ym'), 'MAN-'.Carbon::now()->format('ym').'-'),
                'manifest_url' => $manifestUrl,
                'pickup_reference' => $pickup->reference !== null ? mb_substr($pickup->reference, 0, 60) : null,
                'status' => $pickup->scheduled ? PickupBatch::STATUS_MANIFESTED : PickupBatch::STATUS_OPEN,
                'shipments_count' => count($shipments),
                'created_by' => $actor?->getKey(),
            ]);

            Shipment::query()
                ->whereKey(array_map(fn (Shipment $shipment): int => $shipment->id, $shipments))
                ->update(['pickup_batch_id' => $batch->id, 'updated_at' => Carbon::now()]);

            return $batch;
        });
    }

    /**
     * Follow the batch's shipments: all handed over → picked_up, some → partially_picked.
     */
    public function syncStatus(PickupBatch $batch): void
    {
        $statuses = Shipment::query()->where('pickup_batch_id', $batch->id)->where('status', '!=', Shipment::STATUS_CANCELLED)->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        $picked = $statuses->filter(fn (string $status): bool => ! in_array($status, Shipment::PRE_PICKUP_STATUSES, true))->count();
        $status = match (true) {
            $picked === 0 => $batch->status,
            $picked === $statuses->count() => PickupBatch::STATUS_PICKED_UP,
            default => PickupBatch::STATUS_PARTIALLY_PICKED,
        };

        if ($status !== $batch->status) {
            $batch->forceFill(['status' => $status])->save();
        }
    }

    /**
     * The open forward shipment of each seller order: booked, still with the seller and not in
     * another batch.
     *
     * @param  list<SellerOrder>  $sellerOrders
     * @return list<Shipment>
     *
     * @throws ShipmentException
     */
    protected function forwardShipments(array $sellerOrders): array
    {
        $byOrder = Shipment::query()
            ->forward()
            ->whereIn('seller_order_id', array_map(fn (SellerOrder $sellerOrder): int => $sellerOrder->id, $sellerOrders))
            ->where('status', '!=', Shipment::STATUS_CANCELLED)
            ->orderBy('id')
            ->get()
            ->keyBy('seller_order_id');

        $shipments = [];

        foreach ($sellerOrders as $sellerOrder) {
            $shipment = $byOrder->get($sellerOrder->id);

            if ($shipment === null || ! $shipment->isBooked()) {
                throw new ShipmentException(trans('logistics::pickup_batch.messages.not_booked', ['order' => $sellerOrder->sub_order_no]), 'not_booked');
            }

            if (! $shipment->isPrePickup() || $shipment->pickup_batch_id !== null) {
                throw new ShipmentException(trans('logistics::pickup_batch.messages.already_batched', ['order' => $sellerOrder->sub_order_no]), 'already_batched');
            }

            $shipments[] = $shipment;
        }

        return $shipments;
    }
}
