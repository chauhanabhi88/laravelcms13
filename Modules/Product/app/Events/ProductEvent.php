<?php

declare(strict_types=1);

namespace Modules\Product\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Product\Models\Product;

/**
 * Product moderation event. Dispatched after the surrounding transaction commits.
 *
 * $actor is the admin User, the SellerUser, or null for the system.
 */
abstract class ProductEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Product $product,
        public ?string $reason = null,
        public ?Model $actor = null,
    ) {}
}
