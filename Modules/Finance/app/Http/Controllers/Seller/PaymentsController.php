<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Services\PayoutHoldService;
use Modules\Finance\Services\SellerBalanceService;
use Modules\Order\Models\SellerOrder;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Supplier payments (FR-FIN-005, plan M17 §5.4): overview cards (next payout, pending in the
 * return window, on hold with the reason, paid in the last 30 days) and the weekly chart,
 * the payouts list with the orders each one settled, and the settlement of one order.
 */
class PaymentsController extends SellerPanelController
{
    public function __construct(
        private readonly SellerBalanceService $balances,
        private readonly PayoutHoldService $holds,
    ) {}

    public function overview(): View
    {
        $seller = $this->seller();
        $upcoming = SellerLedgerEntry::query()->forSeller($seller->id)
            ->where('status', SellerLedgerEntry::STATUS_PENDING)
            ->whereNotNull('seller_order_id')
            ->selectRaw('seller_order_id, MIN(eligible_on) as eligible_on, SUM('.SellerLedgerEntry::signedSql().') as net')
            ->groupBy('seller_order_id')
            ->orderBy('eligible_on')
            ->limit(10)
            ->get();

        return view('finance::seller.payments.overview', [
            'seller' => $seller,
            'balances' => $this->balances->balances($seller->id),
            'paidLast30' => $this->balances->paidSince($seller->id, 30),
            'nextPayoutDate' => $this->balances->nextPayoutDate(),
            'holdReasons' => $this->holds->reasons($seller),
            'weekly' => $this->balances->weeklyPayouts($seller->id, 8),
            'recentPayouts' => SellerPayout::query()->forSeller($seller->id)->latest('id')->limit(5)->get(),
            'upcoming' => $upcoming,
            'orderNumbers' => SellerOrder::query()->whereIn('id', $upcoming->pluck('seller_order_id'))->pluck('sub_order_no', 'id'),
        ]);
    }

    public function payouts(): View
    {
        return view('finance::seller.payments.payouts', [
            'payouts' => SellerPayout::query()->forSeller($this->seller()->id)->with('bankAccount')->latest('id')->paginate((int) config('finance.seller_per_page', 25)),
        ]);
    }

    public function showPayout(Request $request): View
    {
        $payout = SellerPayout::query()->where('payout_no', (string) $request->route('payoutNo'))->with('bankAccount')->firstOrFail();
        $this->authorizeOwned($payout);

        $entries = SellerLedgerEntry::query()
            ->where('payout_id', $payout->id)
            ->where('type', '!=', SellerLedgerEntry::TYPE_PAYOUT)
            ->with('sellerOrder')
            ->orderBy('seller_order_id')
            ->orderBy('id')
            ->get();

        return view('finance::seller.payments.payout-show', [
            'payout' => $payout,
            'orders' => $entries->groupBy(fn (SellerLedgerEntry $entry): string => (string) ($entry->sellerOrder?->sub_order_no ?? '')),
        ]);
    }

    public function orderSettlement(Request $request): View
    {
        $sellerOrder = SellerOrder::query()->where('sub_order_no', (string) $request->route('subOrderNo'))->firstOrFail();
        $this->authorizeOwned($sellerOrder);

        return view('finance::seller.payments.order-settlement', [
            'sellerOrder' => $sellerOrder,
            'settlement' => $this->balances->orderSettlement($sellerOrder),
        ]);
    }
}
