<?php

declare(strict_types=1);

namespace Modules\Notification\Drivers;

use Illuminate\Support\Str;
use Modules\Notification\Contracts\PushDriver;

/**
 * Development driver: writes each push to storage/logs/notifications.log.
 */
class LogPushDriver implements PushDriver
{
    public function send(array $tokens, PushMessage $message): PushResult
    {
        $messageIds = [];

        foreach ($tokens as $token) {
            $messageIds[] = $messageId = (string) Str::uuid();

            NotificationLogger::channel()->info("PUSH to {$token}: {$message->title}", [
                'body' => $message->body,
                'data' => $message->data,
                'message_id' => $messageId,
            ]);
        }

        return new PushResult(count($tokens), $messageIds);
    }
}
