<?php

declare(strict_types=1);

namespace Modules\Notification\Contracts;

use Modules\Notification\Drivers\PushMessage;
use Modules\Notification\Drivers\PushResult;

/**
 * A push provider. Bound from config('notification.push.driver'): log or fcm.
 */
interface PushDriver
{
    /**
     * @param  list<string>  $tokens
     */
    public function send(array $tokens, PushMessage $message): PushResult;
}
