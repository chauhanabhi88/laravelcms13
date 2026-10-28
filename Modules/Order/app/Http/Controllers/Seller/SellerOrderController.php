<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\DTO\FulfilmentResult;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Controllers\Seller\Concerns\ReadsDeskFilters;
use Modules\Order\Http\Requests\Seller\BulkSellerOrderRequest;
use Modules\Order\Http\Requests\Seller\SellerCancelRequest;
use Modules\Order\Models\LabelBatch;
use Modules\Order\Models\SalesOrderDocument;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Repositories\SellerOrderDeskRepository;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Services\OrderFulfillmentService;
use Modules\Order\Support\OrderOptions;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Supplier order desk (FR-FUL-001/002/006): tabs with counts, filters, the bulk bar (accept,
 * labels, pack, manifest), the order detail (page, or drawer content over AJAX), seller
 * cancellation and document downloads. Every order is the signed-in seller's own.
 */
class SellerOrderController extends SellerPanelController
{
    use ReadsDeskFilters;

    public function __construct(
        private readonly SellerOrderDeskRepository $desk,
        private readonly OrderFulfillmentService $fulfilment,
        private readonly FulfilmentLogistics $logistics,
    ) {}

    public function index(Request $request): View
    {
        $sellerId = $this->seller()->id;

        return view('order::seller.order.index', $this->gridData($request) + [
            'sellerStoreName' => $this->seller()->displayName(),
            'labelBatches' => LabelBatch::query()->where('seller_id', $sellerId)->orderByDesc('id')->limit(5)->get(),
        ]);
    }

    public function filters(Request $request): JsonResponse
    {
        return response()->json(['html' => view('order::seller.order.partials.grid', $this->gridData($request))->render()]);
    }

    public function show(Request $request): View|JsonResponse
    {
        $sellerOrder = $this->desk->findBySubOrderNo($this->seller()->id, (string) $request->route('subOrderNo'));
        abort_if($sellerOrder === null, 404);
        $this->authorizeOwned($sellerOrder);

        $sellerOrder->load(['salesOrder.shippingAddress', 'items', 'warehouse', 'statusHistories', 'documents']);
        $data = [
            'sellerOrder' => $sellerOrder,
            'shipment' => $this->logistics->shipments([$sellerOrder->id])[$sellerOrder->id] ?? null,
            'timeline' => $this->logistics->timeline($sellerOrder),
            'statuses' => OrderOptions::sellerOrderStatuses(),
            'canCancel' => app(OrderCancellationService::class)->canCancel($sellerOrder, OrderCancellationService::BY_SELLER),
        ];

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['html' => view('order::seller.order.partials.detail', $data)->render()]);
        }

        return view('order::seller.order.show', $data + ['sellerStoreName' => $this->seller()->displayName()]);
    }

    public function accept(BulkSellerOrderRequest $request): RedirectResponse
    {
        $sellerOrders = $this->desk->ownedByIds($this->seller()->id, $request->ids());

        if ($sellerOrders->isEmpty()) {
            return back()->with('error', trans('order::fulfilment.errors.nothing_selected'));
        }

        return $this->bulkRedirect($this->fulfilment->accept($sellerOrders, $this->sellerUser()), 'accepted');
    }

    public function pack(BulkSellerOrderRequest $request): RedirectResponse
    {
        $sellerOrders = $this->desk->ownedByIds($this->seller()->id, $request->ids());

        if ($sellerOrders->isEmpty()) {
            return back()->with('error', trans('order::fulfilment.errors.nothing_selected'));
        }

        return $this->bulkRedirect($this->fulfilment->markPacked($sellerOrders, $this->sellerUser()), 'packed');
    }

    public function cancel(SellerCancelRequest $request): RedirectResponse
    {
        try {
            $this->fulfilment->sellerCancel($request->sellerOrder(), (string) $request->validated('reason'), $request->validated('note'), $this->sellerUser());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', trans('order::fulfilment.messages.cancelled', ['order' => $request->sellerOrder()->sub_order_no]));
    }

    /**
     * A document of one of the seller's orders: our PDF from the private disk, or the courier's
     * document by its URL.
     */
    public function document(Request $request): Response
    {
        $document = SalesOrderDocument::query()->with('sellerOrder')->findOrFail((int) $request->route('document'));
        $this->authorizeOwned($document->sellerOrder);

        if ($document->path === null) {
            abort_if($document->url === null, 404);

            return redirect()->away($document->url);
        }

        $path = $this->fulfilment->documentPath($document);
        abort_if($path === null, 404);

        $name = str_replace('/', '-', $document->number ?? $document->sellerOrder->sub_order_no).'-'.str_replace('_', '-', $document->type).'.'.pathinfo($path, PATHINFO_EXTENSION);

        return $this->fulfilment->disk()->download($path, $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request): array
    {
        $sellerId = $this->seller()->id;
        $filters = $this->deskFilters($request);
        /** @var LengthAwarePaginator<int, SellerOrder> $orders */
        $orders = $this->desk->orders($sellerId, $filters, (int) config('order.fulfilment.seller_per_page', 25));

        return [
            'filters' => $filters,
            'orders' => $orders,
            'counts' => $this->desk->tabCounts($sellerId),
            'shipments' => $this->logistics->shipments($orders->getCollection()->modelKeys()),
            'statuses' => OrderOptions::sellerOrderStatuses(),
            'cancelReasons' => (array) config('order.fulfilment.seller_cancel_reasons'),
        ];
    }

    private function bulkRedirect(FulfilmentResult $result, string $verb): RedirectResponse
    {
        $redirect = back();
        $ok = $result->count(FulfilmentResult::OK);
        $pending = $result->count(FulfilmentResult::AWB_PENDING);

        if ($ok > 0 || $pending > 0) {
            $redirect = $redirect->with('success', trans('order::fulfilment.messages.'.$verb, ['count' => $ok])
                .($pending > 0 ? ' '.trans('order::fulfilment.messages.awb_pending', ['count' => $pending]) : ''));
        }

        if ($result->problems() !== []) {
            $redirect = $redirect->with('error', implode(' · ', array_slice($result->problems(), 0, 5)));
        }

        return $redirect->with('desk_result', array_map(fn (array $row): string => $row['outcome'], $result->rows()));
    }
}
