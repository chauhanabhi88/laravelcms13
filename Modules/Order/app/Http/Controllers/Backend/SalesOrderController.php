<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Order\Events\OrderConfirmed;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Listeners\SendOrderPlacedNotifications;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderNote;
use Modules\Order\Repositories\SalesOrderRepository;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Support\OrderOptions;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin order management (FR-ORD-008): grid with filters (status, payment, reseller, date,
 * pincode, seller), CSV export, the detail with seller orders, items, payments, history and
 * internal notes, manual cancellation and re-sending the order notifications.
 */
class SalesOrderController extends BackendController
{
    public function __construct(private SalesOrderRepository $orders)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('order.cache.sales_order'), $request->input('per_page'))]);

            return view('order::backend.sales_order.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('order.cache.sales_order'), $request->input('per_page'))]);
            setFilterSession(config('order.cache.sales_order'), $request);

            $content = view('order::backend.sales_order.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.sales_order.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request, OrderCancellationService $cancellations): View|RedirectResponse
    {
        try {
            $order = $this->findOrder($request);
            $order->load([
                'customer',
                'addresses',
                'sellerOrders.seller',
                'sellerOrders.warehouse',
                'sellerOrders.items',
                'sellerOrders.statusHistories',
                'payments',
                'notes',
                'statusHistories',
            ]);

            return view('order::backend.sales_order.show', [
                'order' => $order,
                'statuses' => OrderOptions::salesOrderStatuses(),
                'sellerStatuses' => OrderOptions::sellerOrderStatuses(),
                'canCancel' => $order->sellerOrders->contains(fn ($sellerOrder): bool => $cancellations->canCancel($sellerOrder, OrderCancellationService::BY_ADMIN)),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.sales_order.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * The filtered grid as CSV (streamed in chunks).
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->orders->filter($request)->with(['customer', 'shippingAddress'])->orderByDesc('id');
        $statuses = OrderOptions::salesOrderStatuses();

        return response()->streamDownload(function () use ($query, $statuses): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order No', 'Placed At', 'Customer', 'Phone', 'Status', 'Payment', 'Payment Status', 'Items', 'Total', 'Reseller', 'Pincode', 'Channel']);

            $query->chunk(500, function ($orders) use ($out, $statuses): void {
                foreach ($orders as $order) {
                    /** @var SalesOrder $order */
                    fputcsv($out, [
                        $order->order_no,
                        $order->placed_at?->format('Y-m-d H:i'),
                        $order->customer?->displayName(),
                        $order->customer?->phone,
                        $statuses[$order->status] ?? $order->status,
                        OrderOptions::paymentMethod($order->payment_method),
                        $order->payment_status,
                        $order->items_count,
                        $order->grand_total->toDecimal(),
                        $order->isResellerOrder() ? 'Yes' : 'No',
                        $order->shippingAddress?->pincode,
                        $order->channel,
                    ]);
                }
            });

            fclose($out);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function cancel(Request $request, OrderCancellationService $cancellations): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
            'seller_order_id' => ['nullable', 'integer'],
        ]);

        try {
            $order = $this->findOrder($request);
            $admin = Auth::user();

            if (! empty($validated['seller_order_id'])) {
                $sellerOrder = $order->sellerOrders()->whereKey((int) $validated['seller_order_id'])->firstOrFail();
                $cancellations->cancelItems($sellerOrder, null, OrderCancellationService::BY_ADMIN, $validated['reason'], $admin);
            } else {
                $cancellations->cancelOrder($order, OrderCancellationService::BY_ADMIN, $validated['reason'], $admin);
            }
        } catch (OrderException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sales_order.show', updateUrlParams([$order->id]))->with('success', trans('order::sales_order.messages.cancelled'));
    }

    public function note(Request $request): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        try {
            $order = $this->findOrder($request);
            $admin = Auth::user();

            SalesOrderNote::query()->create([
                'sales_order_id' => $order->id,
                'user_id' => $admin?->getKey(),
                'author_name' => $admin?->name,
                'note' => $validated['note'],
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sales_order.show', updateUrlParams([$order->id]))->with('success', trans('order::sales_order.messages.note_added'));
    }

    /**
     * Send the "order placed" (customer) and "new order" (seller) messages again.
     */
    public function resendNotification(Request $request, SendOrderPlacedNotifications $notifications): RedirectResponse
    {
        try {
            $order = $this->findOrder($request);

            if ($order->isAwaitingPayment() || $order->status === SalesOrder::STATUS_CANCELLED) {
                throw new \RuntimeException(trans('order::sales_order.messages.not_confirmed'));
            }

            $notifications->handle(new OrderConfirmed($order));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sales_order.show', updateUrlParams([$order->id]))->with('success', trans('order::sales_order.messages.notification_sent'));
    }

    protected function findOrder(Request $request): SalesOrder
    {
        $order = $this->orders->find((int) $request->route('id'));

        if (! $order instanceof SalesOrder) {
            throw new \RuntimeException(trans('order::sales_order.messages.not_found'));
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'statuses' => OrderOptions::salesOrderStatuses(),
            'methods' => OrderOptions::paymentMethods(),
            'yes_no' => [(string) config('core.yes') => trans('core::core.labels.yes'), (string) config('core.no') => trans('core::core.labels.no')],
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->orders->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->orders->getFilters($request, $options),
        ] + $options;
    }
}
