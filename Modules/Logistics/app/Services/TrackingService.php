<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Logistics\DTO\TrackingEvent;
use Modules\Logistics\Events\ShipmentDelivered;
use Modules\Logistics\Events\ShipmentRtoDelivered;
use Modules\Logistics\Events\ShipmentRtoInitiated;
use Modules\Logistics\Events\ShipmentStatusChanged;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\CourierDriverManager;
use Modules\Logistics\Support\SellerOrderPath;
use Modules\Logistics\Support\ShipmentAlerts;
use Modules\Logistics\Support\TimelineBuilder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderStatusService;
use Modules\Product\Services\InventoryService;
use Throwable;

/**
 * Courier scans → shipment status → seller-order status (FR-LOG-006, plan M13 §5.4).
 *
 * apply() runs in one transaction with the shipment row locked: new scans are inserted
 * (duplicates dropped by the unique key), the status only moves forward (Shipment::canAdvanceTo),
 * and each forward shipment change walks its seller order through OrderStatusService:
 * picked_up/in_transit → shipped (+ InventoryService::commit), out_for_delivery, ndr
 * (+ an NDR case), delivered (+ return window), rto_initiated, rto_delivered. Events fire
 * after commit.
 */
class TrackingService
{
    public function __construct(
        private readonly OrderStatusService $orderStatus,
        private readonly InventoryService $inventory,
        private readonly NdrService $ndr,
        private readonly LogisticsChargeService $charges,
        private readonly CourierDriverManager $drivers,
    ) {}

    /**
     * Record $events for the shipment and apply the latest status they reach.
     *
     * @param  list<TrackingEvent>  $events
     * @return int the number of new scans
     */
    public function apply(Shipment $shipment, array $events, ?Model $actor = null): int
    {
        usort($events, fn (TrackingEvent $a, TrackingEvent $b): int => $a->occurredAt <=> $b->occurredAt);

        $inserted = DB::transaction(function () use ($shipment, $events, $actor): int {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $inserted = 0;
            $newNdrScans = [];
            $changes = [];

            foreach ($events as $event) {
                if (! $this->insertScan($locked, $event)) {
                    continue;
                }

                $inserted++;
                $locked->last_status_label = $event->providerStatus;
                $locked->last_event_at = $locked->last_event_at === null || $event->occurredAt->greaterThan($locked->last_event_at)
                    ? $event->occurredAt
                    : $locked->last_event_at;

                if ($event->status === null) {
                    Log::channel('integrations')->warning('shipment scan without a mapped status', ['shipment' => $locked->id, 'status' => $event->providerStatus]);

                    continue;
                }

                if ($event->status === Shipment::STATUS_NDR) {
                    $locked->attempts = min(255, $locked->attempts + 1);
                    $newNdrScans[] = $event;
                }

                if ($locked->canAdvanceTo($event->status)) {
                    $changes[] = [$locked->status, $event->status];
                    $this->stamp($locked, $event->status, $event->occurredAt);
                    $locked->status = $event->status;
                }
            }

            $locked->save();

            foreach ($changes as [$from, $to]) {
                $this->afterChange($locked, $from, $to, $actor);
            }

            if ($newNdrScans !== [] && $locked->status === Shipment::STATUS_NDR) {
                $last = $newNdrScans[count($newNdrScans) - 1];
                $this->ndr->raise($locked, $last->remarks ?? $last->providerStatus, $locked->attempts);
            }

            return $inserted;
        });

        $shipment->refresh();

        return $inserted;
    }

    /**
     * Move the shipment to $to without a courier scan (booking, label, pickup); the change
     * still goes through the forward-only rule and the seller-order mapping.
     */
    public function moveTo(Shipment $shipment, string $to, ?Model $actor = null): bool
    {
        $moved = DB::transaction(function () use ($shipment, $to, $actor): bool {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if (! $locked->canAdvanceTo($to)) {
                return false;
            }

            $from = $locked->status;
            $this->stamp($locked, $to, Carbon::now());
            $locked->status = $to;
            $locked->save();
            $this->afterChange($locked, $from, $to, $actor);

            return true;
        });

        $shipment->refresh();

        return $moved;
    }

