<?php

declare(strict_types=1);

namespace Modules\Payment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Payment\Models\WebhookEvent;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => WebhookEvent::PROVIDER_RAZORPAY,
            'event_id' => 'evt_'.Str::random(14),
            'event_type' => 'payment.captured',
            'signature_valid' => 1,
            'payload' => ['event' => 'payment.captured', 'payload' => []],
            'status' => WebhookEvent::STATUS_RECEIVED,
            'attempts' => 0,
            'created_at' => now(),
        ];
    }

    public function failed(string $error = 'Boom'): static
    {
        return $this->state(['status' => WebhookEvent::STATUS_FAILED, 'attempts' => 1, 'error' => $error]);
    }
}
