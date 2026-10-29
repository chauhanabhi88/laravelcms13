<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Http\Controllers\Backend\Concerns\RendersFinanceGrid;
use Modules\Finance\Models\PayoutHold;
use Modules\Finance\Services\PayoutHoldService;
use Modules\Seller\Models\Seller;

/**
 * Manual payout holds (FR-FIN-009, plan M17 §5.9): hold a seller with a reason, release it.
 * Automatic holds (bank re-verification, suspension, open claims) are applied on their own.
 */
class PayoutHoldController extends BackendController
{
    use RendersFinanceGrid;

    protected const GRID = 'payout_hold';

    protected const VIEW = 'payout_hold';

    public function __construct(private readonly PayoutHoldService $holds)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        return $this->renderIndex($request);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seller_code' => ['required', 'string', 'max:12'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $seller = Seller::query()->where('code', strtoupper(trim($validated['seller_code'])))->first();

        if ($seller === null) {
            return redirect()->back()->withInput()->withErrors(['seller_code' => trans('finance::ledger.messages.seller_not_found')]);
        }

        try {
            $this->holds->place($seller, $validated['reason'], $request->user());
        } catch (FinanceException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payout_hold.index', updateUrlParams())->with('success', trans('finance::payout_hold.messages.placed', ['seller' => $seller->displayName()]));
    }

    public function release(Request $request): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $hold = PayoutHold::query()->findOrFail((int) $request->route('id'));

        try {
            $this->holds->release($hold, $validated['note'] ?? null, $request->user());
        } catch (FinanceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payout_hold.index', updateUrlParams())->with('success', trans('finance::payout_hold.messages.released'));
    }
}
