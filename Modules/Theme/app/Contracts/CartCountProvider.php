<?php

declare(strict_types=1);

namespace Modules\Theme\Contracts;

/**
 * Number of items in the current visitor's cart, for the header badge. Rebound by the Cart module (M10).
 */
interface CartCountProvider
{
    public function count(): int;
}
