<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Logistics\DTO\AwbResult;
use Modules\Logistics\DTO\CarrierQuote;
use Modules\Logistics\DTO\PickupResult;
use Modules\Logistics\Events\ShipmentStatusChanged;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\CourierDriverManager;
use Modules\Logistics\Support\ShipmentAlerts;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderStatusService;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\SellerWarehouse;
use Throwable;

/**
 * Shipment lifecycle on our side (plan M13 §5.1):
 * - plan(): when a seller order is confirmed, one forward shipment row (package weight and
 *   dimensions from the variants, payment mode, COD amount) with its items. No provider call.
 * - book(): when the seller accepts (M14), the courier rule picks the courier/carrier, the
 *   provider order is created and the AWB assigned; the seller order moves to ready_to_ship.
 * - labels, manifests and pickups for M14; cancel(); the manual AWB entry for Logistics Ops.
 * Provider calls never run while rows are locked; a per-shipment cache lock stops double booking.
 */
class ShipmentService
{
    /**
     * Seller-order statuses in which a shipment may be booked.
     */
    public const BOOKABLE_ORDER_STATUSES = [SellerOrder::STATUS_PENDING_ACCEPTANCE, SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED];

    public function __construct(
        private readonly CourierAssignmentService $assignment,
        private readonly CourierDriverManager $drivers,
        private readonly TrackingService $tracking,
        private readonly LogisticsChargeService $charges,
        private readonly OrderStatusService $orderStatus,
        private readonly ShipmentAlerts $alerts,
    ) {}

