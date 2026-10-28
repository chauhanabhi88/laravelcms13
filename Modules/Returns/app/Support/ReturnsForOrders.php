<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Modules\Customer\Models\Customer;
use Modules\Order\Contracts\OrderReturns;
use Modules\Order\DTO\PackageReturnState;
use Modules\Order\Models\SalesOrder;
use Modules\Returns\DTO\ReturnableItem;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnEligibilityService;

/**
 * The Order module's OrderReturns: per package the "Return / Exchange" link while something is
 * returnable (with the latest window end), and the returns raised so far.
 */
class ReturnsForOrders implements OrderReturns
{
    public function __construct(private readonly ReturnEligibilityService $eligibility) {}

    public function forOrder(SalesOrder $order, Customer $customer): array
    {
        $eligible = $this->eligibility->forOrder($order, $customer);
        $returns = ReturnRequest::query()
            ->where('sales_order_id', $order->id)
            ->orderByDesc('id')
            ->get(['id', 'return_no', 'seller_order_id', 'type', 'status'])
            ->groupBy('seller_order_id');

        $states = [];

        foreach ($order->sellerOrders as $sellerOrder) {
            $items = $eligible[$sellerOrder->id] ?? collect();
            $list = ($returns->get($sellerOrder->id) ?? collect())->map(fn (ReturnRequest $return): array => [
                'return_no' => $return->return_no,
                'type' => $return->type,
                'status' => $return->status,
                'label' => trans('returns::returns.types.'.$return->type).' · '.ReturnOptions::status($return->status),
                'chip' => ReturnOptions::chip($return->status),
                'url' => frontRoute('customer.returns.show', ['returnNo' => $return->return_no]),
            ])->values()->all();

            if ($items->isEmpty() && $list === []) {
                continue;
            }

            $states[$sellerOrder->id] = new PackageReturnState(
                $sellerOrder->id,
                $items->isEmpty() ? null : frontRoute('customer.returns.create', ['orderNo' => $order->order_no, ['seller_order' => $sellerOrder->id]]),
                $items->isEmpty() ? null : $items->max(fn (ReturnableItem $item) => $item->windowEndsAt),
                $items->isNotEmpty() && $items->every(fn (ReturnableItem $item): bool => ! $item->allows(ReturnRequest::TYPE_RETURN)),
                $list,
            );
        }

        return $states;
    }

    public function listUrl(): ?string
    {
        return frontRoute('customer.returns.index');
    }
}
