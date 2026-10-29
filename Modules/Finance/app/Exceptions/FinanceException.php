<?php

declare(strict_types=1);

namespace Modules\Finance\Exceptions;

use RuntimeException;

/**
 * A finance action that cannot be done; the message is safe to show to the user and
 * $reasonCode is stable for tests.
 */
class FinanceException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reasonCode = 'invalid')
    {
        parent::__construct($message);
    }
}
