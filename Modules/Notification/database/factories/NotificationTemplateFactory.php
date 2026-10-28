<?php

declare(strict_types=1);

namespace Modules\Notification\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notification\Models\NotificationTemplate;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => 'order.shipped',
            'channel' => 'sms',
            'audience' => 'customer',
            'title' => 'Order {{order_no}} shipped',
            'body' => 'Hi {{recipient_name}}, your order {{order_no}} is on its way.',
            'provider_template_id' => null,
            'status' => (int) config('core.enabled'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => (int) config('core.disabled')]);
    }
}
