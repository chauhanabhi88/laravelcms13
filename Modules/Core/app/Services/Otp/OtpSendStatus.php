<?php

declare(strict_types=1);

namespace Modules\Core\Services\Otp;

enum OtpSendStatus: string
{
    case Sent = 'sent';
    case Cooldown = 'cooldown';
    case Throttled = 'throttled';
    case Failed = 'failed';
}
