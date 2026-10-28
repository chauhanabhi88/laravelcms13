<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\ShipmentEvent;

/**
 * @extends Factory<ShipmentEvent>
 */
class ShipmentEventFactory extends Factory
{
    protected $model = ShipmentEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'status' => Shipment::STATUS_IN_TRANSIT,
            'provider_status' => 'IN TRANSIT',
            'provider_status_code' => '18',
            'location' => 'Bengaluru Hub',
            'remarks' => 'Shipment in transit',
            'occurred_at' => now(),
        ];
    }
}
