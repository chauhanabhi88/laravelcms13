<?php

declare(strict_types=1);

namespace Modules\Customer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Customer\Models\Customer;

/**
 * A new account was created by OTP verification (FR-CUS-001). Fired before CustomerLoggedIn.
 */
final class CustomerRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $method,
    ) {}
}
