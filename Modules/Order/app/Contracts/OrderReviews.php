<?php

declare(strict_types=1);

namespace Modules\Order\Contracts;

use Modules\Customer\Models\Customer;
use Modules\Order\DTO\ItemReviewState;
use Modules\Order\Models\SalesOrder;

/**
 * What the customer's order pages need from reviews (M16): per order item, whether it can be
 * rated ("Rate product" stars) or how the customer rated it.
 *
 * Bound to NullOrderReviews (bindIf) until the Review module binds its own: Review depends on
 * Order, never the other way round. Load the order's sellerOrders.items first.
 */
interface OrderReviews
{
    /**
     * @return array<int, ItemReviewState> keyed by sales order item id
     */
    public function forOrder(SalesOrder $order, Customer $customer): array;
}
