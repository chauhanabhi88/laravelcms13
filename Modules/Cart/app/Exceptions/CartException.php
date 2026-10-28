<?php

declare(strict_types=1);

namespace Modules\Cart\Exceptions;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A cart change the buyer cannot make (unavailable product, out of stock, cart full...).
 * $field is the request field the message belongs to.
 */
class CartException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'variant_id')
    {
        parent::__construct($message);
    }

    public static function unavailable(): self
    {
        return new self(trans('cart::front.errors.unavailable'));
    }

    public static function outOfStock(): self
    {
        return new self(trans('cart::front.errors.out_of_stock'));
    }

    public static function cartFull(int $max): self
    {
        return new self(trans('cart::front.errors.cart_full', ['max' => $max]));
    }

    public static function invalidQuantity(int $max): self
    {
        return new self(trans('cart::front.errors.quantity', ['max' => $max]), 'quantity');
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([$this->field => $this->getMessage()]);
    }
}
