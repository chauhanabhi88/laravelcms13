<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Modules\Customer\Models\Customer;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Contracts\PayableOrderResolver;

/**
 * The {orderNo} route parameter as an order of the signed-in customer; anything else is a
 * 404 so order numbers cannot be probed. The parameter is read by name because the optional
 * {locale} prefix shifts positional controller arguments.
 */
trait ResolvesCustomerOrder
{
    protected function customerOrder(Request $request, ?Customer $customer): PayableOrder
    {
        $orderNo = (string) $request->route('orderNo');
        $order = $customer === null || $orderNo === '' ? null : app(PayableOrderResolver::class)->findByOrderNo($orderNo);

        if ($order === null || $order->payableCustomer()->id !== $customer?->id) {
            abort(404);
        }

        return $order;
    }
}
