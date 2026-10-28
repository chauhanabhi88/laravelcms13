<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Requests\Seller\BulkSellerOrderRequest;
use Modules\Order\Models\LabelBatch;
use Modules\Order\Repositories\SellerOrderDeskRepository;
use Modules\Order\Services\OrderFulfillmentService;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Label batches (FR-FUL-004): queue one for the selected orders, poll its state and download
 * the file (packing slips PDF, or a ZIP with the courier labels).
 */
class LabelController extends SellerPanelController
{
    public function __construct(
        private readonly SellerOrderDeskRepository $desk,
        private readonly OrderFulfillmentService $fulfilment,
    ) {}

    public function generate(BulkSellerOrderRequest $request): RedirectResponse
    {
        $sellerOrders = $this->desk->ownedByIds($this->seller()->id, $request->ids());

        if ($sellerOrders->isEmpty()) {
            return back()->with('error', trans('order::fulfilment.errors.nothing_selected'));
        }

        try {
            $batch = $this->fulfilment->generateLabels($this->seller(), $sellerOrders, $this->sellerUser());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        $batch->refresh();
        $message = $batch->isReady()
            ? trans('order::fulfilment.messages.labels_ready', ['count' => $batch->orders_count])
            : trans('order::fulfilment.messages.labels_queued', ['count' => $batch->orders_count]);

        return back()->with($batch->status === LabelBatch::STATUS_FAILED ? 'error' : 'success', $batch->status === LabelBatch::STATUS_FAILED ? (string) $batch->error : $message)
            ->with('label_batch', $batch->id);
    }

    public function status(Request $request): JsonResponse
    {
        $batch = $this->batch($request);

        return response()->json([
            'status' => $batch->status,
            'ready' => $batch->isReady(),
            'error' => $batch->status === LabelBatch::STATUS_FAILED ? $batch->error : null,
            'url' => $batch->isReady() ? frontRoute('seller.label.download', [$batch->id]) : null,
        ]);
    }

    public function download(Request $request): Response
    {
        $batch = $this->batch($request);

        if (! $batch->isReady()) {
            return redirect()->to(frontRoute('seller.order.index'))->with('error', $batch->status === LabelBatch::STATUS_FAILED
                ? trans('order::fulfilment.errors.labels_failed', ['error' => $batch->error])
                : trans('order::fulfilment.messages.labels_queued', ['count' => $batch->orders_count]));
        }

        $path = $this->fulfilment->documentPath($batch);
        abort_if($path === null, 404);

        return $this->fulfilment->disk()->download($path, $batch->file_name ?? basename($path));
    }

    private function batch(Request $request): LabelBatch
    {
        $batch = LabelBatch::query()->findOrFail((int) $request->route('batch'));
        $this->authorizeOwned($batch);

        return $batch;
    }
}
