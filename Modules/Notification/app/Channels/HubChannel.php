<?php

declare(strict_types=1);

namespace Modules\Notification\Channels;

use Illuminate\Notifications\Notification;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Notifications\MarketplaceNotification;
use Modules\Notification\Support\RecipientResolver;
use Modules\Notification\Support\TemplateRenderer;

/**
 * Base of the hub's notification channels: only MarketplaceNotification is handled, and
 * every outcome (sent, failed, skipped) is returned as a ChannelResult for the log.
 */
abstract class HubChannel
{
    public const NAME = '';

    public function __construct(
        protected readonly RecipientResolver $recipients,
        protected readonly TemplateRenderer $renderer,
    ) {}

    public function send(object $notifiable, Notification $notification): ?ChannelResult
    {
        if (! $notification instanceof MarketplaceNotification) {
            return null;
        }

        if (! $this->recipients->isReachable($notifiable)) {
            return ChannelResult::skipped(static::NAME, 'unreachable');
        }

        return $this->deliver($notifiable, $notification);
    }

    abstract protected function deliver(object $notifiable, MarketplaceNotification $notification): ChannelResult;

    protected function template(object $notifiable, MarketplaceNotification $notification): ?NotificationTemplate
    {
        $audience = $this->recipients->audience($notifiable) ?? $notification->definition()->audience;

        return NotificationTemplate::findActive($notification->eventKey, static::NAME, $audience);
    }

    /**
     * Template variables: the common ones, then the event data.
     *
     * @return array<string, scalar|null>
     */
    protected function vars(object $notifiable, MarketplaceNotification $notification): array
    {
        return array_merge([
            'app_name' => (string) config('app.name'),
            'recipient_name' => $this->recipients->name($notifiable),
            'url' => '',
        ], $notification->data);
    }

    /**
     * The values of the placeholders a template uses, as provider template variables.
     *
     * @param  array<string, scalar|null>  $vars
     * @return array<string, string>
     */
    protected function templateVars(?string $template, array $vars): array
    {
        $values = [];

        foreach ($this->renderer->placeholders($template) as $placeholder) {
            $values[$placeholder] = $this->renderer->renderText('{{'.$placeholder.'}}', $vars);
        }

        return $values;
    }
}
