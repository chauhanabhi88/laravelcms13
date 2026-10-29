<?php

declare(strict_types=1);

namespace Modules\Review\Support;

use Modules\Customer\Models\Customer;
use Modules\Order\Contracts\OrderReviews;
use Modules\Order\DTO\ItemReviewState;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\Product;
use Modules\Review\Models\ProductReview;

/**
 * Review states for the customer's order pages (plan M16 §7): the rating already given (with
 * an edit link inside the edit window) or the "Rate product" link of each delivered item.
 * Same rules as ReviewEligibilityService, in two queries per order.
 */
class ReviewsForOrders implements OrderReviews
{
    public function forOrder(SalesOrder $order, Customer $customer): array
    {
        if ((int) $order->customer_id !== (int) $customer->id) {
            return [];
        }

        $order->loadMissing('sellerOrders.items');

        /** @var list<SalesOrderItem> $items */
        $items = [];

        foreach ($order->sellerOrders as $sellerOrder) {
            if (! in_array($sellerOrder->status, SellerOrder::DELIVERED_STATUSES, true)) {
                continue;
            }

            foreach ($sellerOrder->items as $item) {
                if (! $item->isCancelled() && $item->cancelled_qty < $item->quantity) {
                    $items[] = $item;
                }
            }
        }

        if ($items === []) {
            return [];
        }

        $itemIds = array_map(fn (SalesOrderItem $item): int => $item->id, $items);
        $reviews = ProductReview::query()->whereIn('sales_order_item_id', $itemIds)->get(['id', 'sales_order_item_id', 'rating', 'status', 'created_at'])->keyBy('sales_order_item_id');
        $liveProducts = array_flip(Product::query()->whereIn('id', array_unique(array_map(fn (SalesOrderItem $item): int => $item->product_id, $items)))->pluck('id')->all());
        $editWindow = ReviewSettings::editWindowDays();
        $states = [];

        foreach ($items as $item) {
            /** @var ProductReview|null $review */
            $review = $reviews->get($item->id);

            if ($review !== null) {
                $editable = ! $review->isHidden() && now()->lessThanOrEqualTo($review->editableUntil($editWindow));
                $states[$item->id] = new ItemReviewState(
                    $item->id,
                    rating: $review->rating,
                    editUrl: $editable ? frontRoute('customer.reviews.edit', ['review' => $review->id]) : null,
                    statusLabel: $review->isPublished() ? null : ReviewOptions::customerStatus($review->status),
                );
            } elseif (isset($liveProducts[$item->product_id])) {
                $states[$item->id] = new ItemReviewState($item->id, createUrl: frontRoute('customer.reviews.create', ['orderItem' => $item->id]));
            }
        }

        return $states;
    }
}
