<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Order\Http\Requests\CancelOrderRequest;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Payment\Services\PaymentService;

/**
 * My Orders (basic, plan M12 §7): the list with a status chip per package, the order detail
 * and cancellation before shipment. M15 adds the tracking timeline and returns.
 */
class OrderController extends Controller
{
    use ResolvesOwnOrder;

    public function __construct(private readonly OrderCancellationService $cancellations) {}

    public function index(): View
    {
        $orders = SalesOrder::query()
            ->forCustomer($this->customer()->id)
            ->with(['sellerOrders.seller', 'sellerOrders.items'])
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('order::frontend.account.orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, PaymentService $payments): View
    {
        $order = $this->ownOrder($request, $this->customer(), ['sellerOrders.seller', 'sellerOrders.items', 'shippingAddress', 'billingAddress']);

        return view('order::frontend.account.orders.show', [
            'order' => $order,
            'payment' => $payments->latestPayment($order->id),
            'cancellableItemIds' => $this->cancellations->cancellableItemIds($order),
            'cancelReasons' => (array) config('order.cancel_reasons', []),
        ]);
    }

    public function cancel(CancelOrderRequest $request): RedirectResponse
    {
        $customer = $this->customer();
        $order = $this->ownOrder($request, $customer, [], 'cancel');
        $back = redirect(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]));

        try {
            $count = $this->cancellations->cancelForCustomer($order, $request->itemIds(), $request->reasonText(), $customer);
        } catch (OrderException $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back->with('success', trans_choice('order::front.cancel.done', $count, ['count' => $count]));
    }

    protected function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return $customer;
    }
}
