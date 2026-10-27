<?php

declare(strict_types=1);

namespace Modules\Product\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Product\Models\Product;

/**
 * Any product status transition, fired by HasStateMachine (Product::$transitionEvents) after
 * the surrounding transaction commits. Covers the seller's pause / resume, which have no
 * moderation event of their own; M06 keeps the storefront search index in step with it.
 */
class ProductStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Product $product,
        public string $from,
        public string $to,
    ) {}
}
