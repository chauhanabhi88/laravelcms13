<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\Payment;

/**
 * Labels and badge / chip colours for order statuses, shared by the admin grids, the
 * storefront order pages and the API.
 */
final class OrderOptions
{
    /**
     * @return array<string, string>
     */
    public static function salesOrderStatuses(): array
    {
        return self::labels(SalesOrder::STATUSES, 'order::order.status.sales_order.');
    }

    /**
     * @return array<string, string>
     */
    public static function sellerOrderStatuses(): array
    {
        return self::labels(SellerOrder::STATUSES, 'order::order.status.seller_order.');
    }

    /**
     * @return array<string, string>
     */
    public static function paymentMethods(): array
    {
        return [
            Payment::METHOD_COD => trans('order::order.payment_methods.cod'),
            Payment::METHOD_RAZORPAY => trans('order::order.payment_methods.razorpay'),
            Payment::METHOD_FAKE => trans('order::order.payment_methods.fake'),
        ];
    }

    public static function salesOrderStatus(string $status): string
    {
        return self::salesOrderStatuses()[$status] ?? $status;
    }

    public static function sellerOrderStatus(string $status): string
    {
        return self::sellerOrderStatuses()[$status] ?? $status;
    }

    public static function paymentMethod(string $method): string
    {
        return self::paymentMethods()[$method] ?? $method;
    }

    /**
     * Bootstrap badge class suffix for the admin grids.
     */
    public static function badge(string $status): string
    {
        return match ($status) {
            SalesOrder::STATUS_CANCELLED, SellerOrder::STATUS_RTO_INITIATED, SellerOrder::STATUS_RTO_DELIVERED => 'danger',
            SalesOrder::STATUS_PARTIALLY_CANCELLED, SellerOrder::STATUS_NDR, SalesOrder::STATUS_PENDING_COD_VERIFICATION, SalesOrder::STATUS_PENDING_PAYMENT, SalesOrder::STATUS_PLACED => 'warning',
            SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_COMPLETED => 'success',
            SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_PARTIALLY_SHIPPED, SellerOrder::STATUS_OUT_FOR_DELIVERY => 'info',
            default => 'primary',
        };
    }

    /**
     * <x-theme::chip> variant for the storefront.
     */
    public static function chip(string $status): string
    {
        return match (self::badge($status)) {
            'danger' => 'danger',
            'warning' => 'deal',
            'success' => 'success',
            'info', 'primary' => 'accent',
            default => 'neutral',
        };
    }

    /**
     * @param  list<string>  $statuses
     * @return array<string, string>
     */
    private static function labels(array $statuses, string $prefix): array
    {
        $labels = [];

        foreach ($statuses as $status) {
            $labels[$status] = trans($prefix.$status);
        }

        return $labels;
    }
}