    /**
     * Poll the courier for scans (fallback for missed webhooks). Courier errors are reported;
     * last_synced_at moves on either way, so a broken shipment is not polled every run.
     */
    public function sync(Shipment $shipment): int
    {
        $shipment->loadMissing('courier');

        try {
            $events = $shipment->courier !== null && $shipment->isBooked() ? $this->drivers->for($shipment->courier)->track($shipment) : [];
            $inserted = $this->apply($shipment, $events);
        } catch (CourierException $e) {
            report($e);
            $inserted = 0;
        }

        $shipment->forceFill(['last_synced_at' => Carbon::now()])->save();

        return $inserted;
    }

    /**
     * Logistics Ops record a scan for a manual-courier shipment (plan M13 §7).
     *
     * @throws ShipmentException
     */
    public function recordManualStatus(Shipment $shipment, string $status, ?string $location, ?string $remarks, ?Carbon $occurredAt = null, ?Model $actor = null): bool
    {
        $shipment->loadMissing('courier');

        if ($shipment->courier === null || ! $shipment->courier->isManual()) {
            throw new ShipmentException(trans('logistics::shipment.messages.manual_only'), 'not_manual');
        }

        if (! $shipment->isBooked()) {
            throw new ShipmentException(trans('logistics::shipment.messages.awb_required'), 'not_booked');
        }

        if (! $shipment->canAdvanceTo($status)) {
            throw new ShipmentException(trans('logistics::shipment.messages.status_not_allowed', ['from' => $shipment->status, 'to' => $status]), 'invalid_transition');
        }

        $this->apply($shipment, [new TrackingEvent(
            $status,
            mb_substr(strtoupper(str_replace('_', ' ', $status)), 0, 60),
            $occurredAt ?? Carbon::now(),
            'manual',
            $location !== null ? mb_substr($location, 0, 120) : null,
            $remarks !== null ? mb_substr($remarks, 0, 255) : null,
            ['source' => 'manual', 'actor_id' => $actor?->getKey()],
        )], $actor);

        return true;
    }

    /**
     * The customer timeline of a seller order (plan M13 §5.5), see TimelineBuilder.
     *
     * @return array<string, mixed>
     */
    public function timeline(SellerOrder $sellerOrder): array
    {
        return app(TimelineBuilder::class)->build($sellerOrder);
    }

    protected function insertScan(Shipment $shipment, TrackingEvent $event): bool
    {
        return DB::table('shipment_event')->insertOrIgnore([
            'shipment_id' => $shipment->id,
            'status' => $event->status,
            'provider_status' => mb_substr($event->providerStatus, 0, 60),
            'provider_status_code' => mb_substr($event->providerStatusCode, 0, 20),
            'location' => $event->location,
            'remarks' => $event->remarks,
            'occurred_at' => $event->occurredAt,
            'raw' => $event->raw === [] ? null : json_encode($event->raw),
            'created_at' => Carbon::now(),
        ]) > 0;
    }

    /**
     * Timestamps of the shipment for the status it enters.
     */
    protected function stamp(Shipment $shipment, string $to, Carbon $at): void
    {
        if (in_array($to, [Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_OUT_FOR_DELIVERY, Shipment::STATUS_NDR, Shipment::STATUS_DELIVERED], true) && $shipment->picked_up_at === null) {
            $shipment->picked_up_at = $at;
        }

        if ($to === Shipment::STATUS_PICKUP_SCHEDULED && $shipment->pickup_scheduled_at === null) {
            $shipment->pickup_scheduled_at = $at;
        }

        if ($to === Shipment::STATUS_DELIVERED) {
            $shipment->delivered_at = $at;
        }

        if ($to === Shipment::STATUS_RTO_DELIVERED) {
            $shipment->rto_delivered_at = $at;
        }
    }

