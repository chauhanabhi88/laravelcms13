<?php

declare(strict_types=1);

namespace Modules\Seller\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

/**
 * Seller lifecycle event. Dispatched after the surrounding transaction commits.
 */
abstract class SellerEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Seller $seller,
        public ?string $reason = null,
        public ?User $admin = null,
    ) {}
}
