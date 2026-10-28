<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Illuminate\Support\Carbon;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\ShipmentEvent;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;

/**
 * The customer-facing timeline of one seller order (FR-LOG-007, plan M13 §5.5):
 * Ordered → Packed → Shipped → Out for Delivery → Delivered, each done / current / pending
 * with its time, plus a branch state (cancelled, delivery attempt failed, returning to
 * seller, returned to seller) and the courier scans. "events" is ready for <x-theme::timeline>.
 */
class TimelineBuilder
{
    public const STEPS = ['ordered', 'packed', 'shipped', 'out_for_delivery', 'delivered'];

    /**
     * How far along the steps each seller-order status is (index into STEPS).
     */
    private const REACHED = [
        SellerOrder::STATUS_PENDING_PAYMENT => 0,
        SellerOrder::STATUS_PENDING_ACCEPTANCE => 0,
        SellerOrder::STATUS_READY_TO_SHIP => 1,
        SellerOrder::STATUS_PACKED => 1,
        SellerOrder::STATUS_SHIPPED => 2,
        SellerOrder::STATUS_NDR => 3,
        SellerOrder::STATUS_RTO_INITIATED => 2,
        SellerOrder::STATUS_RTO_DELIVERED => 2,
        SellerOrder::STATUS_OUT_FOR_DELIVERY => 3,
        SellerOrder::STATUS_DELIVERED => 4,
        SellerOrder::STATUS_RETURN_REQUESTED => 4,
        SellerOrder::STATUS_RETURNED => 4,
        SellerOrder::STATUS_COMPLETED => 4,
    ];

    /**
     * @return array{
     *     sub_order_no: string,
     *     status: string,
     *     steps: list<array{key: string, label: string, at: Carbon|null, state: string}>,
     *     branch: array{key: string, label: string, at: Carbon|null, note: string|null}|null,
     *     events: list<array{label: string, at: Carbon|null, state: string, note?: string|null}>,
     *     scans: list<array{label: string, location: string|null, remarks: string|null, at: Carbon}>,
     *     courier: string|null,
     *     awb: string|null,
     *     tracking_url: string|null,
     *     edd: Carbon|null
     * }
     */
    public function build(SellerOrder $sellerOrder): array
    {
        $sellerOrder->loadMissing('salesOrder');
        $shipment = Shipment::query()
            ->forward()
            ->where('seller_order_id', $sellerOrder->id)
            ->orderByRaw('status = ? asc', [Shipment::STATUS_CANCELLED])
            ->orderByDesc('id')
            ->with(['events', 'ndrCases'])
            ->first();

        $scans = $shipment?->events ?? collect();
        $firstScan = fn (array $statuses): ?Carbon => $scans->first(fn (ShipmentEvent $event): bool => in_array($event->status, $statuses, true))?->occurred_at;

        $times = [
            'ordered' => $sellerOrder->salesOrder?->placed_at ?? $sellerOrder->created_at,
            'packed' => $sellerOrder->packed_at ?? $sellerOrder->accepted_at,
            'shipped' => $sellerOrder->shipped_at ?? $shipment?->picked_up_at,
            'out_for_delivery' => $firstScan([Shipment::STATUS_OUT_FOR_DELIVERY]),
            'delivered' => $sellerOrder->delivered_at ?? $shipment?->delivered_at,
        ];

        $status = $sellerOrder->status;
        $reached = $status === SellerOrder::STATUS_CANCELLED
            ? $this->reachedByTimes($times)
            : (self::REACHED[$status] ?? 0);
        $finished = in_array($status, [SellerOrder::STATUS_DELIVERED, SellerOrder::STATUS_RETURN_REQUESTED, SellerOrder::STATUS_RETURNED, SellerOrder::STATUS_COMPLETED], true);
        $branch = $this->branch($sellerOrder, $shipment);

        $steps = [];
        foreach (self::STEPS as $index => $key) {
            $state = match (true) {
                $index < $reached => 'done',
                $index === $reached => ($finished || $branch !== null) ? 'done' : 'current',
                default => 'pending',
            };

            $steps[] = [
                'key' => $key,
                'label' => trans('logistics::front.timeline.steps.'.$key),
                'at' => $index <= $reached ? $times[$key] : null,
                'state' => $state,
            ];
        }

        $events = array_map(fn (array $step): array => ['label' => $step['label'], 'at' => $step['at'], 'state' => $step['state']], $steps);

        if ($branch !== null) {
            // The branch replaces the steps not reached.
            $events = array_slice($events, 0, $reached + 1);
            $events[] = ['label' => $branch['label'], 'at' => $branch['at'], 'state' => 'current', 'note' => $branch['note']];
        }

        return [
            'sub_order_no' => $sellerOrder->sub_order_no,
            'status' => $status,
            'steps' => $steps,
            'branch' => $branch,
            'events' => $events,
            'scans' => $scans->reverse()->values()->map(fn (ShipmentEvent $event): array => [
                'label' => $event->status !== null ? trans('logistics::front.timeline.scan_status.'.$event->status) : ucwords(strtolower($event->provider_status)),
                'location' => $event->location,
                'remarks' => $event->remarks,
                'at' => $event->occurred_at,
            ])->all(),
            'courier' => $shipment?->carrier_name,
            'awb' => $shipment?->awb,
            'tracking_url' => $shipment?->tracking_url,
            'edd' => $finished ? null : ($shipment?->edd ?? $sellerOrder->expected_delivery_at),
        ];
    }

