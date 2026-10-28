<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\Shipment;

/**
 * @extends Factory<NdrCase>
 */
class NdrCaseFactory extends Factory
{
    protected $model = NdrCase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory()->booked(null, Shipment::STATUS_NDR),
            'reason' => 'Customer not available',
            'attempts' => 1,
            'status' => NdrCase::STATUS_OPEN,
        ];
    }
}
