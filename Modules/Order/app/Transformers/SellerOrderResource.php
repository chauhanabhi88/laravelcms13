<?php

declare(strict_types=1);

namespace Modules\Order\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Support\OrderOptions;

/**
 * One package of an order as the customer sees it (no commission or seller-side fields).
 *
 * @mixin SellerOrder
 */
class SellerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sub_order_no' => $this->sub_order_no,
            'seller' => $this->whenLoaded('seller', fn (): ?string => $this->seller?->displayName()),
            'status' => $this->status,
            'status_label' => OrderOptions::sellerOrderStatus($this->status),
            'subtotal' => $this->items_subtotal->toDecimal(),
            'expected_delivery_at' => $this->expected_delivery_at?->toDateString(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'has_invoice' => $this->invoice_no !== null && ! $this->isCancelled(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
