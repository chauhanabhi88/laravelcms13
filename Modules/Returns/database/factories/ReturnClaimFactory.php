<?php

declare(strict_types=1);

namespace Modules\Returns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;

/**
 * An open claim on a received return.
 *
 * @extends Factory<ReturnClaim>
 */
class ReturnClaimFactory extends Factory
{
    protected $model = ReturnClaim::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_request_id' => ReturnRequest::factory()->status(ReturnRequest::STATUS_QC_FAILED),
            'seller_order_id' => fn (array $attributes): int => (int) ReturnRequest::query()->whereKey($attributes['return_request_id'])->value('seller_order_id'),
            'seller_id' => fn (array $attributes): int => (int) ReturnRequest::query()->whereKey($attributes['return_request_id'])->value('seller_id'),
            'source' => ReturnClaim::SOURCE_RETURN,
            'reason' => 'damaged_return',
            'description' => fake()->sentence(),
            'images' => null,
            'status' => ReturnClaim::STATUS_OPEN,
        ];
    }
}
