<?php

declare(strict_types=1);

namespace Modules\Cart\Exceptions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\ApiError;
use RuntimeException;

/**
 * A cart change the buyer cannot make (unavailable product, out of stock, cart full...).
 * $field is the request field the message belongs to.
 */
class CartException extends RuntimeException implements ApiError
{
    public function __construct(string $message, public readonly string $field = 'variant_id', public readonly string $reason = 'cart_error')
    {
        parent::__construct($message);
    }

    public static function unavailable(): self
    {
        return new self(trans('cart::front.errors.unavailable'), reason: 'unavailable');
    }

    public static function outOfStock(): self
    {
        return new self(trans('cart::front.errors.out_of_stock'), reason: 'out_of_stock');
    }

    public static function cartFull(int $max): self
    {
        return new self(trans('cart::front.errors.cart_full', ['max' => $max]), reason: 'cart_full');
    }

    public static function invalidQuantity(int $max): self
    {
        return new self(trans('cart::front.errors.quantity', ['max' => $max]), 'quantity', 'invalid_quantity');
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([$this->field => $this->getMessage()]);
    }

    public function apiReason(): string
    {
        return $this->reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function apiErrors(): array
    {
        return [$this->field => [$this->getMessage()]];
    }
}
