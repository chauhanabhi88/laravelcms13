<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use Illuminate\Support\Facades\Route;
use Modules\Notification\Services\NotificationHub;
use Modules\Product\Events\ProductApproved;
use Modules\Product\Events\ProductRejected;
use Modules\Seller\Models\Seller;
use Throwable;

/**
 * QC outcomes for the seller's owner and catalog staff ("seller.product.approved|rejected").
 */
class SendProductModerationNotifications
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function handle(ProductApproved|ProductRejected $event): void
    {
        try {
            $product = $event->product->loadMissing('translations');
            $seller = Seller::query()->find($product->seller_id);

            if (! $seller instanceof Seller) {
                return;
            }

            $this->hub->eventForSeller(
                $event instanceof ProductApproved ? 'seller.product.approved' : 'seller.product.rejected',
                $seller,
                [
                    'product_name' => (string) ($product->name ?: '#'.$product->id),
                    'reason' => (string) $event->reason,
                    'url' => Route::has('seller.product.edit') ? frontRoute('seller.product.edit', [$product->id]) : '',
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