    /**
     * The forward shipment of a confirmed seller order (idempotent: an existing, not cancelled
     * one is returned).
     */
    public function plan(SellerOrder $sellerOrder): Shipment
    {
        return DB::transaction(function () use ($sellerOrder): Shipment {
            $locked = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);

            $existing = Shipment::query()
                ->forward()
                ->where('seller_order_id', $locked->id)
                ->where('status', '!=', Shipment::STATUS_CANCELLED)
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $items = SalesOrderItem::query()
                ->where('seller_order_id', $locked->id)
                ->where('status', '!=', SalesOrderItem::STATUS_CANCELLED)
                ->orderBy('id')
                ->get();

            $order = SalesOrder::query()->findOrFail($locked->sales_order_id);
            $package = $this->package($items);
            $warehouse = $locked->warehouse_id !== null ? SellerWarehouse::query()->find($locked->warehouse_id) : null;
            // A package with nothing to collect (an exchange's replacement, M15) ships prepaid.
            $cod = $order->isCod() ? $this->codAmount($order, $locked, $items) : Money::zero();

            $shipment = Shipment::query()->create([
                'seller_order_id' => $locked->id,
                'direction' => Shipment::DIRECTION_FORWARD,
                'status' => Shipment::STATUS_CREATED,
                'payment_mode' => $cod->isPositive() ? Shipment::PAYMENT_COD : Shipment::PAYMENT_PREPAID,
                'cod_amount' => $cod,
                'weight_grams' => $package['weight_grams'],
                'length_cm' => $package['length_cm'],
                'breadth_cm' => $package['breadth_cm'],
                'height_cm' => $package['height_cm'],
                'pickup_location_code' => $warehouse?->courier_pickup_ref,
                'needs_attention' => (int) config('core.no'),
            ]);

            DB::table('shipment_item')->insert($items->map(fn (SalesOrderItem $item): array => [
                'shipment_id' => $shipment->id,
                'sales_order_item_id' => $item->id,
                'quantity' => max(0, $item->quantity - $item->cancelled_qty),
            ])->filter(fn (array $row): bool => $row['quantity'] > 0)->values()->all());

            return $shipment;
        });
    }

    /**
     * Book the shipment with the courier chosen by the rules and assign its AWB. Nothing
     * serviceable or a courier error flags the shipment for Logistics Ops (the courier error
     * is rethrown). The manual driver stops after the provider ids and waits for the AWB.
     *
     * @throws ShipmentException|CourierException
     */
    public function book(Shipment $shipment, ?Model $actor = null): Shipment
    {
        $lock = Cache::lock('logistics:book:'.$shipment->id, 120);

        if (! $lock->get()) {
            throw new ShipmentException(trans('logistics::shipment.messages.busy'), 'busy');
        }

        try {
            return $this->bookLocked($shipment->refresh(), $actor);
        } finally {
            $lock->release();
        }
    }

    /**
     * Logistics Ops enter the AWB of a manual-courier shipment (plan M13 §6 manual_awb).
     *
     * @throws ShipmentException
     */
    public function recordManualAwb(Shipment $shipment, string $awb, ?string $carrierName, ?string $trackingUrl, ?Model $actor = null): Shipment
    {
        $manual = Courier::query()->where('driver', Courier::DRIVER_MANUAL)->active()->orderBy('priority')->first();

        if ($manual === null) {
            throw new ShipmentException(trans('logistics::shipment.messages.no_manual_courier'), 'no_manual_courier');
        }

        DB::transaction(function () use ($shipment, $awb, $carrierName, $trackingUrl, $manual): void {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if ($locked->status !== Shipment::STATUS_CREATED) {
                throw new ShipmentException(trans('logistics::shipment.messages.already_booked'), 'already_booked');
            }

            if ($locked->courier_id !== null && $locked->courier_id !== $manual->id && $locked->provider_order_id !== null) {
                throw new ShipmentException(trans('logistics::shipment.messages.manual_only'), 'not_manual');
            }

            $locked->forceFill([
                'courier_id' => $manual->id,
                'awb' => trim($awb),
                'carrier_name' => $carrierName !== null && trim($carrierName) !== '' ? trim($carrierName) : $manual->name,
                'carrier_id' => null,
                'tracking_url' => $trackingUrl,
                'provider_order_id' => $locked->provider_order_id ?? 'MAN-'.$locked->id,
                'provider_shipment_id' => $locked->provider_shipment_id ?? 'MAN-'.$locked->id,
            ]);
            $this->alerts->clear($locked);
            $locked->save();
        });

        $this->tracking->moveTo($shipment, Shipment::STATUS_AWB_ASSIGNED, $actor);
        $this->acceptSellerOrder($shipment->refresh(), $actor);

        return $shipment->refresh();
    }

    /**
     * Cancel a shipment that has not been picked up (with the courier when booked). The
     * seller order is not touched: order cancellations go through OrderCancellationService.
     *
     * @throws ShipmentException|CourierException
     */
    public function cancel(Shipment $shipment, ?string $reason = null, ?Model $actor = null): Shipment
    {
        $shipment->refresh()->loadMissing('courier');

        if ($shipment->status === Shipment::STATUS_CANCELLED) {
            return $shipment;
        }

        if (! $shipment->isPrePickup()) {
            throw new ShipmentException(trans('logistics::shipment.messages.cannot_cancel'), 'picked_up');
        }

        if ($shipment->courier !== null && $shipment->provider_order_id !== null) {
            $this->drivers->for($shipment->courier)->cancel($shipment);
        }

        $from = $shipment->status;

        DB::transaction(function () use ($shipment, $from, $reason): void {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $locked->forceFill(['status' => Shipment::STATUS_CANCELLED, 'attention_reason' => $reason !== null ? mb_substr($reason, 0, 255) : $locked->attention_reason])->save();

            ShipmentStatusChanged::dispatch($locked, $from, Shipment::STATUS_CANCELLED);
        });

        return $shipment->refresh();
    }

    /**
     * Label PDF for booked shipments of one courier (M14 batches by courier).
     *
     * @param  list<Shipment>  $shipments
     *
     * @throws ShipmentException|CourierException
     */
    public function generateLabels(array $shipments, ?Model $actor = null): ?string
    {
        $courier = $this->sameCourier($shipments);
        $url = $this->drivers->for($courier)->generateLabel($shipments);

        foreach ($shipments as $shipment) {
            if ($url !== null) {
                $shipment->forceFill(['label_url' => $url])->save();
            }

            $this->tracking->moveTo($shipment, Shipment::STATUS_LABEL_GENERATED, $actor);
        }

        return $url;
    }

    /**
     * @param  list<Shipment>  $shipments
     *
     * @throws ShipmentException|CourierException
     */
    public function generateManifest(array $shipments): ?string
    {
        $courier = $this->sameCourier($shipments);
        $url = $this->drivers->for($courier)->generateManifest($shipments);

        if ($url !== null) {
            Shipment::query()->whereKey(array_map(fn (Shipment $shipment): int => $shipment->id, $shipments))->update(['manifest_url' => $url, 'updated_at' => Carbon::now()]);
        }

        return $url;
    }

    /**
     * @param  list<Shipment>  $shipments
     *
     * @throws ShipmentException|CourierException
     */
    public function schedulePickup(array $shipments, Carbon $date, ?Model $actor = null): PickupResult
    {
        $courier = $this->sameCourier($shipments);
        $result = $this->drivers->for($courier)->schedulePickup($shipments, $date);

        if ($result->scheduled) {
            foreach ($shipments as $shipment) {
                $this->tracking->moveTo($shipment, Shipment::STATUS_PICKUP_SCHEDULED, $actor);
                $shipment->forceFill(['pickup_scheduled_at' => $result->scheduledAt ?? $date])->save();
            }
        }

        return $result;
    }

    /**
     * A reverse shipment (customer → seller) for returned items (M15), booked with the forward
     * shipment's courier, or the first active courier that supports reverse pickups.
     * $pickupAddress (name, phone, address_line1/2, landmark, pincode, city_name, state_name)
     * replaces the order's shipping address as the collection point.
     *
     * @param  array<int, int>  $quantities  sales_order_item_id => quantity
     * @param  array<string, mixed>|null  $pickupAddress
     *
     * @throws ShipmentException|CourierException
     */
    public function createReverse(SellerOrder $sellerOrder, array $quantities, ?int $returnRequestId = null, ?array $pickupAddress = null): Shipment
    {
        $forward = Shipment::query()->forward()->where('seller_order_id', $sellerOrder->id)->with('courier')->orderByDesc('id')->first();
        $courier = $forward?->courier !== null && $forward->courier->supportsReverse() && $forward->courier->isActive()
            ? $forward->courier
            : Courier::query()->active()->where('supports_reverse', config('core.yes'))->orderBy('priority')->first();

        if ($courier === null) {
            throw new ShipmentException(trans('logistics::shipment.messages.no_reverse_courier'), 'no_courier');
        }

        $items = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->whereKey(array_keys($quantities))->get();
        $package = $this->package($items, $quantities);

        $reverse = DB::transaction(function () use ($sellerOrder, $courier, $items, $quantities, $package, $returnRequestId, $pickupAddress): Shipment {
            $reverse = Shipment::query()->create([
                'seller_order_id' => $sellerOrder->id,
                'direction' => Shipment::DIRECTION_REVERSE,
                'return_request_id' => $returnRequestId,
                'courier_id' => $courier->id,
                'status' => Shipment::STATUS_CREATED,
                'payment_mode' => Shipment::PAYMENT_PREPAID,
                'cod_amount' => Money::zero(),
                'pickup_address' => $pickupAddress === [] ? null : $pickupAddress,
                'needs_attention' => (int) config('core.no'),
            ] + $package);

            DB::table('shipment_item')->insert($items->map(fn (SalesOrderItem $item): array => [
                'shipment_id' => $reverse->id,
                'sales_order_item_id' => $item->id,
                'quantity' => (int) $quantities[$item->id],
            ])->values()->all());

            return $reverse;
        });

        $provider = $this->drivers->for($courier)->createReturn($reverse);
        $reverse->forceFill(['provider_order_id' => $provider->orderId, 'provider_shipment_id' => $provider->shipmentId])->save();

        return $reverse;
    }

    /**
     * Assign the reverse shipment's AWB and ask for the pickup (M15). The manual driver leaves
     * the AWB to Logistics Ops (the shipment is flagged); a courier error flags it and is
     * rethrown. A refused pickup request is only reported: the courier's own scheduling or Ops
     * follow up.
     *
     * @throws ShipmentException|CourierException
     */
    public function bookReverse(Shipment $reverse, ?Model $actor = null): Shipment
    {
        $reverse->refresh()->loadMissing('courier');

        if ($reverse->direction !== Shipment::DIRECTION_REVERSE || $reverse->courier === null) {
            throw new ShipmentException(trans('logistics::shipment.messages.no_reverse_courier'), 'no_courier');
        }

        if ($reverse->isBooked() || $reverse->status !== Shipment::STATUS_CREATED) {
            return $reverse;
        }

        $driver = $this->drivers->for($reverse->courier);

        try {
            $awb = $driver->assignAwb($reverse, null);
        } catch (CourierException $e) {
            $this->alerts->flag($reverse, trans('logistics::shipment.attention.courier_error', ['error' => $e->getMessage()]));

            throw $e;
        }

        if (! $awb->assigned()) {
            $this->alerts->flag($reverse, trans('logistics::shipment.attention.awb_pending'));

            return $reverse->refresh();
        }

        $this->saveAwb($reverse, $awb, null);
        $this->tracking->moveTo($reverse, Shipment::STATUS_AWB_ASSIGNED, $actor);

        try {
            $date = Carbon::tomorrow();
            $result = $driver->schedulePickup([$reverse->refresh()], $date->isSunday() ? $date->addDay() : $date);

            if ($result->scheduled) {
                $this->tracking->moveTo($reverse, Shipment::STATUS_PICKUP_SCHEDULED, $actor);
                $reverse->forceFill(['pickup_scheduled_at' => $result->scheduledAt ?? $date])->save();
            }
        } catch (CourierException $e) {
            report($e);
        }

        return $reverse->refresh();
    }

    /**
     * @throws ShipmentException|CourierException
     */
    protected function bookLocked(Shipment $shipment, ?Model $actor): Shipment
    {
        if ($shipment->direction !== Shipment::DIRECTION_FORWARD || $shipment->status !== Shipment::STATUS_CREATED || $shipment->isBooked()) {
            throw new ShipmentException(trans('logistics::shipment.messages.already_booked'), 'already_booked');
        }

        $sellerOrder = SellerOrder::query()->with('warehouse')->findOrFail($shipment->seller_order_id);

        if (! in_array($sellerOrder->status, self::BOOKABLE_ORDER_STATUSES, true)) {
            throw new ShipmentException(trans('logistics::shipment.messages.order_not_bookable', ['status' => $sellerOrder->status]), 'order_not_bookable');
        }

        $assignment = $this->assignment->assign($shipment);

        if ($assignment === null) {
            $this->alerts->flag($shipment, trans('logistics::shipment.attention.not_serviceable'));

            return $shipment->refresh();
        }

        $driver = $this->drivers->for($assignment->courier);

        try {
            if ($shipment->pickup_location_code === null && $sellerOrder->warehouse !== null) {
                $code = $sellerOrder->warehouse->courier_pickup_ref ?: $driver->syncPickupLocation($sellerOrder->warehouse);
                SellerWarehouse::query()->whereKey($sellerOrder->warehouse->id)->update(['courier_pickup_ref' => $code]);
                $shipment->forceFill(['pickup_location_code' => $code])->save();
            }

            if ($shipment->provider_order_id === null || $shipment->courier_id !== $assignment->courier->id) {
                $provider = $driver->createShipment($shipment);
                $shipment->forceFill([
                    'courier_id' => $assignment->courier->id,
                    'courier_rule_id' => $assignment->rule?->id,
                    'strategy' => $assignment->strategy,
                    'provider_order_id' => $provider->orderId,
                    'provider_shipment_id' => $provider->shipmentId,
                ])->save();
            }

            $awb = $driver->assignAwb($shipment, $assignment->quote?->id);
        } catch (CourierException $e) {
            $this->alerts->flag($shipment, trans('logistics::shipment.attention.courier_error', ['error' => $e->getMessage()]));

            throw $e;
        }

        if (! $awb->assigned()) {
            $this->alerts->flag($shipment, trans('logistics::shipment.attention.awb_pending'));

            return $shipment->refresh();
        }

        $this->saveAwb($shipment, $awb, $assignment->quote);
        $this->tracking->moveTo($shipment, Shipment::STATUS_AWB_ASSIGNED, $actor);
        $this->acceptSellerOrder($shipment->refresh(), $actor);

        return $shipment->refresh();
    }

    protected function saveAwb(Shipment $shipment, AwbResult $awb, ?CarrierQuote $quote): void
    {
        DB::transaction(function () use ($shipment, $awb, $quote): void {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $locked->forceFill([
                'awb' => $awb->awb,
                'carrier_id' => $awb->carrierId ?? $quote?->id,
                'carrier_name' => $awb->carrierName ?? $quote?->name,
                'tracking_url' => $awb->trackingUrl ?? $locked->tracking_url,
                'edd' => $awb->edd ?? ($quote?->etaDays !== null ? Carbon::today()->addDays($quote->etaDays) : $locked->edd),
            ]);
            $this->charges->recordBooking($locked, $quote, $awb);
            $this->alerts->clear($locked);
            $locked->save();
        });

        $shipment->refresh();
    }

    /**
     * Booking a shipment is the seller's acceptance: pending_acceptance → ready_to_ship.
     */
    protected function acceptSellerOrder(Shipment $shipment, ?Model $actor): void
    {
        $sellerOrder = SellerOrder::query()->findOrFail($shipment->seller_order_id);

        if ($sellerOrder->status !== SellerOrder::STATUS_PENDING_ACCEPTANCE) {
            return;
        }

        try {
            $this->orderStatus->transition($sellerOrder, SellerOrder::STATUS_READY_TO_SHIP, trans('logistics::shipment.history.awb_assigned'), $actor, $actor === null ? 'system' : null, ['awb' => $shipment->awb]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Sum of variant weight × qty (at least the configured minimum) and the largest
     * dimensions (one package; plan M13 §3.3).
     *
     * @param  iterable<SalesOrderItem>  $items
     * @param  array<int, int>|null  $quantities  overrides per sales_order_item_id
     * @return array{weight_grams: int, length_cm: string|null, breadth_cm: string|null, height_cm: string|null}
     */
    protected function package(iterable $items, ?array $quantities = null): array
    {
        $items = collect($items);
        $variants = ProductVariant::query()->whereKey($items->pluck('variant_id')->unique()->all())->get()->keyBy('id');
        $weight = 0;
        $dims = ['length_cm' => null, 'breadth_cm' => null, 'height_cm' => null];

        foreach ($items as $item) {
            /** @var SalesOrderItem $item */
            $qty = $quantities[$item->id] ?? ($item->quantity - $item->cancelled_qty);
            $variant = $variants->get($item->variant_id);

            if ($variant === null || $qty <= 0) {
                continue;
            }

            $weight += $variant->weight_grams * $qty;

            foreach (array_keys($dims) as $column) {
                $value = $variant->getAttribute($column);

                if ($value !== null && ($dims[$column] === null || (float) $value > (float) $dims[$column])) {
                    $dims[$column] = (string) $value;
                }
            }
        }

        return ['weight_grams' => max((int) config('logistics.package.min_weight_grams', 100), $weight)] + $dims;
    }

    /**
     * What the courier collects for this package. A single-package order collects the whole
     * grand total; otherwise each package collects its items (reseller customer prices, minus
     * the coupon share) and its delivery share, and the first package also the COD fee.
     *
     * @param  iterable<SalesOrderItem>  $items
     */
    protected function codAmount(SalesOrder $order, SellerOrder $sellerOrder, iterable $items): Money
    {
        $packages = SellerOrder::query()
            ->where('sales_order_id', $order->id)
            ->where('status', '!=', SellerOrder::STATUS_CANCELLED)
            ->orderBy('id')
            ->pluck('id');

        if ($packages->count() <= 1) {
            return $order->grand_total;
        }

        $amount = $sellerOrder->delivery_charge_share;

        foreach ($items as $item) {
            /** @var SalesOrderItem $item */
            $qty = $item->quantity - $item->cancelled_qty;
            $unit = $order->isResellerOrder() && $item->customer_price !== null ? $item->customer_price : $item->price;
            $amount = $amount->plus($unit->times($qty))->minus($item->discount_allocated);
        }

        if ((int) $packages->first() === $sellerOrder->id) {
            $amount = $amount->plus($order->cod_fee);
        }

        return $amount->isNegative() ? Money::zero() : $amount;
    }

    /**
     * @param  list<Shipment>  $shipments
     *
     * @throws ShipmentException
     */
    protected function sameCourier(array $shipments): Courier
    {
        if ($shipments === []) {
            throw new ShipmentException(trans('logistics::shipment.messages.nothing_selected'), 'empty');
        }

        $courierIds = array_values(array_unique(array_map(fn (Shipment $shipment): ?int => $shipment->courier_id, $shipments)));

        if (count($courierIds) !== 1 || $courierIds[0] === null) {
            throw new ShipmentException(trans('logistics::shipment.messages.mixed_couriers'), 'mixed_couriers');
        }

        foreach ($shipments as $shipment) {
            if (! $shipment->isBooked()) {
                throw new ShipmentException(trans('logistics::shipment.messages.awb_required'), 'not_booked');
            }
        }

        return Courier::query()->findOrFail($courierIds[0]);
    }
}
