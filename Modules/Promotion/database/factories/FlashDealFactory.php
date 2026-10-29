<?php

declare(strict_types=1);

namespace Modules\Promotion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Promotion\Models\FlashDeal;

/**
 * An enabled deal that started an hour ago and ends in a day.
 *
 * @extends Factory<FlashDeal>
 */
class FlashDealFactory extends Factory
{
    protected $model = FlashDeal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test flash deal',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'banner' => null,
            'status' => config('core.enabled'),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
    }

    public function ended(): static
    {
        return $this->state(['starts_at' => now()->subDays(2), 'ends_at' => now()->subMinute()]);
    }

    public function disabled(): static
    {
        return $this->state(['status' => config('core.disabled')]);
    }
}
