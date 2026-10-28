<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Repositories\SellerOrderRepository;
use Modules\Order\Support\OrderOptions;

/**
 * Every seller order across sellers (FR-ORD-008), with an SLA-breach filter. Read-only:
 * fulfilment actions belong to the supplier panel (M14).
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

            return view('order::backend.seller_order.show', [
                'sellerOrder' => $sellerOrder,
                'statuses' => OrderOptions::sellerOrderStatuses(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.seller_order.index', updateUrlParams())->with('error', $e->getMessage());
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
