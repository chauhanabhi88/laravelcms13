<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Order\Http\Requests\ForceSellerOrderStatusRequest;
use Modules\Order\Models\SalesOrderNote;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Repositories\SellerOrderRepository;
use Modules\Order\Services\OrderStatusService;
use Modules\Order\Support\OrderOptions;

/**
 * Every seller order across sellers (FR-ORD-008), with an SLA-breach filter. Fulfilment
 * actions belong to the supplier panel (M14); Ops may only force a status along the state
 * machine with a reason (admin.seller_order.force_status), recorded in the status history and
 * as an internal note on the order.
 */
class SellerOrderController extends BackendController
{
    public function __construct(private SellerOrderRepository $sellerOrders)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('order.cache.seller_order'), $request->input('per_page'))]);

            return view('order::backend.seller_order.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('order.cache.seller_order'), $request->input('per_page'))]);
            setFilterSession(config('order.cache.seller_order'), $request);

            $content = view('order::backend.seller_order.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.seller_order.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request): View|RedirectResponse
    {
        try {
            $sellerOrder = $this->sellerOrders->find((int) $request->route('id'));

            if (! $sellerOrder instanceof SellerOrder) {
                throw new \RuntimeException(trans('order::seller_order.messages.not_found'));
            }

            $sellerOrder->load(['salesOrder.customer', 'salesOrder.shippingAddress', 'seller', 'warehouse', 'items', 'statusHistories']);

            $statuses = OrderOptions::sellerOrderStatuses();
            $next = array_values(array_diff(SellerOrder::transitions()[$sellerOrder->status] ?? [], [SellerOrder::STATUS_CANCELLED]));

            return view('order::backend.seller_order.show', [
                'sellerOrder' => $sellerOrder,
                'statuses' => $statuses,
                'forceStatuses' => array_intersect_key($statuses, array_flip($next)),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.seller_order.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function forceStatus(ForceSellerOrderStatusRequest $request, OrderStatusService $statuses): RedirectResponse
    {
        try {
            $sellerOrder = $this->sellerOrders->find((int) $request->route('id'));

            if (! $sellerOrder instanceof SellerOrder) {
                throw new \RuntimeException(trans('order::seller_order.messages.not_found'));
            }

            $to = (string) $request->validated('status');
            $from = $sellerOrder->status;
            $reason = trim((string) $request->validated('reason'));
            $user = $request->user();

            $statuses->transition($sellerOrder, $to, $reason, $user, null, ['forced' => true, 'from' => $from]);

            SalesOrderNote::query()->create([
                'sales_order_id' => $sellerOrder->sales_order_id,
                'user_id' => $user?->getKey(),
                'author_name' => $user?->getAttribute('name'),
                'note' => mb_substr(trans('order::seller_order.labels.force_status').' '.$sellerOrder->sub_order_no.': '.OrderOptions::sellerOrderStatus($from).' → '.OrderOptions::sellerOrderStatus($to).'. '.$reason, 0, 2000),
            ]);

            return redirect()->back()->with('success', trans('order::seller_order.messages.status_forced', ['status' => OrderOptions::sellerOrderStatus($to)]));
        } catch (InvalidStateTransition) {
            return redirect()->back()->withInput()->with('error', trans('order::seller_order.messages.status_not_allowed', [
                'from' => OrderOptions::sellerOrderStatus((string) ($sellerOrder->status ?? '')),
                'to' => OrderOptions::sellerOrderStatus((string) $request->validated('status')),
            ]));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'statuses' => OrderOptions::sellerOrderStatuses(),
            'sla' => ['breached' => trans('order::seller_order.labels.sla_breached')],
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->sellerOrders->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->sellerOrders->getFilters($request, $options),
        ] + $options;
    }
}
