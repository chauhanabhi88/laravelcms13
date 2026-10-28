<?php

declare(strict_types=1);

namespace Modules\Returns\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Models\ReturnRequestItem;
use Modules\Returns\Support\ReturnOptions;

/**
 * A return for the mobile apps. Refund details are masked; photos are not exposed (private).
 *
 * @mixin ReturnRequest
 */
class ReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'return_no' => $this->return_no,
            'order_no' => $this->whenLoaded('sellerOrder', fn () => $this->sellerOrder?->sub_order_no),
            'type' => $this->type,
            'status' => $this->status,
            'status_label' => ReturnOptions::status($this->status),
            'reason_code' => $this->reason_code,
            'reason' => ReturnOptions::reason($this->reason_code),
            'comment' => $this->comment,
            'refund_mode' => $this->refund_mode,
            'refund_to' => $this->maskedBeneficiary(),
            'refund_amount' => $this->refund_amount->toDecimal(),
            'refund_status' => $this->refund_status,
            'pickup_address' => $this->pickupSummary(),
            'cancellable' => $this->isCancellableByCustomer(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (ReturnRequestItem $item): array => [
                'item_id' => $item->sales_order_item_id,
                'name' => $item->orderItem?->name,
                'variant_label' => $item->orderItem?->variant_label,
                'quantity' => $item->quantity,
                'exchange_variant_id' => $item->exchange_variant_id,
                'unit_refund' => $item->unit_refund->toDecimal(),
                'status' => $item->status,
            ])->values()->all()),
            'photos_count' => $this->whenLoaded('images', fn () => $this->images->count()),
            'tracking' => $this->whenLoaded('reverseShipment', fn () => $this->reverseShipment === null ? null : [
                'awb' => $this->reverseShipment->awb,
                'courier' => $this->reverseShipment->carrier_name,
                'tracking_url' => $this->reverseShipment->tracking_url,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
        ];
    }
}
