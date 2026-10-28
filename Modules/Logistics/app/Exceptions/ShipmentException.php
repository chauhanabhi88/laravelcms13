<?php

declare(strict_types=1);

namespace Modules\Logistics\Exceptions;

use RuntimeException;

/**
 * A shipment action that is not allowed in its current state (already booked, cancelled
 * after pickup, manual action on an API courier…). The message is shown to the user.
 */
class ShipmentException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reasonCode = 'invalid_state')
    {
        parent::__construct($message);
    }
}
