<?php

declare(strict_types=1);

namespace Modules\Returns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Returns\Models\ReturnRequest;

/**
 * A return of a delivered seller order (placeholder items, no stock or payment). Feature
 * tests that need the whole flow go through ReturnService with a real order instead.
 *
 * @extends Factory<ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    protected $model = ReturnRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_no' => 'RET-'.now()->format('ym').'-'.fake()->unique()->numerify('8#####'),
            'seller_order_id' => SellerOrder::factory()->withItems()->state([
                'status' => SellerOrder::STATUS_DELIVERED,
                'delivered_at' => now()->subDay(),
                'return_window_ends_at' => now()->addDays(6),
            ]),
            'sales_order_id' => fn (array $attributes): int => (int) SellerOrder::query()->whereKey($attributes['seller_order_id'])->value('sales_order_id'),
            'seller_id' => fn (array $attributes): int => (int) SellerOrder::query()->whereKey($attributes['seller_order_id'])->value('seller_id'),
            'customer_id' => fn (array $attributes): int => (int) SalesOrder::query()->whereKey($attributes['sales_order_id'])->value('customer_id'),
            'type' => ReturnRequest::TYPE_RETURN,
            'status' => ReturnRequest::STATUS_REQUESTED,
            'reason_code' => 'wrong_size',
            'comment' => null,
            'refund_mode' => 'source',
            'refund_amount' => '499.00',
            'delivery_refund' => '0.00',
            'refund_on' => ReturnRequest::REFUND_ON_QC,
            'pickup_address' => [
                'name' => fake()->name(),
                'phone' => '9876543210',
                'address_line1' => fake()->streetAddress(),
                'pincode' => '560001',
                'city_name' => 'Bengaluru',
                'state_name' => 'Karnataka',
            ],
            'auto_approved' => (int) config('core.no'),
            'needs_attention' => (int) config('core.no'),
        ];
    }

    public function exchange(): static
    {
        return $this->state(['type' => ReturnRequest::TYPE_EXCHANGE, 'refund_mode' => null, 'refund_amount' => '0.00']);
    }

    public function status(string $status): static
    {
        return $this->state(['status' => $status]);
    }
}
