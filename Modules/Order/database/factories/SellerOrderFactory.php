<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Seller\Models\Seller;

/**
 * @extends Factory<SellerOrder>
 */
class SellerOrderFactory extends Factory
{
    protected $model = SellerOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'seller_id' => Seller::factory()->active(),
            'sub_order_no' => 'ORD-'.now()->format('ym').'-'.fake()->unique()->numerify('8#####').'-1',
            'status' => SellerOrder::STATUS_PENDING_ACCEPTANCE,
            'items_subtotal' => '499.00',
            'tax_total' => '23.76',
            'commission_total' => '0.00',
            'delivery_charge_share' => '0.00',
            'sla_dispatch_by' => now()->addDays(2),
        ];
    }

    /**
     * $count ₹499 items (5% GST, intra-state) of placeholder variants.
     */
    public function withItems(int $count = 1): static
    {
        return $this->afterCreating(function (SellerOrder $sellerOrder) use ($count): void {
            SalesOrderItem::factory()->count($count)->create([
                'sales_order_id' => $sellerOrder->sales_order_id,
                'seller_order_id' => $sellerOrder->id,
            ]);
        });
    }
}
