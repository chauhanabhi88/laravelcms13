<?php

declare(strict_types=1);

namespace Modules\Core\Drivers\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Contracts\SmsDriver;

/**
 * Development driver: writes each message, OTP included, to storage/logs/sms.log.
 */
class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $templateKey, array $vars = []): SmsResult
    {
        $messageId = (string) Str::uuid();

        Log::channel('sms')->info("SMS to {$to} [{$templateKey}]", ['vars' => $vars, 'message_id' => $messageId]);

        return SmsResult::sent($messageId);
    }
}
