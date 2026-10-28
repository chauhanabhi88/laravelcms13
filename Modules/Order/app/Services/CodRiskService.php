<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use Illuminate\Support\Carbon;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Support\OrderSettings;
use Modules\Payment\Contracts\CodRiskCheck;
use Modules\Payment\Models\Payment;

/**
 * COD risk rules of the Order module (FR-ORD-009, plan M12 §5.6), added to the Payment
 * module's checks (COD enabled, pincode blacklist, order value, cod_blocked, daily limit)
 * through the CodEligibilityService::RISK_CHECK_TAG tag:
 *
 * - open COD orders: a customer with max_open_cod_orders undelivered COD orders gets no more;
 * - RTOs: when a seller order of the customer goes back to origin (recordRto), the customer
 *   is COD-blocked once rto_block_threshold RTO orders fall within rto_window_days. Admins
 *   lift the block from the customer grid (M08 toggle); only a new RTO blocks again.
 */
class CodRiskService implements CodRiskCheck
{
    /**
     * COD orders that are placed but not yet delivered, cancelled or returned.
     */
    private const OPEN_COD_STATUSES = [
        SalesOrder::STATUS_PENDING_COD_VERIFICATION,
        SalesOrder::STATUS_CONFIRMED,
        SalesOrder::STATUS_PARTIALLY_SHIPPED,
        SalesOrder::STATUS_SHIPPED,
        SalesOrder::STATUS_PARTIALLY_CANCELLED,
    ];

    public function blockReason(Customer $customer, Money $orderTotal, ?string $pincode): ?string
    {
        $limit = OrderSettings::maxOpenCodOrders();

        if ($limit > 0 && $this->openCodOrders($customer) >= $limit) {
            return trans('order::front.cod_risk.open_orders', ['count' => $limit]);
        }

        return null;
    }

    public function openCodOrders(Customer $customer): int
    {
        return SalesOrder::query()
            ->forCustomer($customer->id)
            ->where('payment_method', Payment::METHOD_COD)
            ->whereIn('status', self::OPEN_COD_STATUSES)
            ->count();
    }

    /**
     * Orders of the customer with a seller order that went back to origin within the window.
     */
    public function recentRtoOrders(Customer $customer, ?Carbon $now = null): int
    {
        return SellerOrder::query()
            ->whereIn('status', [SellerOrder::STATUS_RTO_INITIATED, SellerOrder::STATUS_RTO_DELIVERED])
            ->where('updated_at', '>=', ($now ?? Carbon::now())->copy()->subDays(OrderSettings::rtoWindowDays()))
            ->whereHas('salesOrder', fn ($query) => $query->where('customer_id', $customer->id))
            ->distinct()
            ->count('sales_order_id');
    }

    /**
     * Count a new RTO against the customer (SellerOrderStatusChanged → rto_initiated) and block
     * COD when the threshold is reached. Returns whether the customer is now blocked.
     */
    public function recordRto(Customer $customer, ?Carbon $now = null): bool
    {
        $lifetime = SellerOrder::query()
            ->whereIn('status', [SellerOrder::STATUS_RTO_INITIATED, SellerOrder::STATUS_RTO_DELIVERED])
            ->whereHas('salesOrder', fn ($query) => $query->where('customer_id', $customer->id))
            ->distinct()
            ->count('sales_order_id');

        $threshold = OrderSettings::rtoBlockThreshold();
        $block = $threshold > 0 && $this->recentRtoOrders($customer, $now) >= $threshold;

        $customer->forceFill(array_filter([
            'rto_count' => max((int) $customer->rto_count, $lifetime),
            'cod_blocked' => $block ? (int) config('core.yes') : null,
        ], fn ($value): bool => $value !== null))->save();

        return $customer->isCodBlocked();
    }
}
