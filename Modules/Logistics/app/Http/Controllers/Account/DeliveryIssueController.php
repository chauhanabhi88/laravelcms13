<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Customer\Models\Customer;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Http\Requests\NdrActionRequest;
use Modules\Logistics\Services\NdrService;
use Modules\Logistics\Support\DeliveryIssues;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Order\Models\SellerOrder;

/**
 * The customer's answer to a failed delivery attempt (FR-LOG-008): re-attempt, re-attempt at
 * a new address / phone, or cancel (the package returns to the seller). The panel sits on the
 * order page (logistics::frontend.partials.delivery-issue); the decision goes to the courier
 * through NdrService::act(), exactly as from the admin NDR queue.
 */
class DeliveryIssueController extends Controller
{
    use ResolvesOwnOrder;

    public function store(NdrActionRequest $request, NdrService $ndr, DeliveryIssues $issues): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();
        $order = $this->ownOrder($request, $customer);
        $sellerOrder = SellerOrder::query()->where('sales_order_id', $order->id)->find((int) $request->route('sellerOrder'));
        $case = $sellerOrder !== null ? $issues->openFor($sellerOrder) : null;

        abort_if($case === null, 404);

        $back = redirect(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]));

        try {
            $ndr->act($case, (string) $request->validated('action'), $request->actionPayload(), 'customer:'.$customer->getKey());
        } catch (ShipmentException $e) {
            return $back->with('error', $e->getMessage());
        } catch (CourierException $e) {
            report($e);

            return $back->with('error', trans('logistics::front.delivery_issue.failed'));
        }

        return $back->with('success', trans('logistics::front.delivery_issue.done.'.$request->validated('action')));
    }
}
