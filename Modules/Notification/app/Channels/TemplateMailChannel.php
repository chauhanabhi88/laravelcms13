<?php

declare(strict_types=1);

namespace Modules\Notification\Channels;

use Illuminate\Support\Facades\Mail;
use Modules\Mail\Models\MailTemplate;
use Modules\Notification\Mail\TemplateMailable;
use Modules\Notification\Notifications\MarketplaceNotification;
use Modules\Notification\Support\EventDefinition;
use Modules\Notification\Support\Mask;

/**
 * Email through the Mail module's DB templates (slug "<event-key>-<audience>"); the
 * existing LogSendMail listener records it in the mail log as well.
 */
class TemplateMailChannel extends HubChannel
{
    public const NAME = 'mail';

    protected function deliver(object $notifiable, MarketplaceNotification $notification): ChannelResult
    {
        $email = $this->recipients->email($notifiable);

        if ($email === null) {
            return ChannelResult::skipped(self::NAME, 'no_route');
        }

        $audience = $this->recipients->audience($notifiable) ?? $notification->definition()->audience;
        $template = MailTemplate::query()
            ->where('slug', EventDefinition::mailSlugFor($notification->eventKey, $audience))
            ->where('status', config('core.enabled'))
            ->first();

        if (! $template instanceof MailTemplate) {
            return ChannelResult::skipped(self::NAME, 'no_template', Mask::email($email));
        }

        $sent = Mail::to($email, $this->recipients->name($notifiable) ?: null)
            ->send(new TemplateMailable($template, $this->vars($notifiable, $notification)));

        return ChannelResult::sent(self::NAME, Mask::email($email), $sent?->getMessageId());
    }
}
