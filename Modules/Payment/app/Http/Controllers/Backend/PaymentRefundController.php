<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Repositories\PaymentRefundRepository;
use Modules\Payment\Services\RefundService;
use Modules\Payment\Support\PaymentOptions;

/**
 * Refund log for Finance / CX (FR-PAY-007): mark a refund paid by hand (COD refunds until
 * RazorpayX payouts exist, with the bank UTR) and retry failed or stuck refunds.
 */
class PaymentRefundController extends BackendController
{
    public function __construct(private PaymentRefundRepository $refundRepository)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('payment.cache.payment_refund'), $request->input('per_page'))]);

            return view('payment::backend.payment_refund.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('payment.cache.payment_refund'), $request->input('per_page'))]);
            setFilterSession(config('payment.cache.payment_refund'), $request);

            $content = view('payment::backend.payment_refund.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.payment_refund.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function markProcessed(Request $request, RefundService $refunds): RedirectResponse
    {
        $validated = $request->validate(['utr' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/']]);

        try {
            $refund = $this->findRefund($request);

            if (! in_array($refund->status, [PaymentRefund::STATUS_PENDING, PaymentRefund::STATUS_PROCESSING], true)) {
                throw new PaymentException(trans('payment::payment_refund.messages.invalid_transition', ['status' => $refund->status]));
            }

            if ($refund->payment?->isCod() && blank($validated['utr'] ?? null)) {
                throw new PaymentException(trans('payment::payment_refund.messages.utr_required'));
            }

            $refunds->markProcessed($refund, $validated['utr'] ?? null, $request->user());

            return redirect()->back()->with('success', trans('payment::payment_refund.messages.marked_processed'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function retry(Request $request, RefundService $refunds): RedirectResponse
    {
        try {
            $refund = $refunds->retry($this->findRefund($request), $request->user());

            return redirect()->back()->with('success', trans('payment::payment_refund.messages.retried', [
                'status' => PaymentOptions::refundStatuses()[$refund->status] ?? $refund->status,
            ]));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function findRefund(Request $request): PaymentRefund
    {
        $refund = $this->refundRepository->find((int) $request->route('id'));

        if (! $refund instanceof PaymentRefund) {
            throw new \RuntimeException(trans('payment::payment_refund.messages.not_found'));
        }

        return $refund->loadMissing('payment');
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'statuses' => PaymentOptions::refundStatuses(),
            'modes' => PaymentOptions::refundModes(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->refundRepository->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->refundRepository->getFilters($request, $options),
        ] + $options;
    }
}
