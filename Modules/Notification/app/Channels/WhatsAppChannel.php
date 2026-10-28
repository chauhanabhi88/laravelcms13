<?php

declare(strict_types=1);

namespace Modules\Notification\Channels;

use Modules\Notification\Contracts\WhatsAppDriver;
use Modules\Notification\Notifications\MarketplaceNotification;
use Modules\Notification\Support\Mask;

/**
 * WhatsApp through the bound WhatsAppDriver (the null driver only logs in v1).
 */
class WhatsAppChannel extends HubChannel
{
    public const NAME = 'whatsapp';

    protected function deliver(object $notifiable, MarketplaceNotification $notification): ChannelResult
    {
        $phone = $this->recipients->phone($notifiable);

        if ($phone === null) {
            return ChannelResult::skipped(self::NAME, 'no_route');
        }

        $template = $this->template($notifiable, $notification);

        if ($template === null) {
            return ChannelResult::skipped(self::NAME, 'no_template', Mask::phone($phone));
        }

        $vars = $this->vars($notifiable, $notification);
        $result = app(WhatsAppDriver::class)->send(
            $phone,
            $template->provider_template_id,
            $this->templateVars($template->body, $vars),
            $this->renderer->renderText($template->body, $vars),
        );

        return $result->successful
            ? ChannelResult::sent(self::NAME, Mask::phone($phone), $result->messageId)
            : ChannelResult::failed(self::NAME, Mask::phone($phone), (string) $result->error);
    }
}
