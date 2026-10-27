<?php

declare(strict_types=1);

namespace Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * A taxonomy rule was broken (cycle, depth, category in use, too many variant axes...).
 * The message is translated and safe to show to the admin.
 */
class CatalogException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $key, array $replace = []): self
    {
        return new self(trans('catalog::catalog.errors.'.$key, $replace));
    }
}
