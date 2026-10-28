<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use Illuminate\Support\Facades\Route;
use Modules\Customer\Events\CustomerRegistered;
use Modules\Notification\Services\NotificationHub;
use Throwable;

/**
 * "customer.welcome" (email + in-app) for a new OTP account.
 */
class SendCustomerWelcome
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function handle(CustomerRegistered $event): void
    {
        try {
            $this->hub->event('customer.welcome', $event->customer, [
                'url' => Route::has('homepage') ? frontRoute('homepage') : url('/'),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
