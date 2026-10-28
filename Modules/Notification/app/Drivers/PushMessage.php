<?php

declare(strict_types=1);

namespace Modules\Notification\Drivers;

/**
 * A push notification: what the device shows plus string data for the app.
 */
final readonly class PushMessage
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
    ) {}
}
