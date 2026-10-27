<?php

declare(strict_types=1);

namespace Modules\Product\Exceptions;

use RuntimeException;

/**
 * A product business rule refused the operation; the message is translated and safe to show.
 */
class ProductException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $key, array $replace = []): self
    {
        return new static(trans('product::product.messages.'.$key, $replace));
    }
}
