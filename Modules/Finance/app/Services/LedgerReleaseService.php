<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Carbon;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Seller\Models\Seller;

/**
 * Daily eligibility run (plan M17 §5.2): pending entries whose eligible_on has come become
 * eligible, or on_hold for held sellers; holds that ended release their entries, new holds
 * catch unbatched eligible ones.
 */
class LedgerReleaseService
{
    public function __construct(private readonly PayoutHoldService $holds) {}

    /**
     * @return array{released: int, on_hold: int, resynced: int}
     */
    public function run(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $result = ['released' => 0, 'on_hold' => 0, 'resynced' => 0];

        $due = SellerLedgerEntry::query()
            ->where('status', SellerLedgerEntry::STATUS_PENDING)
            ->whereDate('eligible_on', '<=', $today->toDateString())
            ->distinct()
            ->pluck('seller_id');

        $this->holds->forget();

        foreach (Seller::query()->whereIn('id', $due)->get() as $seller) {
            /** @var Seller $seller */
            $held = $this->holds->isHeld($seller);
            $moved = SellerLedgerEntry::query()
                ->where('seller_id', $seller->id)
                ->where('status', SellerLedgerEntry::STATUS_PENDING)
                ->whereDate('eligible_on', '<=', $today->toDateString())
                ->update(['status' => $held ? SellerLedgerEntry::STATUS_ON_HOLD : SellerLedgerEntry::STATUS_ELIGIBLE]);

            $result[$held ? 'on_hold' : 'released'] += $moved;
        }

        // Holds placed or lifted elsewhere (bank re-verification, suspension, claims).
        $payable = SellerLedgerEntry::query()
            ->whereIn('status', [SellerLedgerEntry::STATUS_ELIGIBLE, SellerLedgerEntry::STATUS_ON_HOLD])
            ->whereNull('payout_id')
            ->distinct()
            ->pluck('seller_id');

        foreach (Seller::query()->whereIn('id', $payable)->get() as $seller) {
            $result['resynced'] += $this->holds->sync($seller);
        }

        return $result;
    }
}
