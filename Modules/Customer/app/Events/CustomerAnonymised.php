<?php

declare(strict_types=1);

namespace Modules\Customer\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A deleted account's PII was cleared (FR-CUS-008). Fired after commit so other modules can
 * clear their own copies of the customer's name (e.g. review authors, M16).
 */
final class CustomerAnonymised implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $customerId) {}
}
