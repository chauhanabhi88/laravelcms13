<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\PayoutBatch;

/**
 * An empty draft batch.
 *
 * @extends Factory<PayoutBatch>
 */
class PayoutBatchFactory extends Factory
{
    protected $model = PayoutBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_no' => 'PB-'.now()->format('ym').'-'.fake()->unique()->numerify('9###'),
            'cycle_start' => null,
            'cycle_end' => now()->toDateString(),
            'status' => PayoutBatch::STATUS_DRAFT,
            'gateway' => 'fake',
            'total_amount' => '0.00',
            'sellers_count' => 0,
        ];
    }
}
