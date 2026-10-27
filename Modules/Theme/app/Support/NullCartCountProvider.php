<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

use Modules\Theme\Contracts\CartCountProvider;

class NullCartCountProvider implements CartCountProvider
{
    public function count(): int
    {
        return 0;
    }
}
