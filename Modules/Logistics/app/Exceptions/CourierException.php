<?php

declare(strict_types=1);

namespace Modules\Logistics\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A courier provider refused or failed a request. $reasonCode is a stable machine value
 * (e.g. not_configured, unauthorized, not_serviceable, provider_error).
 */
class CourierException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reasonCode = 'provider_error', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
