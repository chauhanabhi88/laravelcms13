<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Finance\Http\Controllers\Backend\Concerns\RendersFinanceGrid;
use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Finance\Services\ResellerWalletService;

/**
 * Reseller withdrawals (FR-FIN-007): with the manual gateway Finance transfers and marks them
 * paid with the UTR, or failed (the amount returns to the wallet).
 */
class ResellerWithdrawalController extends BackendController
{
    use RendersFinanceGrid;

    protected const GRID = 'reseller_withdrawal';

    protected const VIEW = 'reseller_withdrawal';

    public function __construct(private readonly ResellerWalletService $wallet)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        return $this->renderIndex($request);
    }

    public function markPaid(Request $request): RedirectResponse
    {
        $validated = $request->validate(['utr' => ['required', 'string', 'min:6', 'max:40']]);
        $withdrawal = ResellerWithdrawal::query()->findOrFail((int) $request->route('id'));
        $this->wallet->markPaid($withdrawal, $validated['utr']);

        return redirect()->route('admin.reseller_withdrawal.index', updateUrlParams())->with('success', trans('finance::wallet.messages.marked_paid', ['no' => $withdrawal->withdrawal_no]));
    }

    public function markFailed(Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $withdrawal = ResellerWithdrawal::query()->findOrFail((int) $request->route('id'));
        $this->wallet->markFailed($withdrawal, $validated['reason']);

        return redirect()->route('admin.reseller_withdrawal.index', updateUrlParams())->with('success', trans('finance::wallet.messages.marked_failed', ['no' => $withdrawal->withdrawal_no]));
    }
}
