<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Logistics\Models\PickupBatch;
use Modules\Logistics\Repositories\PickupBatchRepository;
use Modules\Logistics\Support\LogisticsOptions;
use Modules\Logistics\Support\ManifestDocument;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pickup batches across sellers for Logistics Ops (plan M14 §6, admin.pickup_batch.index):
 * manifest, courier, pickup date and status, the packages of a batch and the manifest PDF.
 */
class PickupBatchController extends BackendController
{
    public function __construct(private PickupBatchRepository $batches)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.pickup_batch'), $request->input('per_page'))]);

            return view('logistics::backend.pickup_batch.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.pickup_batch'), $request->input('per_page'))]);
            setFilterSession(config('logistics.cache.pickup_batch'), $request);

            $content = view('logistics::backend.pickup_batch.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.pickup_batch.index')));

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
            $batch = $this->batch($request);
            $batch->load(['seller', 'warehouse', 'courier', 'shipments.sellerOrder']);

            return view('logistics::backend.pickup_batch.show', [
                'batch' => $batch,
                'statuses' => self::statuses(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.pickup_batch.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function download(Request $request, ManifestDocument $document): Response
    {
        try {
            $batch = $this->batch($request);
        } catch (\Throwable $e) {
            return redirect()->route('admin.pickup_batch.index', updateUrlParams())->with('error', $e->getMessage());
        }

        return response($document->render($batch), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="manifest-'.$batch->manifest_no.'.pdf"',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return array_combine(PickupBatch::STATUSES, array_map(fn (string $status): string => trans('logistics::pickup_batch.statuses.'.$status), PickupBatch::STATUSES));
    }

    private function batch(Request $request): PickupBatch
    {
        $batch = $this->batches->find((int) $request->route('id'));

        if (! $batch instanceof PickupBatch) {
            throw new \RuntimeException(trans('logistics::pickup_batch.messages.not_found'));
        }

        return $batch;
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = ['statuses' => self::statuses()];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->batches->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->batches->getFilters($request, $options),
            'shipmentStatuses' => LogisticsOptions::shipmentStatuses(),
        ] + $options;
    }
}
