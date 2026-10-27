<?php

declare(strict_types=1);

namespace Modules\Seller\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to a seller about an admin decision (approved, rejected, changes requested,
 * suspended, reactivated). Copy lives in lang seller::seller.mail.<decision>.
 */
class SellerDecisionNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, string>  $replace  store, reason, app, url
     */
    public function __construct(public readonly string $decision, public readonly array $replace) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject(trans("seller::seller.mail.{$this->decision}.subject", $this->replace));

        foreach (explode("\n\n", trans("seller::seller.mail.{$this->decision}.body", $this->replace)) as $paragraph) {
            $message->line($paragraph);
        }

        return $message;
    }
}
