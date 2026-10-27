<?php

declare(strict_types=1);

namespace Modules\Customer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Customer\Models\Customer;

/**
 * A customer signed in on the storefront (OTP or password). $previousSessionId is the guest
 * session id before regeneration, so the Cart module (M10) can merge the guest cart (FR-CUS-009).
 */
final class CustomerLoggedIn
{
    use Dispatchable;

    public const METHOD_OTP_SMS = 'otp_sms';

    public const METHOD_OTP_EMAIL = 'otp_email';

    public const METHOD_PASSWORD = 'password';

    public const METHOD_API = 'api';

    public function __construct(
        public readonly Customer $customer,
        public readonly ?string $previousSessionId,
        public readonly string $method,
    ) {}
}
