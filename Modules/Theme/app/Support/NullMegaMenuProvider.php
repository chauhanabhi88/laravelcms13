<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

use Modules\Theme\Contracts\MegaMenuProvider;

/**
 * Used until the Catalog module provides categories: the header falls back to the configured nav links.
 */
class NullMegaMenuProvider implements MegaMenuProvider
{
    public function tree(int $depth = 3): array
    {
        return [];
    }
}
