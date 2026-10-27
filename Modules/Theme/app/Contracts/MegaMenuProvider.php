<?php

declare(strict_types=1);

namespace Modules\Theme\Contracts;

/**
 * Supplies the storefront category tree for the mega-menu, mobile drawer and footer.
 *
 * Theme binds a null implementation; the Catalog module (M02) rebinds it to the real tree.
 */
interface MegaMenuProvider
{
    /**
     * @return array<int, array{id: int, name: string, url: string, image: ?string, children: array<int, mixed>}>
     */
    public function tree(int $depth = 3): array;
}
