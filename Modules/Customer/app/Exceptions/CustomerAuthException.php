<?php

declare(strict_types=1);

namespace Modules\Customer\Exceptions;

use RuntimeException;

/**
 * An OTP was valid but the account cannot sign in. The message is a translated, user-safe string.
 */
final class CustomerAuthException extends RuntimeException
{
    public const DISABLED = 'disabled';

    public const DELETED = 'deleted';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(trans('customer::customer_front.otp.account_'.$reason));
    }
}
