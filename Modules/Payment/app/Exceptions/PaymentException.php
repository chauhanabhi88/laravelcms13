<?php

declare(strict_types=1);

namespace Modules\Payment\Exceptions;

use RuntimeException;

/**
 * A payment operation the buyer or admin cannot perform; the message is safe to show.
 */
class PaymentException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reasonCode = 'payment_error')
    {
        parent::__construct($message);
    }
}
