<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A category, its position in the tree, or its attribute mapping changed.
 *
 * Dispatched after the surrounding transaction commits, so no request can re-cache
 * the old tree between the flush and the commit.
 */
class CatalogTreeChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ?int $categoryId = null,
        public readonly string $reason = 'updated',
    ) {}
}
