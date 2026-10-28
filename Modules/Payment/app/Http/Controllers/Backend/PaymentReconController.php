<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Payment\Models\PaymentReconItem;
use Modules\Payment\Repositories\PaymentReconRepository;
use Modules\Payment\Support\PaymentOptions;

/**
 * Settlement reconciliation report (FR-PAY-008): mismatches found by the daily job, which
 * Finance investigates and marks resolved.
 */
class PaymentReconController extends BackendController
{
    public function __construct(private PaymentReconRepository $recon)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('payment.cache.payment_recon'), $request->input('per_page'))]);

            return view('payment::backend.payment_recon.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('payment.cache.payment_recon'), $request->input('per_page'))]);
            setFilterSession(config('payment.cache.payment_recon'), $request);

            $content = view('payment::backend.payment_recon.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.payment_recon.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function resolve(Request $request): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        try {
            $item = $this->recon->find((int) $request->route('id'));

            if (! $item instanceof PaymentReconItem) {
                throw new \RuntimeException(trans('payment::payment_recon.messages.not_found'));
            }

            $this->recon->resolve($item, (int) $request->user()?->getKey(), $validated['note'] ?? null);

            return redirect()->back()->with('success', trans('payment::payment_recon.messages.resolved'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'issues' => PaymentOptions::reconIssues(),
            'states' => PaymentOptions::reconStates(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->recon->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->recon->getFilters($request, $options),
        ] + $options;
    }
}
