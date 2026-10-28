<?php

declare(strict_types=1);

namespace Modules\Order\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderAddress;
use Modules\Order\Support\OrderOptions;

/**
 * @mixin SalesOrder
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_no' => $this->order_no,
            'status' => $this->status,
            'status_label' => OrderOptions::salesOrderStatus($this->status),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'items_count' => $this->items_count,
            'total_mrp' => $this->total_mrp->toDecimal(),
            'discount' => $this->total_discount->toDecimal(),
            'coupon_discount' => $this->coupon_discount->toDecimal(),
            'delivery_charge' => $this->delivery_charge->toDecimal(),
            'cod_fee' => $this->cod_fee->toDecimal(),
            'grand_total' => $this->grand_total->toDecimal(),
            'is_reseller_order' => $this->isResellerOrder(),
            'reseller_margin' => $this->reseller_margin_total->toDecimal(),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'shipping_address' => $this->whenLoaded('shippingAddress', fn (): ?array => $this->address($this->shippingAddress)),
            'seller_orders' => SellerOrderResource::collection($this->whenLoaded('sellerOrders')),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function address(?SalesOrderAddress $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'name' => $address->name,
            'phone' => $address->phone,
            'address_line1' => $address->address_line1,
            'address_line2' => $address->address_line2,
            'landmark' => $address->landmark,
            'pincode' => $address->pincode,
            'city' => $address->city_name,
            'state' => $address->state_name,
            'is_end_customer' => $address->isEndCustomer(),
        ];
    }
}
