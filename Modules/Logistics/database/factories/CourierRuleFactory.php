<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\CourierRule;

/**
 * @extends Factory<CourierRule>
 */
class CourierRuleFactory extends Factory
{
    protected $model = CourierRule::class;

    /**
     * An active, unconditional rule for a new fake courier.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Rule '.fake()->unique()->numerify('###'),
            'priority' => 10,
            'conditions' => null,
            'courier_id' => Courier::factory(),
            'preferred_carrier_ids' => null,
            'strategy' => CourierRule::STRATEGY_RECOMMENDED,
            'status' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    public function withConditions(array $conditions): static
    {
        return $this->state(['conditions' => $conditions]);
    }

    public function strategy(string $strategy, ?array $preferred = null): static
    {
        return $this->state(['strategy' => $strategy, 'preferred_carrier_ids' => $preferred]);
    }
}