    /**
     * @return array{key: string, label: string, at: Carbon|null, note: string|null}|null
     */
    protected function branch(SellerOrder $sellerOrder, ?Shipment $shipment): ?array
    {
        return match ($sellerOrder->status) {
            SellerOrder::STATUS_CANCELLED => [
                'key' => 'cancelled',
                'label' => trans('logistics::front.timeline.branches.cancelled'),
                'at' => $sellerOrder->cancelled_at,
                'note' => $sellerOrder->cancel_reason,
            ],
            SellerOrder::STATUS_NDR => [
                'key' => 'delivery_failed',
                'label' => trans('logistics::front.timeline.branches.delivery_failed'),
                'at' => $shipment?->ndrCases->first(fn (NdrCase $case): bool => $case->isOpen())?->updated_at ?? $shipment?->last_event_at,
                'note' => trans_choice('logistics::front.timeline.attempts', $attempts = max(1, (int) $shipment?->attempts), ['count' => $attempts]),
            ],
            SellerOrder::STATUS_RTO_INITIATED => [
                'key' => 'returning',
                'label' => trans('logistics::front.timeline.branches.returning'),
                'at' => $shipment?->last_event_at,
                'note' => null,
            ],
            SellerOrder::STATUS_RTO_DELIVERED => [
                'key' => 'returned',
                'label' => trans('logistics::front.timeline.branches.returned'),
                'at' => $shipment?->rto_delivered_at,
                'note' => null,
            ],
            default => null,
        };
    }

    /**
     * For a cancelled order: the last step that has a time.
     *
     * @param  array<string, Carbon|null>  $times
     */
    protected function reachedByTimes(array $times): int
    {
        $reached = 0;

        foreach (self::STEPS as $index => $key) {
            if ($times[$key] !== null) {
                $reached = $index;
            }
        }

        return $reached;
    }

    /**
     * Timelines of every seller order of a sales order.
     *
     * @return list<array<string, mixed>>
     */
    public function forOrder(SalesOrder $order): array
    {
        $order->loadMissing('sellerOrders');

        return $order->sellerOrders->map(function (SellerOrder $sellerOrder) use ($order): array {
            $sellerOrder->setRelation('salesOrder', $order);

            return $this->build($sellerOrder);
        })->all();
    }
}
