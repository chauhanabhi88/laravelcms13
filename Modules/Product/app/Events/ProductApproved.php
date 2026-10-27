<?php

declare(strict_types=1);

namespace Modules\Product\Events;

use Illuminate\Database\Eloquent\Model;
use Modules\Product\Models\Product;

/**
 * QC approved a submission (the product went live) or a live product's pending changes.
 * M09 notifies the seller; M06 indexes the product for search.
 */
class ProductApproved extends ProductEvent
{
    public function __construct(
        Product $product,
        ?string $reason = null,
        ?Model $actor = null,
        public bool $changesOnly = false,
    ) {
        parent::__construct($product, $reason, $actor);
    }
}
