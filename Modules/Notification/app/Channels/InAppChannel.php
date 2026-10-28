<?php

declare(strict_types=1);

namespace Modules\Notification\Channels;

use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Notifications\MarketplaceNotification;
use Modules\Notification\Services\InboxService;
use Modules\Notification\Support\RecipientResolver;
use Modules\Notification\Support\TemplateRenderer;

/**
 * In-app bell entry in Laravel's "notifications" table: {event_key, title, body, url, icon}.
 */
class InAppChannel extends HubChannel
{
    public const NAME = 'database';

    public function __construct(
        RecipientResolver $recipients,
        TemplateRenderer $renderer,
        private readonly InboxService $inbox,
    ) {
        parent::__construct($recipients, $renderer);
    }

    protected function deliver(object $notifiable, MarketplaceNotification $notification): ChannelResult
    {
        if (! $notifiable instanceof Model || ! method_exists($notifiable, 'notifications')) {
            return ChannelResult::skipped(self::NAME, 'no_route');
        }

        $template = $this->template($notifiable, $notification);

        if ($template === null) {
            return ChannelResult::skipped(self::NAME, 'no_template', 'in-app');
        }

        $vars = $this->vars($notifiable, $notification);

        // updateOrCreate: a retried job must not trip over the row it already wrote.
        $notifiable->notifications()->updateOrCreate(['id' => $notification->id], [
            'type' => $notification->eventKey,
            'data' => [
                'event_key' => $notification->eventKey,
                'title' => $this->renderer->renderText($template->title ?: '{{app_name}}', $vars),
                'body' => $this->renderer->renderText($template->body, $vars),
                'url' => (string) ($vars['url'] ?? ''),
                'icon' => $notification->definition()->icon,
            ],
            'read_at' => null,
        ]);

        $this->inbox->forgetUnreadCount($notifiable);

        return ChannelResult::sent(self::NAME, 'in-app', (string) $notification->id);
    }
}
