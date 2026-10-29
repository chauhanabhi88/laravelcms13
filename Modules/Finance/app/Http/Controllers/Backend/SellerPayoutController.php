<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Services\PayoutService;

/**
 * One payout of a batch: retry a failed transfer, or mark it paid with the bank UTR.
 */
class SellerPayoutController extends BackendController
{
    public function __construct(private readonly PayoutService $payouts)
    {
        parent::__construct();
    }

    public function retry(Request $request): RedirectResponse
    {
        $payout = SellerPayout::query()->findOrFail((int) $request->route('id'));

        try {
            $payout = $this->payouts->retry($payout);
        } catch (FinanceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payout_batch.show', updateUrlParams([$payout->payout_batch_id]))
            ->with('success', trans('finance::seller_payout.messages.retried', ['payout' => $payout->payout_no]));
    }

    public function markPaid(Request $request): RedirectResponse
    {
        $validated = $request->validate(['utr' => ['required', 'string', 'min:6', 'max:40']]);
        $payout = SellerPayout::query()->findOrFail((int) $request->route('id'));

        try {
            $this->payouts->markPaid($payout, $validated['utr'], $request->user());
        } catch (FinanceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payout_batch.show', updateUrlParams([$payout->payout_batch_id]))
            ->with('success', trans('finance::seller_payout.messages.marked_paid', ['payout' => $payout->payout_no]));
    }
}
