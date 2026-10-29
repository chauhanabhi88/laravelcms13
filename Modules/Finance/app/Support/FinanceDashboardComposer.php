<?php

declare(strict_types=1);

namespace Modules\Finance\Support;

use Illuminate\Contracts\View\View;
use Modules\Finance\Services\SellerBalanceService;
use Modules\Seller\Support\CurrentSeller;
use Throwable;

/**
 * The supplier dashboard's "Next payout" card (FR-SEL-010): the payable balance and the next
 * payout day, linking to Payments.
 */
class FinanceDashboardComposer
{
    public function __construct(
        private readonly CurrentSeller $current,
        private readonly SellerBalanceService $balances,
    ) {}

    public function compose(View $view): void
    {
        $seller = $this->current->seller();

        if ($seller === null) {
            return;
        }

        try {
            $balances = $this->balances->balances($seller->id);
            $view->with('financeKpis', [
                'next_payout' => $balances['eligible']->plus($balances['in_payout'])->format(),
                'next_payout_date' => $this->balances->nextPayoutDate()->format('D, d M'),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
