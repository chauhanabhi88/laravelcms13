<?php

declare(strict_types=1);

namespace Modules\Core\Services\Otp;

final readonly class OtpSendResult
{
    /**
     * @param  int  $retryAfter  seconds until another send may succeed (0 when sent)
     */
    public function __construct(
        public OtpSendStatus $status,
        public int $retryAfter = 0,
        public ?string $identifier = null,
    ) {}

    public function successful(): bool
    {
        return $this->status === OtpSendStatus::Sent;
    }
}
