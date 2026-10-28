<?php

declare(strict_types=1);

namespace Modules\Notification\Drivers;

use Illuminate\Support\Str;
use Modules\Notification\Contracts\WhatsAppDriver;

/**
 * Stub until a WhatsApp Business provider is chosen: logs the message and reports success.
 */
class NullWhatsAppDriver implements WhatsAppDriver
{
    public function send(string $to, ?string $templateId, array $vars, string $body): DeliveryResult
    {
        $messageId = (string) Str::uuid();

        NotificationLogger::channel()->info("WHATSAPP to {$to} [".($templateId ?: '-').']', [
            'body' => $body,
            'vars' => $vars,
            'message_id' => $messageId,
        ]);

        return DeliveryResult::sent($messageId);
    }
}
