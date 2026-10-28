<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Payment\Models\Payment;
use Modules\Payment\Repositories\PaymentRepository;
use Modules\Payment\Services\RefundService;
use Modules\Payment\Support\PaymentOptions;

/**
 * Transactions grid and detail (FR-PAY-007): every payment with its attempts, refunds and
 * status history. Read-only; refunds are started by the order and return flows.
 */
class PaymentController extends BackendController
{
    public function __construct(private PaymentRepository $payments)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('payment.cache.payment'), $request->input('per_page'))]);

            return view('payment::backend.payment.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('payment.cache.payment'), $request->input('per_page'))]);
            setFilterSession(config('payment.cache.payment'), $request);

            $content = view('payment::backend.payment.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.payment.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request, RefundService $refunds): View|RedirectResponse
    {
        try {
            $payment = $this->payments->find((int) $request->route('id'));

            if (! $payment instanceof Payment) {
                throw new \RuntimeException(trans('payment::payment.messages.not_found'));
            }

            $payment->load([
                'customer',
                'attempts' => fn ($query) => $query->orderByDesc('id'),
                'refunds' => fn ($query) => $query->orderByDesc('id'),
                'statusHistories',
            ]);

            return view('payment::backend.payment.show', [
                'payment' => $payment,
                'refundable' => $refunds->refundableAmount($payment),
                'methods' => PaymentOptions::methods(),
                'statuses' => PaymentOptions::paymentStatuses(),
                'refundStatuses' => PaymentOptions::refundStatuses(),
                'refundModes' => PaymentOptions::refundModes(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.payment.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'methods' => PaymentOptions::methods(),
            'statuses' => PaymentOptions::paymentStatuses(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->payments->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->payments->getFilters($request, $options),
        ] + $options;
    }
}
