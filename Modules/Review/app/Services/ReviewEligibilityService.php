<?php

declare(strict_types=1);

namespace Modules\Review\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\Product;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Support\ReviewSettings;

/**
 * Who may review what (FR-REV-001, plan M16 §5): the customer's own order item whose package
 * was delivered (also later: return requested, returned, completed), not cancelled, of a
 * product that still exists, and not reviewed yet. A returned item may still be reviewed
 * (the review is flagged "Returned"). Editing is allowed for edit_window_days after writing.
 */
class ReviewEligibilityService
{
    /**
     * Why the customer cannot review the item, or null when they can. Reason codes match the
     * review::front.errors keys.
     */
    public function reason(SalesOrderItem $item, Customer $customer): ?string
    {
        $item->loadMissing(['salesOrder', 'sellerOrder']);

        if ($item->salesOrder === null || (int) $item->salesOrder->customer_id !== (int) $customer->id) {
            return 'not_found';
        }

        if ($item->isCancelled() || $item->cancelled_qty >= $item->quantity
            || $item->sellerOrder === null || ! in_array($item->sellerOrder->status, SellerOrder::DELIVERED_STATUSES, true)) {
            return 'not_delivered';
        }

        if (ProductReview::query()->where('sales_order_item_id', $item->id)->exists()) {
            return 'already_reviewed';
        }

        if (! Product::query()->whereKey($item->product_id)->exists()) {
            return 'product_unavailable';
        }

        return null;
    }

    public function canReview(SalesOrderItem $item, Customer $customer): bool
    {
        return $this->reason($item, $customer) === null;
    }

    /**
     * @throws ReviewException
     */
    public function assertCanReview(SalesOrderItem $item, Customer $customer): void
    {
        if (($reason = $this->reason($item, $customer)) !== null) {
            throw ReviewException::because($reason);
        }
    }

    /**
     * Every unit the customer kept went back to the seller.
     */
    public function isReturned(SalesOrderItem $item): bool
    {
        $kept = $item->quantity - $item->cancelled_qty;

        return $item->status === SalesOrderItem::STATUS_RETURNED || ($kept > 0 && $item->returned_qty >= $kept);
    }

    /**
     * Why the customer cannot edit the review, or null.
     */
    public function editReason(ProductReview $review, Customer $customer): ?string
    {
        if ($review->customer_id !== (int) $customer->id) {
            return 'not_found';
        }

        if ($review->isHidden()) {
            return 'review_hidden';
        }

        if (now()->greaterThan($review->editableUntil(ReviewSettings::editWindowDays()))) {
            return 'edit_window_closed';
        }

        return null;
    }

    public function canEdit(ProductReview $review, Customer $customer): bool
    {
        return $this->editReason($review, $customer) === null;
    }

    /**
     * The customer's delivered items still waiting for a review, newest delivery first.
     *
     * @return Collection<int, SalesOrderItem>
     */
    public function pendingItems(Customer $customer, int $limit = 50): Collection
    {
        return $this->pendingQuery($customer)
            ->with(['salesOrder', 'sellerOrder'])
            ->orderByDesc('seller_order.delivered_at')
            ->orderByDesc('sales_order_item.id')
            ->limit($limit)
            ->get();
    }

    public function pendingCount(Customer $customer): int
    {
        return $this->pendingQuery($customer)->count();
    }

    /**
     * @return Builder<SalesOrderItem>
     */
    protected function pendingQuery(Customer $customer): Builder
    {
        return SalesOrderItem::query()
            ->select('sales_order_item.*')
            ->join('sales_order', 'sales_order.id', '=', 'sales_order_item.sales_order_id')
            ->join('seller_order', 'seller_order.id', '=', 'sales_order_item.seller_order_id')
            ->join('product', 'product.id', '=', 'sales_order_item.product_id')
            ->where('sales_order.customer_id', $customer->id)
            ->whereIn('seller_order.status', SellerOrder::DELIVERED_STATUSES)
            ->where('sales_order_item.status', '!=', SalesOrderItem::STATUS_CANCELLED)
            ->whereColumn('sales_order_item.cancelled_qty', '<', 'sales_order_item.quantity')
            ->whereNull('product.deleted_at')
            ->whereNotExists(fn (QueryBuilder $query) => $query->selectRaw('1')
                ->from('product_review')
                ->whereColumn('product_review.sales_order_item_id', 'sales_order_item.id'));
    }
}
