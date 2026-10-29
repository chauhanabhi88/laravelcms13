<?php

declare(strict_types=1);

namespace Modules\Review\Support;

use Modules\Customer\Models\Customer;
use Modules\Notification\Services\NotificationHub;
use Modules\Order\Models\SalesOrderItem;
use Modules\Review\Models\ProductReview;
use Throwable;

/**
 * Review notifications from the M09 catalogue. A notification failure never undoes the
 * review action, so every send is reported and swallowed.
 */
class ReviewNotifier
{
    public function __construct(private readonly NotificationHub $hub) {}

    /**
     * The post-delivery prompt (FR-REV-007) for one delivered item of the order.
     */
    public function requestReview(Customer $customer, SalesOrderItem $item, string $orderNo): void
    {
        try {
            $this->hub->event('review.request', $customer, [
                'product_name' => $item->name,
                'order_no' => $orderNo,
                'url' => frontRoute('customer.reviews.create', ['orderItem' => $item->id]),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function rejected(ProductReview $review): void
    {
        try {
            $customer = Customer::query()->find($review->customer_id);

            if ($customer === null) {
                return;
            }

            $this->hub->event('review.rejected', $customer, [
                'product_name' => $this->productName($review),
                'reason' => (string) $review->rejection_reason,
                'url' => frontRoute('customer.reviews.index', [['tab' => 'reviewed']]),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * A published review reached the report threshold: it is back in the moderation queue.
     */
    public function reported(ProductReview $review): void
    {
        try {
            $this->hub->eventForAdmins('admin.review.reported', [
                'product_name' => $this->productName($review),
                'reports' => (string) $review->report_count,
                'url' => route('admin.product_review.show', updateUrlParams([$review->id])),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function productName(ProductReview $review): string
    {
        $review->loadMissing('orderItem');

        return (string) $review->orderItem?->name;
    }
}
