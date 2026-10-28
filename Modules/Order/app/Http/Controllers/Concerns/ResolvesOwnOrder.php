<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;

/**
 * The {orderNo} route parameter as an order the customer may see; anything else is a 404 so
 * order numbers cannot be probed. Read by name: the optional {locale} prefix shifts
 * positional controller arguments.
 */
trait ResolvesOwnOrder
{
    /**
     * @param  list<string>  $with
     */
    protected function ownOrder(Request $request, ?Customer $customer, array $with = [], string $ability = 'view'): SalesOrder
    {
        $orderNo = (string) $request->route('orderNo');
        $order = $customer === null || $orderNo === '' ? null : SalesOrder::query()->with($with)->where('order_no', $orderNo)->first();

        if ($order === null || ! Gate::forUser($customer)->allows($ability, $order)) {
            abort(404);
        }

        return $order;
    }
}
