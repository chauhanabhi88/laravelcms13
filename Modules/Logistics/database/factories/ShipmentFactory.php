<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SellerOrder;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * A planned (not booked) prepaid forward shipment of a new seller order.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_order_id' => SellerOrder::factory(),
            'direction' => Shipment::DIRECTION_FORWARD,
            'status' => Shipment::STATUS_CREATED,
            'payment_mode' => Shipment::PAYMENT_PREPAID,
            'cod_amount' => '0.00',
            'weight_grams' => 500,
            'needs_attention' => 2,
        ];
    }

    /**
     * Booked with a (fake by default) courier and an AWB.
     */
    public function booked(?Courier $courier = null, string $status = Shipment::STATUS_AWB_ASSIGNED): static
    {
        return $this->state(fn (): array => [
            'courier_id' => $courier?->id ?? Courier::factory(),
            'awb' => 'AWB'.fake()->unique()->numerify('##########'),
            'carrier_id' => '11',
            'carrier_name' => 'Fake Fast',
            'provider_order_id' => (string) fake()->numerify('#######'),
            'provider_shipment_id' => (string) fake()->numerify('#######'),
            'status' => $status,
            'freight_charge' => '60.00',
        ]);
    }

    public function cod(string $amount = '499.00'): static
    {
        return $this->state(['payment_mode' => Shipment::PAYMENT_COD, 'cod_amount' => $amount]);
    }
}
