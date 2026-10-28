<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use Illuminate\Support\Facades\Route;
use Modules\Notification\Services\NotificationHub;
use Modules\Seller\Events\SellerSubmittedForVerification;
use Throwable;

/**
 * "admin.seller.submitted" to the admins who work the KYC queue.
 */
class NotifyAdminsOfSellerSubmission
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function handle(SellerSubmittedForVerification $event): void
    {
        try {
            $this->hub->eventForAdmins('admin.seller.submitted', [
                'store' => $event->seller->displayName(),
                'seller_code' => (string) $event->seller->code,
                'url' => Route::has('admin.seller_application.show') ? route('admin.seller_application.show', updateUrlParams([$event->seller->id])) : '',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
