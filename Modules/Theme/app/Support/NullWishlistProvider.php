<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

use Modules\Theme\Contracts\WishlistProvider;

class NullWishlistProvider implements WishlistProvider
{
    public function ids(): array
    {
        return [];
    }
}
