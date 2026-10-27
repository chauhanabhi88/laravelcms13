<?php

declare(strict_types=1);

namespace Modules\Product\Events;

use Illuminate\Database\Eloquent\Model;
use Modules\Product\Models\Product;

/**
 * QC rejected a submission, or a live product's pending changes (the live content stays).
 */
class ProductRejected extends ProductEvent
{
    /**
     * @param  list<string>  $reasonCodes  keys of config('product.qc_reason_templates')
     */
    public function __construct(
        Product $product,
        ?string $reason = null,
        ?Model $actor = null,
        public array $reasonCodes = [],
        public bool $changesOnly = false,
    ) {
        parent::__construct($product, $reason, $actor);
    }
}
