<?php

declare(strict_types=1);

namespace Modules\Core\Drivers\Sms;

final readonly class SmsResult
{
    public function __construct(
        public bool $successful,
        public ?string $messageId = null,
        public ?string $error = null,
    ) {}

    public static function sent(?string $messageId = null): self
    {
        return new self(true, $messageId);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
