<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Logistics\Http\Requests\ManualAwbRequest;
use Modules\Logistics\Http\Requests\ManualStatusRequest;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Repositories\CourierRepository;
use Modules\Logistics\Repositories\ShipmentRepository;
use Modules\Logistics\Services\ShipmentService;
use Modules\Logistics\Services\TrackingService;
use Modules\Logistics\Support\LogisticsOptions;
use Modules\Logistics\Support\TimelineBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shipments admin (plan M13 §7): grid with status / courier / payment / stuck / needs-attention
 * filters, detail with the scan timeline and raw payloads, and the actions: book, resync,
 * cancel, manual AWB and manual scans (manual courier), CSV export.
 */
class ShipmentController extends BackendController
{
    public function __construct(
        private ShipmentRepository $shipments,
        private CourierRepository $couriers,
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.shipment'), $request->input('per_page'))]);

            return view('logistics::backend.shipment.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.shipment'), $request->input('per_page'))]);
            setFilterSession(config('logistics.cache.shipment'), $request);

            $content = view('logistics::backend.shipment.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.shipment.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request, TimelineBuilder $timelines): View|RedirectResponse
    {
        try {
            $shipment = $this->findOrFail($request->route('id'));

            return view('logistics::backend.shipment.show', [
                'shipment' => $shipment,
                'timeline' => $timelines->build($shipment->sellerOrder),
                'manualStatuses' => LogisticsOptions::manualStatuses(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.shipment.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->shipments->filter($request)->with(['sellerOrder.seller', 'courier'])->orderByDesc('shipment.id');
        $statuses = LogisticsOptions::shipmentStatuses();

        return response()->streamDownload(function () use ($query, $statuses): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Sub-order No', 'Direction', 'Seller', 'Courier', 'Carrier', 'AWB', 'Status', 'Payment', 'COD Amount', 'Weight (g)', 'Charged Weight (g)', 'Freight', 'COD Charge', 'RTO Charge', 'EDD', 'Attempts', 'Picked Up At', 'Delivered At', 'Created At']);

            $query->chunk(500, function ($shipments) use ($out, $statuses): void {
                foreach ($shipments as $shipment) {
                    /** @var Shipment $shipment */
                    fputcsv($out, [
                        $shipment->id,
                        $shipment->sellerOrder?->sub_order_no,
                        $shipment->direction,
                        $shipment->sellerOrder?->seller?->displayName(),
                        $shipment->courier?->name,
                        $shipment->carrier_name,
                        $shipment->awb,
                        $statuses[$shipment->status] ?? $shipment->status,
                        $shipment->payment_mode,
                        $shipment->cod_amount->toDecimal(),
                        $shipment->weight_grams,
                        $shipment->charged_weight_grams,
                        $shipment->freight_charge->toDecimal(),
                        $shipment->cod_charge->toDecimal(),
                        $shipment->rto_charge->toDecimal(),
                        $shipment->edd?->toDateString(),
                        $shipment->attempts,
                        $shipment->picked_up_at?->format('Y-m-d H:i'),
                        $shipment->delivered_at?->format('Y-m-d H:i'),
                        $shipment->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($out);
        }, 'shipments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Book with the courier chosen by the rules (normally M14 does it when the seller accepts).
     */
    public function book(Request $request, ShipmentService $service): RedirectResponse
    {
        try {
            $shipment = $service->book($this->findOrFail($request->route('id')), $request->user());

            return $shipment->isBooked()
                ? redirect()->back()->with('success', trans('logistics::shipment.messages.booked', ['awb' => $shipment->awb]))
                : redirect()->back()->with('error', (string) ($shipment->attention_reason ?? trans('logistics::shipment.messages.not_booked')));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function resync(Request $request, TrackingService $tracking): RedirectResponse
    {
        try {
            $count = $tracking->sync($this->findOrFail($request->route('id')));

            return redirect()->back()->with('success', trans('logistics::shipment.messages.resynced', ['count' => $count]));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, ShipmentService $service): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $service->cancel($this->findOrFail($request->route('id')), $validated['reason'] ?? null, $request->user());

            return redirect()->back()->with('success', trans('logistics::shipment.messages.cancelled'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function manualAwb(ManualAwbRequest $request, ShipmentService $service): RedirectResponse
    {
        try {
            $shipment = $service->recordManualAwb(
                $this->findOrFail($request->route('id')),
                (string) $request->validated('awb'),
                $request->validated('carrier_name'),
                $request->validated('tracking_url'),
                $request->user(),
            );

            return redirect()->back()->with('success', trans('logistics::shipment.messages.booked', ['awb' => $shipment->awb]));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function manualStatus(ManualStatusRequest $request, TrackingService $tracking): RedirectResponse
    {
        try {
            $occurredAt = $request->validated('occurred_at');

            $tracking->recordManualStatus(
                $this->findOrFail($request->route('id')),
                (string) $request->validated('status'),
                $request->validated('location'),
                $request->validated('remarks'),
                $occurredAt !== null ? Carbon::parse($occurredAt) : null,
                $request->user(),
            );

            return redirect()->back()->with('success', trans('logistics::shipment.messages.status_recorded'));
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
            'statuses' => LogisticsOptions::shipmentStatuses(),
            'couriers' => $this->couriers->getOptions(false, true),
            'payment_modes' => LogisticsOptions::paymentModes(),
            'flags' => LogisticsOptions::shipmentFlags(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->shipments->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->shipments->getFilters($request, $options),
        ] + $options;
    }

    private function findOrFail(mixed $id): Shipment
    {
        $shipment = $id ? $this->shipments->findForShow((int) $id) : null;

        if (! $shipment instanceof Shipment) {
            throw new \RuntimeException(trans('logistics::shipment.messages.not_found'));
        }

        return $shipment;
    }
}
