<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\Contracts\OrderReturns;
use Modules\Order\Contracts\OrderReviews;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Order\Http\Requests\CancelOrderRequest;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Services\OrderFulfillmentService;
use Modules\Payment\Services\PaymentService;
use Modules\Theme\Support\StorefrontLinks;
use Modules\Theme\Support\ThemeSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * My Orders (FR-RET-001, plans M12 §7 / M15 §5.1 / M16 §7): one card per package with its
 * status, a contextual line and actions; the order detail with the shipment timeline, courier
 * and AWB, invoices, returns, "Rate product" stars per delivered item, cancellation before
 * shipment and help links.
 */
class OrderController extends Controller
{
    use ResolvesOwnOrder;

    public function __construct(
        private readonly OrderCancellationService $cancellations,
        private readonly OrderReturns $returns,
        private readonly OrderReviews $reviews,
    ) {}

    public function index(): View
    {
        $customer = $this->customer();
        $orders = SalesOrder::query()
            ->forCustomer($customer->id)
            ->with(['sellerOrders.seller', 'sellerOrders.items'])
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(10);

        $returnStates = [];
        $reviewStates = [];

        foreach ($orders as $order) {
            $returnStates += $this->returns->forOrder($order, $customer);
            $reviewStates += $this->reviews->forOrder($order, $customer);
        }

        return view('order::frontend.account.orders.index', [
            'orders' => $orders,
            'returnStates' => $returnStates,
            'reviewStates' => $reviewStates,
            'returnsUrl' => $this->returns->listUrl(),
        ]);
    }

    public function show(Request $request, PaymentService $payments, FulfilmentLogistics $logistics): View
    {
        $customer = $this->customer();
        $order = $this->ownOrder($request, $customer, ['sellerOrders.seller', 'sellerOrders.items', 'shippingAddress', 'billingAddress']);

        return view('order::frontend.account.orders.show', [
            'order' => $order,
            'payment' => $payments->latestPayment($order->id),
            'cancellableItemIds' => $this->cancellations->cancellableItemIds($order),
            'cancelReasons' => (array) config('order.cancel_reasons', []),
            'timelines' => $order->sellerOrders->mapWithKeys(fn (SellerOrder $sellerOrder): array => [$sellerOrder->id => $logistics->timeline($sellerOrder)])->all(),
            'shipments' => $logistics->shipments($order->sellerOrders->modelKeys()),
            'returnStates' => $this->returns->forOrder($order, $customer),
            'reviewStates' => $this->reviews->forOrder($order, $customer),
            'returnsUrl' => $this->returns->listUrl(),
            'helpLinks' => $this->helpLinks(),
            'supportPhone' => ThemeSettings::supportPhone(),
            'supportEmail' => ThemeSettings::supportEmail(),
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

    /**
     * The package's tax invoice (owner only; available once the seller accepted the order).
     */
    public function invoice(Request $request, OrderFulfillmentService $fulfilment): Response
    {
        $order = $this->ownOrder($request, $this->customer());
        $sellerOrder = SellerOrder::query()->where('sales_order_id', $order->id)->find((int) $request->route('sellerOrder'));
        $path = $sellerOrder !== null ? $fulfilment->invoicePath($sellerOrder) : null;

        abort_if($path === null, 404);

        return $fulfilment->disk()->download($path, str_replace('/', '-', (string) $sellerOrder->invoice_no).'.pdf');
    }

    /**
     * "Need help?" links: the storefront footer's help column (policies, FAQs, contact).
     *
     * @return list<array{url: string, label: string}>
     */
    protected function helpLinks(): array
    {
        $links = app(StorefrontLinks::class);

        return array_values(array_filter(array_map(
            fn (array $link): ?array => $links->resolve($link),
            (array) config('theme.footer.columns.help.links', []),
        )));
    }

    protected function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return $customer;
    }
}
