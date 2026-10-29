<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\PayoutHold;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Support\FinanceSettings;
use Modules\Returns\Models\ReturnClaim;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Models\Seller;

/**
 * Payout holds (FR-FIN-009, plan M17 §5.9). A seller is held while any of these is true:
 * - manual: Finance placed a hold (seller_payout_hold);
 * - bank: the primary bank account is not verified (seller.payout_hold, kept by M03);
 * - status: the seller is not active (suspended, deactivated…);
 * - claims: open claims reach admin > Settings > Finance "hold_open_claims" (0 = off).
 * Held sellers' payable entries wait "on_hold" and are skipped by payout batches.
 */
class PayoutHoldService
{
    public const REASON_MANUAL = 'manual';

    public const REASON_BANK = 'bank';

    public const REASON_STATUS = 'status';

    public const REASON_CLAIMS = 'claims';

    /**
     * @var array<int, list<string>>
     */
    private array $memo = [];

    /**
     * @return list<string> reason codes, empty when payouts may flow
     */
    public function reasons(Seller $seller): array
    {
        if (array_key_exists($seller->id, $this->memo)) {
            return $this->memo[$seller->id];
        }

        $reasons = [];

        if (PayoutHold::query()->where('seller_id', $seller->id)->active()->exists()) {
            $reasons[] = self::REASON_MANUAL;
        }

        if ((int) $seller->payout_hold === (int) config('core.yes')) {
            $reasons[] = self::REASON_BANK;
        }

        if ($seller->status !== SellerStatus::Active) {
            $reasons[] = self::REASON_STATUS;
        }

        $threshold = FinanceSettings::holdOpenClaims();

        if ($threshold > 0 && ReturnClaim::query()->where('seller_id', $seller->id)->where('status', ReturnClaim::STATUS_OPEN)->count() >= $threshold) {
            $reasons[] = self::REASON_CLAIMS;
        }

        return $this->memo[$seller->id] = $reasons;
    }

    public function isHeld(Seller $seller): bool
    {
        return $this->reasons($seller) !== [];
    }

    public function forget(?int $sellerId = null): void
    {
        if ($sellerId === null) {
            $this->memo = [];
        } else {
            unset($this->memo[$sellerId]);
        }
    }

    /**
     * Place a manual hold; payable entries move to on_hold.
     *
     * @throws FinanceException
     */
    public function place(Seller $seller, string $reason, ?Model $actor = null): PayoutHold
    {
        $hold = DB::transaction(function () use ($seller, $reason, $actor): PayoutHold {
            Seller::query()->lockForUpdate()->findOrFail($seller->id);

            if (PayoutHold::query()->where('seller_id', $seller->id)->active()->exists()) {
                throw new FinanceException(trans('finance::payout_hold.messages.already_held'), 'already_held');
            }

            return PayoutHold::query()->create([
                'seller_id' => $seller->id,
                'reason' => mb_substr($reason, 0, 500),
                'placed_by' => $actor?->getKey(),
                'placed_at' => Carbon::now(),
            ]);
        });

        $this->sync($seller->refresh());

        return $hold;
    }

    /**
     * Release a manual hold; entries become eligible again unless another hold remains.
     *
     * @throws FinanceException
     */
    public function release(PayoutHold $hold, ?string $note = null, ?Model $actor = null): PayoutHold
    {
        DB::transaction(function () use ($hold, $note, $actor): void {
            $locked = PayoutHold::query()->lockForUpdate()->findOrFail($hold->id);

            if (! $locked->isActive()) {
                throw new FinanceException(trans('finance::payout_hold.messages.not_active'), 'not_active');
            }

            $locked->forceFill([
                'released_at' => Carbon::now(),
                'released_by' => $actor?->getKey(),
                'release_note' => $note === null ? null : mb_substr($note, 0, 500),
            ])->save();
        });

        $seller = Seller::query()->findOrFail($hold->seller_id);
        $this->sync($seller);

        return $hold->refresh();
    }

    /**
     * Move the seller's unbatched payable entries between eligible and on_hold to match the
     * current hold state. Returns how many entries moved.
     */
    public function sync(Seller $seller): int
    {
        $this->forget($seller->id);
        [$from, $to] = $this->isHeld($seller)
            ? [SellerLedgerEntry::STATUS_ELIGIBLE, SellerLedgerEntry::STATUS_ON_HOLD]
            : [SellerLedgerEntry::STATUS_ON_HOLD, SellerLedgerEntry::STATUS_ELIGIBLE];

        return SellerLedgerEntry::query()
            ->where('seller_id', $seller->id)
            ->where('status', $from)
            ->whereNull('payout_id')
            ->update(['status' => $to]);
    }

    /**
     * Status of a new entry payable from $eligibleOn.
     */
    public function initialStatus(Seller $seller, Carbon $eligibleOn): string
    {
        if ($eligibleOn->copy()->startOfDay()->greaterThan(Carbon::today())) {
            return SellerLedgerEntry::STATUS_PENDING;
        }

        return $this->isHeld($seller) ? SellerLedgerEntry::STATUS_ON_HOLD : SellerLedgerEntry::STATUS_ELIGIBLE;
    }
}
