<?php

declare(strict_types=1);

namespace Modules\Promotion\Listeners;

use Modules\Customer\Events\CustomerRegistered;
use Modules\Promotion\Services\ReferralService;
use Throwable;

/**
 * A new customer who arrived through a referral link (cookie set by /r/{code}; API clients send
 * the X-Referral-Code header) is attached to the referrer. Never blocks the sign-up.
 */
class AttachReferralOnRegister
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function handle(CustomerRegistered $event): void
    {
        try {
            $request = request();
            $code = $request->cookie((string) config('promotion.referral_cookie', 'referral_code')) ?: $request->header('X-Referral-Code');

            if (is_string($code) && $code !== '') {
                $this->referrals->attach($event->customer, $code);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
