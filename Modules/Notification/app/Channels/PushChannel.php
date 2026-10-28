<?php

declare(strict_types=1);

namespace Modules\Notification\Channels;

use Modules\Notification\Contracts\PushDriver;
use Modules\Notification\Drivers\PushMessage;
use Modules\Notification\Notifications\MarketplaceNotification;
use Modules\Notification\Services\DeviceTokenRegistry;
use Modules\Notification\Support\NotificationSettings;
use Modules\Notification\Support\RecipientResolver;
use Modules\Notification\Support\TemplateRenderer;

/**
 * Push to every registered device of the recipient. Tokens the provider reports as
 * unregistered are deleted.
 */
class PushChannel extends HubChannel
{
    public const NAME = 'push';

    public function __construct(
        RecipientResolver $recipients,
        TemplateRenderer $renderer,
        private readonly DeviceTokenRegistry $tokens,
    ) {
        parent::__construct($recipients, $renderer);
    }

    protected function deliver(object $notifiable, MarketplaceNotification $notification): ChannelResult
    {
        if (! NotificationSettings::pushEnabled()) {
            return ChannelResult::skipped(self::NAME, 'disabled');
        }

        $tokens = $this->recipients->deviceTokens($notifiable);

        if ($tokens === []) {
            return ChannelResult::skipped(self::NAME, 'no_route');
        }

        $destination = $this->recipients->destination($notifiable, self::NAME);
        $template = $this->template($notifiable, $notification);

        if ($template === null) {
            return ChannelResult::skipped(self::NAME, 'no_template', $destination);
        }

        $vars = $this->vars($notifiable, $notification);
        $result = app(PushDriver::class)->send($tokens, new PushMessage(
            title: $this->renderer->renderText($template->title ?: '{{app_name}}', $vars),
            body: $this->renderer->renderText($template->body, $vars),
            data: [
                'event_key' => $notification->eventKey,
                'notification_id' => (string) $notification->id,
                'url' => (string) ($vars['url'] ?? ''),
            ],
        ));

        $this->tokens->forget($result->invalidTokens);

        return $result->successful()
            ? ChannelResult::sent(self::NAME, $destination, $result->messageIds[0] ?? null)
            : ChannelResult::failed(self::NAME, $destination, implode('; ', $result->errors) ?: 'No device accepted the push.');
    }
}
