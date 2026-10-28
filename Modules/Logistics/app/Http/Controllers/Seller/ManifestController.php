<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Logistics\Models\PickupBatch;
use Modules\Logistics\Support\ManifestDocument;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Requests\Seller\BulkSellerOrderRequest;
use Modules\Order\Repositories\SellerOrderDeskRepository;
use Modules\Order\Services\OrderFulfillmentService;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Supplier manifests (FR-FUL-005): create a pickup batch from packed orders on the order desk,
 * list the batches and print our manifest with the handover signature table.
 */
class ManifestController extends SellerPanelController
{
    public function __construct(
        private readonly SellerOrderDeskRepository $desk,
        private readonly OrderFulfillmentService $fulfilment,
    ) {}

    public function index(): View
    {
        return view('logistics::seller.manifest.index', [
            'batches' => PickupBatch::query()
                ->forSeller($this->seller()->id)
                ->with('warehouse')
                ->orderByDesc('id')
                ->paginate(20),
            'sellerStoreName' => $this->seller()->displayName(),
        ]);
    }

    public function store(BulkSellerOrderRequest $request): RedirectResponse
    {
        $sellerOrders = $this->desk->ownedByIds($this->seller()->id, $request->ids());

        if ($sellerOrders->isEmpty()) {
            return back()->with('error', trans('order::fulfilment.errors.nothing_selected'));
        }

        $date = $request->validated('pickup_date') !== null ? Carbon::parse((string) $request->validated('pickup_date')) : $this->nextWorkingDay();

        try {
            $outcome = $this->fulfilment->createManifest($sellerOrders, $date, $this->sellerUser());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        $redirect = redirect()->to(frontRoute('seller.manifest.index'))
            ->with('success', trans('logistics::pickup_batch.seller.created', ['manifest' => $outcome->manifestNo, 'count' => $outcome->shipmentsCount]));

        return $outcome->message !== null ? $redirect->with('warning', $outcome->message) : $redirect;
    }

    public function download(Request $request, ManifestDocument $document): Response
    {
        $batch = PickupBatch::query()->findOrFail((int) $request->route('id'));
        $this->authorizeOwned($batch);

        return response($document->render($batch), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="manifest-'.$batch->manifest_no.'.pdf"',
        ]);
    }

    private function nextWorkingDay(): Carbon
    {
        $date = Carbon::tomorrow();

        return $date->isSunday() ? $date->addDay() : $date;
    }
}
