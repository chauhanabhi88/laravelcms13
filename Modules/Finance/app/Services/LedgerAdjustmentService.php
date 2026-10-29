<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\LedgerAdjustment;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Seller\Models\Seller;

/**
 * Manual ledger adjustments with maker-checker (plan M17 §6 admin.ledger.adjust): one finance
 * admin requests a credit or debit with a reason, a different admin approves it, which posts
 * the "adjustment" entry, or rejects it.
 */
class LedgerAdjustmentService
{
    public function __construct(private readonly LedgerPosterImpl $ledger) {}

    public function request(Seller $seller, string $direction, Money $amount, string $reason, Model $requester, ?int $sellerOrderId = null): LedgerAdjustment
    {
        if (! $amount->isPositive()) {
            throw new FinanceException(trans('finance::ledger.messages.amount_positive'), 'amount');
        }

        return LedgerAdjustment::query()->create([
            'seller_id' => $seller->id,
            'seller_order_id' => $sellerOrderId,
            'direction' => $direction === SellerLedgerEntry::CREDIT ? SellerLedgerEntry::CREDIT : SellerLedgerEntry::DEBIT,
            'amount' => $amount,
            'reason' => mb_substr($reason, 0, 500),
            'status' => LedgerAdjustment::STATUS_PENDING,
            'requested_by' => (int) $requester->getKey(),
        ]);
    }

    /**
     * @throws FinanceException
     */
    public function approve(LedgerAdjustment $adjustment, Model $approver): LedgerAdjustment
    {
        return $this->decide($adjustment, $approver, true);
    }

    /**
     * @throws FinanceException
     */
    public function reject(LedgerAdjustment $adjustment, Model $approver): LedgerAdjustment
    {
        return $this->decide($adjustment, $approver, false);
    }

    protected function decide(LedgerAdjustment $adjustment, Model $approver, bool $approve): LedgerAdjustment
    {
        DB::transaction(function () use ($adjustment, $approver, $approve): void {
            $locked = LedgerAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if (! $locked->isPending()) {
                throw new FinanceException(trans('finance::ledger.messages.adjustment_decided'), 'decided');
            }

            if ((int) $locked->requested_by === (int) $approver->getKey()) {
                throw new FinanceException(trans('finance::ledger.messages.maker_checker'), 'maker_checker');
            }

            $entryId = $approve ? $this->ledger->postAdjustment($locked, $approver)->id : null;

            $locked->forceFill([
                'status' => $approve ? LedgerAdjustment::STATUS_APPROVED : LedgerAdjustment::STATUS_REJECTED,
                'decided_by' => $approver->getKey(),
                'decided_at' => Carbon::now(),
                'ledger_entry_id' => $entryId,
            ])->save();
        });

        return $adjustment->refresh();
    }
}
