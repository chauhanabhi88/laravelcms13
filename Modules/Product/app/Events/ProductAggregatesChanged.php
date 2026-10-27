<?php

declare(strict_types=1);

namespace Modules\Product\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The denormalised price / stock / discount / primary image columns of a product changed
 * (M06 re-indexes the listing card).
 */
class ProductAggregatesChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $changed  column names that changed
     */
    public function __construct(
        public readonly int $productId,
        public readonly array $changed = [],
    ) {}
}
