<?php

declare(strict_types=1);

namespace Modules\Notification\Channels;

use Modules\Core\Contracts\SmsDriver;
use Modules\Notification\Notifications\MarketplaceNotification;
use Modules\Notification\Support\Mask;
use Modules\Notification\Support\NotificationSettings;

/**
 * SMS through Core's SmsDriver. DLT needs the registered template id plus its variables,
 * so the driver gets provider_template_id (falling back to the event key) and the values of
 * the template's placeholders; "message" carries the rendered text for logs.
 */
class SmsChannel extends HubChannel
{
    public const NAME = 'sms';

    protected function deliver(object $notifiable, MarketplaceNotification $notification): ChannelResult
    {
        if (! $notification->definition()->isOptional() && ! NotificationSettings::transactionalSmsEnabled()) {
            return ChannelResult::skipped(self::NAME, 'disabled');
        }

        $phone = $this->recipients->phone($notifiable);

        if ($phone === null) {
            return ChannelResult::skipped(self::NAME, 'no_route');
        }

        $template = $this->template($notifiable, $notification);

        if ($template === null) {
            return ChannelResult::skipped(self::NAME, 'no_template', Mask::phone($phone));
        }

        $vars = $this->vars($notifiable, $notification);
        $message = $this->renderer->limitSms(
            $this->renderer->renderText($template->body, $vars),
            (int) config('notification.sms_max_segments', 2),
        );

        $result = app(SmsDriver::class)->send(
            $phone,
            $template->provider_template_id ?: $notification->eventKey,
            $this->templateVars($template->body, $vars) + ['message' => $message],
        );

        return $result->successful
            ? ChannelResult::sent(self::NAME, Mask::phone($phone), $result->messageId)
            : ChannelResult::failed(self::NAME, Mask::phone($phone), (string) $result->error);
    }
}
