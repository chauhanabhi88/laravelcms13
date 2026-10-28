<?php

declare(strict_types=1);

namespace Modules\Notification\Drivers;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * storage/logs/notifications.log, written by the log push driver and the null WhatsApp driver.
 */
final class NotificationLogger
{
    public static function channel(): LoggerInterface
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/notifications.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
    }
}