    /**
     * Side effects of one shipment change, inside the caller's transaction.
     */
    protected function afterChange(Shipment $shipment, string $from, string $to, ?Model $actor): void
    {
        if (in_array($to, [Shipment::STATUS_DELIVERED, Shipment::STATUS_RTO_INITIATED, Shipment::STATUS_RTO_IN_TRANSIT, Shipment::STATUS_RTO_DELIVERED, Shipment::STATUS_LOST, Shipment::STATUS_CANCELLED], true)) {
            NdrCase::query()->where('shipment_id', $shipment->id)->open()->update(['status' => NdrCase::STATUS_CLOSED, 'updated_at' => Carbon::now()]);
        }

        $enteredRto = in_array($to, Shipment::RTO_STATUSES, true) && ! in_array($from, Shipment::RTO_STATUSES, true);

        if ($enteredRto) {
            $this->charges->recordRto($shipment);
            $shipment->save();
        }

        if ($shipment->direction === Shipment::DIRECTION_FORWARD) {
            $this->moveSellerOrder($shipment, $to, $actor);
        }

        ShipmentStatusChanged::dispatch($shipment, $from, $to);

        if ($to === Shipment::STATUS_DELIVERED) {
            ShipmentDelivered::dispatch($shipment);
        }

        if ($enteredRto) {
            ShipmentRtoInitiated::dispatch($shipment);
        }

        if ($to === Shipment::STATUS_RTO_DELIVERED) {
            ShipmentRtoDelivered::dispatch($shipment);
        }

        if ($to === Shipment::STATUS_LOST) {
            app(ShipmentAlerts::class)->flag($shipment, trans('logistics::shipment.attention.lost'));
        }
    }

    /**
     * Walk the seller order to the status matching the shipment's (plan M13 §5.4). Seller
     * orders that cannot get there (cancelled, already further) are left alone and logged.
     */
    protected function moveSellerOrder(Shipment $shipment, string $shipmentStatus, ?Model $actor): void
    {
        $target = SellerOrderPath::targetFor($shipmentStatus);

        if ($target === null) {
            return;
        }

        $sellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($shipment->seller_order_id);
        $steps = SellerOrderPath::between($sellerOrder->status, $target);

        if ($steps === null) {
            Log::warning('seller order cannot follow its shipment', ['seller_order' => $sellerOrder->id, 'from' => $sellerOrder->status, 'to' => $target, 'shipment' => $shipment->id]);

            return;
        }

        $meta = array_filter(['awb' => $shipment->awb, 'carrier' => $shipment->carrier_name]);

        foreach ($steps as $step) {
            $this->orderStatus->transition($sellerOrder, $step, trans('logistics::shipment.history.'.$step), $actor, $actor === null ? 'courier' : null, $meta);

            if ($step === SellerOrder::STATUS_SHIPPED) {
                $this->commitStock($sellerOrder);
            }
        }

        if ($target === SellerOrder::STATUS_DELIVERED) {
            $this->setReturnWindow($sellerOrder->refresh());
        }
    }

    /**
     * The goods left the warehouse: reserved → sold. A stock mismatch must not block the
     * tracking update, so it is reported and rolled back on its own savepoint.
     */
    protected function commitStock(SellerOrder $sellerOrder): void
    {
        $lines = SalesOrderItem::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->where('status', '!=', SalesOrderItem::STATUS_CANCELLED)
            ->orderBy('variant_id')
            ->get(['variant_id', 'quantity', 'cancelled_qty'])
            ->map(fn (SalesOrderItem $item): array => ['variant_id' => $item->variant_id, 'qty' => $item->quantity - $item->cancelled_qty])
            ->filter(fn (array $line): bool => $line['qty'] > 0)
            ->values()
            ->all();

        if ($lines === []) {
            return;
        }

        try {
            DB::transaction(fn () => $this->inventory->commit($lines, $sellerOrder, null, 'shipped'));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * return_window_ends_at = delivered_at + the longest item return window (days).
     */
    protected function setReturnWindow(SellerOrder $sellerOrder): void
    {
        if ($sellerOrder->return_window_ends_at !== null || $sellerOrder->delivered_at === null) {
            return;
        }

        $days = (int) SalesOrderItem::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->where('status', '!=', SalesOrderItem::STATUS_CANCELLED)
            ->max('return_window_days');

        SellerOrder::query()->whereKey($sellerOrder->id)->update([
            'return_window_ends_at' => $sellerOrder->delivered_at->copy()->addDays(max(0, $days)),
            'updated_at' => Carbon::now(),
        ]);
    }
}
