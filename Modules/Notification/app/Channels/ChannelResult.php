<?php

declare(strict_types=1);

namespace Modules\Notification\Channels;

use Modules\Notification\Models\NotificationLog;

/**
 * What a hub channel did with one notification; it is the NotificationSent "response" and
 * becomes the notification_log row.
 */
final readonly class ChannelResult
{
    public function __construct(
        public string $channel,
        public string $status,
        public string $destination = '',
        public ?string $providerMessageId = null,
        public ?string $error = null,
    ) {}

    public static function sent(string $channel, string $destination, ?string $providerMessageId = null): self
    {
        return new self($channel, NotificationLog::STATUS_SENT, $destination, $providerMessageId);
    }

    public static function failed(string $channel, string $destination, string $error): self
    {
        return new self($channel, NotificationLog::STATUS_FAILED, $destination, null, $error);
    }

    /**
     * @param  string  $reason  no_route | no_template | disabled | opted_out | unreachable
     */
    public static function skipped(string $channel, string $reason, string $destination = ''): self
    {
        return new self($channel, NotificationLog::STATUS_SKIPPED, $destination, null, $reason);
    }

    public function wasSent(): bool
    {
        return $this->status === NotificationLog::STATUS_SENT;
    }
}
