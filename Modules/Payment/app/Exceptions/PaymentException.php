<?php

declare(strict_types=1);

namespace Modules\Payment\Exceptions;

use Modules\Core\Contracts\ApiError;
use RuntimeException;

/**
 * A payment operation the buyer or admin cannot perform; the message is safe to show.
 */
class PaymentException extends RuntimeException implements ApiError
{
    public function __construct(string $message, public readonly string $reasonCode = 'payment_error')
    {
        parent::__construct($message);
    }

    public function apiReason(): string
    {
        return $this->reasonCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function apiErrors(): array
    {
        return [];
    }
}
