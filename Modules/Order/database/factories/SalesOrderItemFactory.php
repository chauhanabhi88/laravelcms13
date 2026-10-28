<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Order\Models\SalesOrderItem;

/**
 * A ₹499 (MRP ₹999) line at 5% GST, intra-state. product_id / variant_id are placeholders:
 * use PlaceOrderService when the test needs real stock.
 *
 * @extends Factory<SalesOrderItem>
 */
class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => fake()->numberBetween(900000, 999999),
            'variant_id' => fake()->numberBetween(900000, 999999),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'name' => Str::title(fake()->words(3, true)),
            'variant_label' => 'M / Blue',
            'hsn_code' => '6204',
            'quantity' => 1,
            'mrp' => '999.00',
            'price' => '499.00',
            'discount_allocated' => '0.00',
            'taxable_value' => '475.24',
            'gst_percent' => '5.00',
            'cgst' => '11.88',
            'sgst' => '11.88',
            'igst' => '0.00',
            'commission_amount' => '0.00',
            'reseller_margin' => '0.00',
            'return_window_days' => 7,
            'return_type' => 'return',
            'status' => SalesOrderItem::STATUS_ACTIVE,
        ];
    }
}
