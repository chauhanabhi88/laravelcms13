<?php

declare(strict_types=1);

namespace Modules\Review\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Customer\Models\Customer;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Review\Services\ReviewEligibilityService;
use Modules\Review\Support\ReviewNotifier;
use Modules\Review\Support\ReviewSettings;

/**
 * The post-delivery review prompt (FR-REV-007): prompt_delay_days after a package is
 * delivered, "review.request" (promotional: the customer's preferences and quiet hours
 * apply) for its first item still waiting for a review. Re-checked when the delayed job runs,
 * so a package cancelled into RTO or already reviewed sends nothing.
 */
class PromptReviewAfterDelivery implements ShouldQueue
{
    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
        private readonly ReviewNotifier $notifier,
    ) {}

    public function handle(SellerOrderStatusChanged $event): void
    {
        $sellerOrder = SellerOrder::query()->with(['salesOrder', 'items'])->find($event->sellerOrder->id);

        if ($sellerOrder === null || $sellerOrder->salesOrder === null || ! in_array($sellerOrder->status, SellerOrder::DELIVERED_STATUSES, true)) {
            return;
        }

        $customer = Customer::query()->find($sellerOrder->salesOrder->customer_id);

        if ($customer === null) {
            return;
        }

        $item = $sellerOrder->items
            ->each(fn (SalesOrderItem $item) => $item->setRelation('salesOrder', $sellerOrder->salesOrder)->setRelation('sellerOrder', $sellerOrder))
            ->first(fn (SalesOrderItem $item): bool => $this->eligibility->canReview($item, $customer));

        if ($item !== null) {
            $this->notifier->requestReview($customer, $item, $sellerOrder->salesOrder->order_no);
        }
    }

    /**
     * Only first deliveries: a package back at "delivered" after a closed return (M15) was
     * prompted already.
     */
    public function shouldQueue(SellerOrderStatusChanged $event): bool
    {
        return $event->to === SellerOrder::STATUS_DELIVERED && $event->from !== SellerOrder::STATUS_RETURN_REQUESTED;
    }

    public function withDelay(SellerOrderStatusChanged $event): int
    {
        return ReviewSettings::promptDelayDays() * 86400;
    }

    public function viaQueue(): string
    {
        return 'notifications';
    }
}
