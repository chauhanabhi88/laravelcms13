<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Core\Support\Money;
use Modules\Payment\Support\PaymentOptions;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Models\ReturnRequestImage;
use Modules\Returns\Repositories\ReturnRequestRepository;
use Modules\Returns\Services\ReturnService;
use Modules\Returns\Support\ReturnOptions;
use Modules\Returns\Support\ReturnPhotoStore;
use Modules\Returns\Support\ReturnTimeline;
use Modules\Returns\Support\Validation\RefundDetailsRules;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Returns console for CX (FR-RET-008): grid with filters and export, the detail with the
 * customer's photos, items, pickup and refunds, and the decisions: approve / reject a held
 * request, retry the pickup, refund by hand (after a failed QC, partly, or with COD details)
 * and close without a refund.
 */
class ReturnRequestController extends BackendController
{
    public function __construct(
        private ReturnRequestRepository $returnRepository,
        private ReturnService $returns,
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('returns.cache.return_request'), $request->input('per_page'))]);

            return view('returns::backend.return_request.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('returns.cache.return_request'), $request->input('per_page'))]);
            setFilterSession(config('returns.cache.return_request'), $request);

            $content = view('returns::backend.return_request.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.return_request.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->returnRepository->filter($request)->with(['sellerOrder', 'seller', 'customer'])->orderByDesc('id');
        $statuses = ReturnOptions::statuses();
        $reasons = ReturnOptions::reasons();

        return response()->streamDownload(function () use ($query, $statuses, $reasons): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Return No', 'Created At', 'Order', 'Seller', 'Customer', 'Type', 'Reason', 'Status', 'Refund Amount', 'Refund Mode', 'Refund Status', 'Auto Approved']);

            $query->chunk(500, function ($returns) use ($out, $statuses, $reasons): void {
                foreach ($returns as $return) {
                    /** @var ReturnRequest $return */
                    fputcsv($out, [
                        $return->return_no,
                        $return->created_at?->format('Y-m-d H:i'),
                        $return->sellerOrder?->sub_order_no,
                        $return->seller?->displayName(),
                        $return->customer?->displayName(),
                        $return->type,
                        $reasons[$return->reason_code] ?? $return->reason_code,
                        $statuses[$return->status] ?? $return->status,
                        $return->refund_amount->toDecimal(),
                        $return->refund_mode,
                        $return->refund_status,
                        $return->isAutoApproved() ? 'Yes' : 'No',
                    ]);
                }
            });

            fclose($out);
        }, 'returns-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Request $request, ReturnTimeline $timeline): View|RedirectResponse
    {
        try {
            $return = $this->findReturn($request);
            $return->load(['items.orderItem', 'items.exchangeVariant', 'images', 'sellerOrder', 'salesOrder', 'seller', 'customer', 'reverseShipment', 'refunds', 'claims', 'exchangeSellerOrder', 'statusHistories']);

            return view('returns::backend.return_request.show', [
                'return' => $return,
                'events' => $timeline->build($return),
                'statuses' => ReturnOptions::statuses(),
                'refundStatuses' => PaymentOptions::refundStatuses(),
                'trusted' => $this->returns->isTrusted($return->customer_id, $return->id),
                'returnRate' => $this->returns->returnRate($return->customer_id, $return->id),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.return_request.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function photo(Request $request, ReturnPhotoStore $photos): Response
    {
        $image = ReturnRequestImage::query()->where('return_request_id', (int) $request->route('id'))->findOrFail((int) $request->route('image'));

        abort_unless($photos->disk()->exists($image->path), 404);

        return $photos->disk()->response($image->path);
    }

    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        return $this->act($request, fn (ReturnRequest $return) => $this->returns->approve($return, $request->user(), $validated['note'] ?? null), 'approved');
    }

    public function reject(Request $request): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);

        return $this->act($request, fn (ReturnRequest $return) => $this->returns->reject($return, $validated['note'], $request->user()), 'rejected');
    }

    public function schedulePickup(Request $request): RedirectResponse
    {
        return $this->act($request, function (ReturnRequest $return) use ($request): void {
            $return = $this->returns->schedulePickup($return, $request->user());

            if ($return->status !== ReturnRequest::STATUS_PICKUP_SCHEDULED) {
                throw new ReturnException((string) ($return->attention_reason ?: trans('returns::return_request.messages.pickup_failed')));
            }
        }, 'pickup_scheduled');
    }

    public function forceRefund(Request $request): RedirectResponse
    {
        $validated = $request->validate(RefundDetailsRules::rules(false) + [
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $details = RefundDetailsRules::beneficiary($validated);
        $override = $details !== null ? ['mode' => (string) $validated['refund_mode'], 'beneficiary' => $details] : null;
        $amount = filled($validated['amount'] ?? null) ? Money::of((string) $validated['amount']) : null;

        return $this->act($request, fn (ReturnRequest $return) => $this->returns->forceRefund($return, $amount, $request->user(), $override, $validated['note']), 'refund_started');
    }

    public function close(Request $request): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);

        return $this->act($request, fn (ReturnRequest $return) => $this->returns->close($return, $request->user(), $validated['note']), 'closed');
    }

    /**
     * @param  callable(ReturnRequest): mixed  $action
     */
    private function act(Request $request, callable $action, string $message): RedirectResponse
    {
        try {
            $return = $this->findReturn($request);
            $action($return);

            return redirect()->route('admin.return_request.show', updateUrlParams([$return->id]))
                ->with('success', trans('returns::return_request.messages.'.$message, ['return_no' => $return->return_no]));
        } catch (ReturnException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage())->withErrors($e->errors);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function findReturn(Request $request): ReturnRequest
    {
        $return = $this->returnRepository->find((int) $request->route('id'));

        if (! $return instanceof ReturnRequest) {
            throw new \RuntimeException(trans('returns::return_request.messages.not_found'));
        }

        return $return;
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'statuses' => ReturnOptions::statuses(),
            'types' => ReturnOptions::types(),
            'reasons' => ReturnOptions::reasons(),
            'attention' => [(string) config('core.yes') => trans('core::core.labels.yes'), (string) config('core.no') => trans('core::core.labels.no')],
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->returnRepository->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->returnRepository->getFilters($request, $options),
        ] + $options;
    }
}
