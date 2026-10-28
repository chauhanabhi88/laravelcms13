<?php

declare(strict_types=1);

namespace Modules\Notification\Contracts;

use Modules\Notification\Drivers\DeliveryResult;

/**
 * A WhatsApp Business provider. Only the "null" driver exists in v1; a provider driver
 * implements this later without touching the channel.
 */
interface WhatsAppDriver
{
    /**
     * @param  string  $to  E.164 phone number
     * @param  string|null  $templateId  approved provider template id
     * @param  array<string, string>  $vars  template variables
     * @param  string  $body  the rendered text (for providers and logs that take free text)
     */
    public function send(string $to, ?string $templateId, array $vars, string $body): DeliveryResult;
}
