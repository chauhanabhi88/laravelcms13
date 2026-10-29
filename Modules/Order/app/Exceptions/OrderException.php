<?php

declare(strict_types=1);

namespace Modules\Order\Exceptions;

use Modules\Core\Contracts\ApiError;
use RuntimeException;

/**
 * An order operation was refused (e.g. cancelling after shipment). Nothing was written.
 */
class OrderException extends RuntimeException implements ApiError
{
    public function __construct(string $message, public readonly string $reasonCode = 'order_failed')
    {
        parent::__construct($message);
    }

    public static function notCancellable(): self
    {
        return new self(trans('order::front.errors.not_cancellable'), 'not_cancellable');
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
