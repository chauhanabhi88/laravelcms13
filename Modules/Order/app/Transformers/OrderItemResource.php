<?php

declare(strict_types=1);

namespace Modules\Order\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Models\SalesOrderItem;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * @mixin SalesOrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'name' => $this->name,
            'variant_label' => $this->variant_label,
            'sku' => $this->sku,
            'image' => StorefrontUrls::productImage($this->image, 'thumb') ?? StorefrontUrls::productImage($this->image),
            'quantity' => $this->quantity,
            'mrp' => $this->mrp->toDecimal(),
            'price' => $this->price->toDecimal(),
            'line_total' => $this->lineTotal()->toDecimal(),
            'discount' => $this->discount_allocated->toDecimal(),
            'customer_price' => $this->customer_price?->toDecimal(),
            'status' => $this->status,
            'return_window_days' => $this->return_window_days,
            'return_type' => $this->return_type,
        ];
    }
}
