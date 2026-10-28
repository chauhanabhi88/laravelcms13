<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\PickupBatchService;
use Modules\Logistics\Services\RtoService;
use Modules\Logistics\Services\ShipmentService;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\DTO\BookingOutcome;
use Modules\Order\DTO\ManifestOutcome;
use Modules\Order\DTO\ProviderLabel;
use Modules\Order\DTO\ShipmentSummary;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SellerOrder;

/**
 * The Order module's FulfilmentLogistics (plan M14): the supplier order desk books, labels,
 * manifests and confirms RTOs through the shipment services. Courier and shipment errors
 * reach the desk as OrderException (with the shipment error's code).
 */
class LogisticsFulfilment implements FulfilmentLogistics
{
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly PickupBatchService $batches,
        private readonly RtoService $rto,
        private readonly TimelineBuilder $timeline,
    ) {}

    public function book(SellerOrder $sellerOrder, ?Model $actor = null): BookingOutcome
    {
        try {
            $shipment = $this->forward($sellerOrder) ?? $this->shipments->plan($sellerOrder);

            if (! $shipment->isBooked()) {
                $shipment = $this->shipments->book($shipment, $actor);
            }
        } catch (CourierException $e) {
            return BookingOutcome::pending($e->getMessage());
        } catch (ShipmentException $e) {
            return $e->reasonCode === 'busy' ? BookingOutcome::pending($e->getMessage()) : BookingOutcome::refused($e->getMessage());
        }

        if (! $shipment->isBooked()) {
            return BookingOutcome::pending((string) ($shipment->attention_reason ?: trans('logistics::shipment.attention.awb_pending')));
        }

        return BookingOutcome::booked((string) $shipment->awb, $shipment->carrier_name);
    }

    public function shipments(array $sellerOrderIds): array
    {
        if ($sellerOrderIds === []) {
            return [];
        }

        return Shipment::query()
            ->forward()
            ->whereIn('seller_order_id', $sellerOrderIds)
            ->with('pickupBatch')
            ->orderByRaw('status = ? desc', [Shipment::STATUS_CANCELLED])
            ->orderBy('id')
            ->get()
            ->keyBy('seller_order_id')
            ->map(fn (Shipment $shipment): ShipmentSummary => new ShipmentSummary(
                $shipment->seller_order_id,
                $shipment->id,
                $shipment->status,
                LogisticsOptions::shipmentStatus($shipment->status),
                $shipment->awb,
                $shipment->courier_id,
                $shipment->carrier_name,
                $shipment->tracking_url,
                $shipment->label_url,
                $shipment->pickup_batch_id,
                $shipment->pickupBatch?->manifest_no,
                $shipment->needsAttention(),
                $shipment->attention_reason,
                $shipment->pickup_scheduled_at,
                $shipment->edd,
            ))
            ->all();
    }

    public function labels(array $sellerOrders, ?Model $actor = null): array
    {
        $shipments = [];

        foreach ($sellerOrders as $sellerOrder) {
            $shipment = $this->forward($sellerOrder);

            if ($shipment === null || ! $shipment->isBooked()) {
                throw new OrderException(trans('order::fulfilment.errors.awb_required', ['order' => $sellerOrder->sub_order_no]), 'not_booked');
            }

            $shipments[] = $shipment->loadMissing('courier');
        }

        $labels = [];

        foreach (collect($shipments)->groupBy('courier_id') as $group) {
            /** @var list<Shipment> $list */
            $list = $group->values()->all();

            try {
                $url = $this->shipments->generateLabels($list, $actor);
            } catch (ShipmentException|CourierException $e) {
                throw new OrderException($e->getMessage(), $e instanceof ShipmentException ? $e->reasonCode : 'courier_error');
            }

            $labels[] = new ProviderLabel(
                (string) ($list[0]->courier?->name ?? $list[0]->carrier_name ?? ''),
                $url,
                array_map(fn (Shipment $shipment): int => $shipment->seller_order_id, $list),
            );
        }

        return $labels;
    }

    public function createManifest(array $sellerOrders, Carbon $pickupDate, ?Model $actor = null): ManifestOutcome
    {
        try {
            $batch = $this->batches->create($sellerOrders, $pickupDate, $actor);
        } catch (ShipmentException|CourierException $e) {
            throw new OrderException($e->getMessage(), $e instanceof ShipmentException ? $e->reasonCode : 'courier_error');
        }

        return new ManifestOutcome(
            $batch->id,
            $batch->manifest_no,
            $batch->shipments_count,
            $batch->manifest_url,
            $batch->status !== $batch::STATUS_OPEN,
            $batch->status === $batch::STATUS_OPEN ? trans('logistics::pickup_batch.messages.pickup_not_scheduled') : null,
        );
    }

    public function confirmRtoReceipt(SellerOrder $sellerOrder, bool $restock, ?Model $actor = null): void
    {
        $shipment = Shipment::query()
            ->forward()
            ->where('seller_order_id', $sellerOrder->id)
            ->where('status', Shipment::STATUS_RTO_DELIVERED)
            ->orderByDesc('id')
            ->first();

        if ($shipment === null) {
            throw new OrderException(trans('logistics::shipment.messages.rto_not_receivable'), 'invalid_state');
        }

        try {
            $this->rto->confirmReceipt($shipment, $restock, $actor);
        } catch (ShipmentException $e) {
            throw new OrderException($e->getMessage(), $e->reasonCode);
        }
    }

    public function timeline(SellerOrder $sellerOrder): array
    {
        return $this->timeline->build($sellerOrder)['events'];
    }

    /**
     * The seller order's live forward shipment (not cancelled), latest first.
     */
    protected function forward(SellerOrder $sellerOrder): ?Shipment
    {
        return Shipment::query()
            ->forward()
            ->where('seller_order_id', $sellerOrder->id)
            ->where('status', '!=', Shipment::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->first();
    }
}
