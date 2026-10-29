<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Services\SequenceService;
use Modules\Core\Support\Money;
use Modules\Finance\DTO\PayoutRequest;
use Modules\Finance\DTO\PayoutResult;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Exceptions\PayoutGatewayUnavailable;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Support\FinanceSettings;
use Modules\Finance\Support\PayoutGatewayManager;
use Modules\Notification\Services\NotificationHub;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Throwable;

/**
 * The payout cycle (FR-FIN-003/004, plan M17 §5.3), the service requirement.md names.
 *
 * 1. build(): per seller, net = Σ eligible credits − Σ eligible debits (unbatched); skipped
 *    below min_payout_amount or when negative (carried forward), when held or without a
 *    verified primary bank account. One draft batch, one scheduled payout per seller, and the
 *    entries linked (payout_id, still eligible).
 * 2. approve(): a second admin (maker-checker when an admin built the batch).
 * 3. process(): each payout in its own step → PayoutGateway::pay() → processing.
 * 4. markPaid() (webhook / UTR upload): payout debit posted, linked entries settled, seller
 *    notified. markFailed(): entries unlinked (back to the next batch), Finance alerted.
 */
class PayoutService
{
    public function __construct(
        private readonly PayoutGatewayManager $gateways,
        private readonly PayoutHoldService $holds,
        private readonly LedgerPosterImpl $ledger,
        private readonly SequenceService $sequences,
        private readonly NotificationHub $hub,
    ) {}

    /**
     * Build the cycle's draft batch. Null when no seller is payable.
     */
    public function build(?Carbon $cycleEnd = null, ?Model $actor = null): ?PayoutBatch
    {
        $cycleEnd = ($cycleEnd ?? Carbon::today())->copy()->startOfDay();
        $lock = Cache::lock('finance:payout-build', 300);

        if (! $lock->get()) {
            throw new FinanceException(trans('finance::payout_batch.messages.build_running'), 'build_running');
        }

        try {
            return DB::transaction(fn (): ?PayoutBatch => $this->buildLocked($cycleEnd, $actor));
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws FinanceException
     */
    public function approve(PayoutBatch $batch, Model $approver): PayoutBatch
    {
        DB::transaction(function () use ($batch, $approver): void {
            $locked = PayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if (! $locked->isDraft()) {
                throw new FinanceException(trans('finance::payout_batch.messages.not_draft'), 'not_draft');
            }

            if ($locked->created_by !== null && (int) $locked->created_by === (int) $approver->getKey()) {
                throw new FinanceException(trans('finance::payout_batch.messages.maker_checker'), 'maker_checker');
            }

            $locked->forceFill([
                'status' => PayoutBatch::STATUS_APPROVED,
                'approved_by' => $approver->getKey(),
                'approved_at' => Carbon::now(),
            ])->save();
        });

        return $batch->refresh();
    }

    /**
     * Send every scheduled payout of an approved batch.
     *
     * @throws FinanceException
     */
    public function process(PayoutBatch $batch): PayoutBatch
    {
        DB::transaction(function () use ($batch): void {
            $locked = PayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if (! in_array($locked->status, [PayoutBatch::STATUS_APPROVED, PayoutBatch::STATUS_PROCESSING], true)) {
                throw new FinanceException(trans('finance::payout_batch.messages.not_approved'), 'not_approved');
            }

            $locked->forceFill(['status' => PayoutBatch::STATUS_PROCESSING, 'processed_at' => $locked->processed_at ?? Carbon::now()])->save();
        });

        $ids = SellerPayout::query()->where('payout_batch_id', $batch->id)->where('status', SellerPayout::STATUS_SCHEDULED)->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            $this->send(SellerPayout::query()->findOrFail($id));
        }

        return $this->refreshBatch($batch->id);
    }

    /**
     * Send one scheduled payout. No row is locked while the gateway is called; the
     * idempotency key makes a repeated send safe.
     */
    public function send(SellerPayout $payout): SellerPayout
    {
        $payout = DB::transaction(function () use ($payout): ?SellerPayout {
            $locked = SellerPayout::query()->lockForUpdate()->with(['bankAccount', 'seller'])->findOrFail($payout->id);

            if ($locked->status !== SellerPayout::STATUS_SCHEDULED) {
                return null;
            }

            $locked->forceFill(['attempts' => $locked->attempts + 1])->save();

            return $locked;
        }) ?? $payout->refresh();

        if ($payout->status !== SellerPayout::STATUS_SCHEDULED) {
            return $payout;
        }

        try {
            $result = $this->gateways->for($payout->gateway)->pay($this->requestFor($payout));
        } catch (PayoutGatewayUnavailable $e) {
            report($e);
            $payout->forceFill(['failure_reason' => mb_substr('Gateway unavailable: '.$e->getMessage(), 0, 255)])->save();

            return $payout;
        }

        return $this->applyResult($payout, $result);
    }

    /**
     * Apply a gateway answer (API response or webhook).
     */
    public function applyResult(SellerPayout $payout, PayoutResult $result, ?Model $actor = null): SellerPayout
    {
        return match ($result->status) {
            PayoutResult::PAID => $this->markPaid($payout, (string) ($result->utr ?? ''), $actor, $result->gatewayPayoutId),
            PayoutResult::FAILED => $payout->isPaid()
                ? $this->markReversed($payout, (string) $result->error, $actor)
                : $this->markFailed($payout, (string) $result->error, $actor, $result->gatewayPayoutId),
            default => $this->markProcessing($payout, $result->gatewayPayoutId),
        };
    }

    /**
     * The money reached the seller: payout debit, linked entries settled, seller notified.
     * Idempotent for a paid payout.
     *
     * @throws FinanceException
     */
    public function markPaid(SellerPayout $payout, string $utr, ?Model $actor = null, ?string $gatewayPayoutId = null): SellerPayout
    {
        $utr = mb_substr(trim($utr), 0, 40);
        $paidNow = DB::transaction(function () use ($payout, $utr, $gatewayPayoutId, $actor): bool {
            $locked = SellerPayout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($locked->isPaid()) {
                return false;
            }

            if (! in_array($locked->status, [SellerPayout::STATUS_SCHEDULED, SellerPayout::STATUS_PROCESSING], true)) {
                throw new FinanceException(trans('finance::seller_payout.messages.not_payable', ['status' => $locked->status]), 'not_payable');
            }

            $batch = PayoutBatch::query()->lockForUpdate()->findOrFail($locked->payout_batch_id);

            if ($batch->isDraft()) {
                throw new FinanceException(trans('finance::payout_batch.messages.not_approved'), 'not_approved');
            }

            // Manual rail: Finance may transfer right after approval and upload the UTRs.
            if ($batch->isApproved()) {
                $batch->forceFill(['status' => PayoutBatch::STATUS_PROCESSING, 'processed_at' => Carbon::now()])->save();
            }

            $locked->forceFill([
                'status' => SellerPayout::STATUS_PAID,
                'utr' => $utr !== '' ? $utr : $locked->utr,
                'gateway_payout_id' => $gatewayPayoutId ?? $locked->gateway_payout_id,
                'failure_reason' => null,
                'paid_at' => Carbon::now(),
            ])->save();

            $this->ledger->post([
                'seller_id' => $locked->seller_id,
                'payout_id' => $locked->id,
                'type' => SellerLedgerEntry::TYPE_PAYOUT,
                'direction' => SellerLedgerEntry::DEBIT,
                'amount' => $locked->amount,
                'description' => trans('finance::ledger.descriptions.payout', ['payout' => $locked->payout_no, 'utr' => $locked->utr ?? '-']),
                'meta' => ['utr' => $locked->utr],
                'eligible_on' => Carbon::today(),
                'status' => SellerLedgerEntry::STATUS_SETTLED,
                'idempotency_key' => 'payout:'.$locked->id,
                'created_by_type' => $actor === null ? 'system' : 'admin',
                'created_by_id' => $actor?->getKey(),
            ]);

            SellerLedgerEntry::query()->where('payout_id', $locked->id)->update(['status' => SellerLedgerEntry::STATUS_SETTLED]);

            return true;
        });

        $this->refreshBatch($payout->payout_batch_id);
        $payout->refresh();

        if ($paidNow) {
            $this->notifyPaid($payout);
        }

        return $payout;
    }

    /**
     * The transfer failed: entries go back to the next batch, Finance is alerted.
     */
    public function markFailed(SellerPayout $payout, string $reason, ?Model $actor = null, ?string $gatewayPayoutId = null): SellerPayout
    {
        $failedNow = DB::transaction(function () use ($payout, $reason, $gatewayPayoutId): bool {
            $locked = SellerPayout::query()->lockForUpdate()->findOrFail($payout->id);

            if (! in_array($locked->status, [SellerPayout::STATUS_SCHEDULED, SellerPayout::STATUS_PROCESSING, SellerPayout::STATUS_ON_HOLD], true)) {
                return false;
            }

            $locked->forceFill([
                'status' => SellerPayout::STATUS_FAILED,
                'failure_reason' => mb_substr($reason !== '' ? $reason : 'Payout failed', 0, 255),
                'gateway_payout_id' => $gatewayPayoutId ?? $locked->gateway_payout_id,
            ])->save();

            $this->unlink($locked);

            return true;
        });

        $this->refreshBatch($payout->payout_batch_id);
        $payout->refresh();

        if ($failedNow) {
            $this->notifyFailed($payout);
        }

        return $payout;
    }

    /**
     * The bank bounced a paid payout: the amount is owed to the seller again (adjustment
     * credit, payable in the next batch).
     */
    public function markReversed(SellerPayout $payout, string $reason, ?Model $actor = null): SellerPayout
    {
        $reversedNow = DB::transaction(function () use ($payout, $reason, $actor): bool {
            $locked = SellerPayout::query()->lockForUpdate()->with('seller')->findOrFail($payout->id);

            if (! $locked->isPaid()) {
                return false;
            }

            $locked->forceFill(['status' => SellerPayout::STATUS_REVERSED, 'failure_reason' => mb_substr($reason, 0, 255)])->save();

            $this->ledger->post([
                'seller_id' => $locked->seller_id,
                'type' => SellerLedgerEntry::TYPE_ADJUSTMENT,
                'direction' => SellerLedgerEntry::CREDIT,
                'amount' => $locked->amount,
                'description' => trans('finance::ledger.descriptions.payout_reversed', ['payout' => $locked->payout_no]),
                'meta' => ['reversed_payout_id' => $locked->id],
                'eligible_on' => Carbon::today(),
                'status' => $locked->seller === null ? SellerLedgerEntry::STATUS_ON_HOLD : $this->holds->initialStatus($locked->seller, Carbon::today()),
                'idempotency_key' => 'payout_reversal:'.$locked->id,
                'created_by_type' => $actor === null ? 'system' : 'admin',
                'created_by_id' => $actor?->getKey(),
            ]);

            return true;
        });

        $this->refreshBatch($payout->payout_batch_id);
        $payout->refresh();

        if ($reversedNow) {
            $this->notifyFailed($payout);
        }

        return $payout;
    }

    /**
     * Retry a failed payout: its original entries are linked again (only if none was paid out
     * meanwhile) and it is sent with a new idempotency key.
     *
     * @throws FinanceException
     */
    public function retry(SellerPayout $payout): SellerPayout
    {
        DB::transaction(function () use ($payout): void {
            $locked = SellerPayout::query()->lockForUpdate()->with('batch')->findOrFail($payout->id);

            if ($locked->status !== SellerPayout::STATUS_FAILED) {
                throw new FinanceException(trans('finance::seller_payout.messages.not_retryable'), 'not_retryable');
            }

            $ids = array_map('intval', (array) $locked->entry_ids);
            $entries = SellerLedgerEntry::query()->whereIn('id', $ids)->lockForUpdate()->get();
            $free = $entries->every(fn (SellerLedgerEntry $entry): bool => $entry->payout_id === null && $entry->status === SellerLedgerEntry::STATUS_ELIGIBLE);

            if ($ids === [] || $entries->count() !== count($ids) || ! $free) {
                throw new FinanceException(trans('finance::seller_payout.messages.entries_moved'), 'entries_moved');
            }

            SellerLedgerEntry::query()->whereIn('id', $ids)->update(['payout_id' => $locked->id]);

            $locked->forceFill([
                'status' => SellerPayout::STATUS_SCHEDULED,
                'idempotency_key' => (string) Str::uuid(),
                'gateway_payout_id' => null,
                'failure_reason' => null,
            ])->save();
        });

        $payout->refresh();
        $batch = PayoutBatch::query()->findOrFail($payout->payout_batch_id);

        if (in_array($batch->status, [PayoutBatch::STATUS_PROCESSING, PayoutBatch::STATUS_PARTIALLY_FAILED, PayoutBatch::STATUS_COMPLETED], true)) {
            $this->send($payout);
        }

        $this->refreshBatch($batch->id);

        return $payout->refresh();
    }

    /**
     * Manual gateway: UTRs from the bank's statement, payout_no => UTR. Returns the payouts
     * marked paid; unknown or unpayable rows are returned as errors.
     *
     * @param  array<string, string>  $utrs
     * @return array{paid: int, errors: list<string>}
     */
    public function uploadUtrs(PayoutBatch $batch, array $utrs, ?Model $actor = null): array
    {
        $paid = 0;
        $errors = [];
        $payouts = SellerPayout::query()->where('payout_batch_id', $batch->id)->get()->keyBy('payout_no');

        foreach ($utrs as $payoutNo => $utr) {
            $payout = $payouts->get(strtoupper(trim((string) $payoutNo)));

            if (! $payout instanceof SellerPayout || trim($utr) === '') {
                $errors[] = trans('finance::payout_batch.messages.utr_unknown', ['payout' => $payoutNo]);

                continue;
            }

            try {
                if (! $payout->isPaid()) {
                    $this->markPaid($payout, $utr, $actor);
                    $paid++;
                }
            } catch (FinanceException $e) {
                $errors[] = $payout->payout_no.': '.$e->getMessage();
            }
        }

        return ['paid' => $paid, 'errors' => $errors];
    }

    /**
     * Rows of the bank bulk-transfer file for the manual gateway.
     *
     * @return list<array<string, string>>
     */
    public function bankFileRows(PayoutBatch $batch): array
    {
        return SellerPayout::query()
            ->where('payout_batch_id', $batch->id)
            ->whereIn('status', [SellerPayout::STATUS_SCHEDULED, SellerPayout::STATUS_PROCESSING])
            ->with(['bankAccount', 'seller'])
            ->orderBy('id')
            ->get()
            ->map(fn (SellerPayout $payout): array => [
                'payout_no' => $payout->payout_no,
                'beneficiary_name' => (string) $payout->bankAccount?->account_holder_name,
                'account_number' => (string) $payout->bankAccount?->account_number,
                'ifsc' => (string) $payout->bankAccount?->ifsc,
                'amount' => $payout->amount->toDecimal(),
                'narration' => mb_substr(config('app.name').' '.$payout->payout_no, 0, 30),
                'seller_code' => (string) $payout->seller?->code,
            ])
            ->values()
            ->all();
    }

    public function refreshBatch(int $batchId): PayoutBatch
    {
        return DB::transaction(function () use ($batchId): PayoutBatch {
            $batch = PayoutBatch::query()->lockForUpdate()->findOrFail($batchId);

            if ($batch->isDraft() || $batch->isApproved()) {
                return $batch;
            }

            $statuses = SellerPayout::query()->where('payout_batch_id', $batchId)->pluck('status')->unique()->values()->all();
            $open = array_intersect($statuses, [SellerPayout::STATUS_SCHEDULED, SellerPayout::STATUS_PROCESSING, SellerPayout::STATUS_ON_HOLD]) !== [];
            $failed = array_intersect($statuses, [SellerPayout::STATUS_FAILED, SellerPayout::STATUS_REVERSED]) !== [];

            $status = match (true) {
                $open => PayoutBatch::STATUS_PROCESSING,
                $failed => PayoutBatch::STATUS_PARTIALLY_FAILED,
                default => PayoutBatch::STATUS_COMPLETED,
            };

            if ($batch->status !== $status) {
                $batch->forceFill(['status' => $status])->save();
            }

            return $batch;
        });
    }

    /**
     * "eligible" is the authority: the daily release run only moves entries whose eligible_on
     * has come, and adjustments / penalties / claims are payable at once.
     */
    protected function buildLocked(Carbon $cycleEnd, ?Model $actor): ?PayoutBatch
    {
        $candidates = SellerLedgerEntry::query()
            ->where('status', SellerLedgerEntry::STATUS_ELIGIBLE)
            ->whereNull('payout_id')
            ->distinct()
            ->orderBy('seller_id')
            ->pluck('seller_id');

        if ($candidates->isEmpty()) {
            return null;
        }

        $gateway = FinanceSettings::payoutGateway();
        $minimum = FinanceSettings::minPayoutAmount();
        $batch = null;
        $total = Money::zero();
        $count = 0;

        foreach (Seller::query()->whereIn('id', $candidates)->orderBy('id')->get() as $seller) {
            /** @var Seller $seller */
            $this->holds->forget($seller->id);

            if ($this->holds->isHeld($seller)) {
                $this->holds->sync($seller);

                continue;
            }

            $bank = SellerBankAccount::query()
                ->where('seller_id', $seller->id)
                ->where('is_primary', config('core.yes'))
                ->where('verification_status', SellerBankAccount::STATUS_VERIFIED)
                ->first();

            if ($bank === null) {
                continue;
            }

            /** @var Collection<int, SellerLedgerEntry> $entries */
            $entries = SellerLedgerEntry::query()
                ->where('seller_id', $seller->id)
                ->where('status', SellerLedgerEntry::STATUS_ELIGIBLE)
                ->whereNull('payout_id')
                ->lockForUpdate()
                ->get();

            $net = $entries->reduce(fn (Money $carry, SellerLedgerEntry $entry): Money => $carry->plus($entry->signedValue()), Money::zero());

            // Negative balances carry forward; small ones wait for the next cycle.
            if (! $net->isPositive() || $net->compare($minimum) < 0) {
                continue;
            }

            $batch ??= PayoutBatch::query()->create([
                'batch_no' => $this->sequences->next('payout_batch:'.$cycleEnd->format('ym'), 'PB-'.$cycleEnd->format('ym').'-', 4),
                'cycle_start' => $entries->min('eligible_on'),
                'cycle_end' => $cycleEnd,
                'status' => PayoutBatch::STATUS_DRAFT,
                'gateway' => $gateway,
                'total_amount' => Money::zero(),
                'created_by' => $actor?->getKey(),
            ]);

            $payout = SellerPayout::query()->create([
                'payout_batch_id' => $batch->id,
                'payout_no' => $this->sequences->payoutNumber($cycleEnd),
                'seller_id' => $seller->id,
                'bank_account_id' => $bank->id,
                'amount' => $net,
                'status' => SellerPayout::STATUS_SCHEDULED,
                'gateway' => $gateway,
                'idempotency_key' => (string) Str::uuid(),
                'entry_ids' => $entries->modelKeys(),
            ]);

            SellerLedgerEntry::query()->whereIn('id', $entries->modelKeys())->update(['payout_id' => $payout->id]);

            $total = $total->plus($net);
            $count++;
        }

        if ($batch === null) {
            return null;
        }

        $batch->forceFill(['total_amount' => $total, 'sellers_count' => $count])->save();

        return $batch;
    }

    protected function markProcessing(SellerPayout $payout, ?string $gatewayPayoutId): SellerPayout
    {
        DB::transaction(function () use ($payout, $gatewayPayoutId): void {
            $locked = SellerPayout::query()->lockForUpdate()->findOrFail($payout->id);

            if (! in_array($locked->status, [SellerPayout::STATUS_SCHEDULED, SellerPayout::STATUS_PROCESSING], true)) {
                return;
            }

            $locked->forceFill([
                'status' => SellerPayout::STATUS_PROCESSING,
                'gateway_payout_id' => $gatewayPayoutId ?? $locked->gateway_payout_id,
                'failure_reason' => null,
            ])->save();
        });

        $this->refreshBatch($payout->payout_batch_id);

        return $payout->refresh();
    }

    /**
     * Entries of a failed payout return to the pool for the next batch.
     */
    protected function unlink(SellerPayout $payout): void
    {
        SellerLedgerEntry::query()
            ->where('payout_id', $payout->id)
            ->where('status', '!=', SellerLedgerEntry::STATUS_SETTLED)
            ->update(['payout_id' => null]);
    }

    protected function requestFor(SellerPayout $payout): PayoutRequest
    {
        $bank = $payout->bankAccount;

        return new PayoutRequest(
            reference: $payout->payout_no,
            amount: $payout->amount,
            idempotencyKey: $payout->idempotency_key,
            beneficiary: [
                'type' => 'bank',
                'name' => (string) $bank?->account_holder_name,
                'account_number' => (string) $bank?->account_number,
                'ifsc' => (string) $bank?->ifsc,
                'fund_account_id' => $bank?->razorpayx_fund_account_id,
            ],
            purpose: PayoutRequest::PURPOSE_PAYOUT,
            narration: config('app.name').' '.$payout->payout_no,
            contactReference: 'seller-'.$payout->seller_id,
            notes: ['payout_no' => $payout->payout_no, 'seller_id' => (string) $payout->seller_id],
        );
    }

    protected function notifyPaid(SellerPayout $payout): void
    {
        try {
            $seller = Seller::query()->find($payout->seller_id);

            if ($seller !== null) {
                $this->hub->eventForSeller('seller.payout.processed', $seller, [
                    'payout_no' => $payout->payout_no,
                    'amount' => $payout->amount->format(true),
                    'utr' => (string) ($payout->utr ?? '-'),
                    'url' => frontRoute('seller.payments.payouts.show', ['payoutNo' => $payout->payout_no]),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function notifyFailed(SellerPayout $payout): void
    {
        try {
            $this->hub->eventForAdmins('admin.payout.failed', [
                'payout_no' => $payout->payout_no,
                'store' => (string) Seller::query()->find($payout->seller_id)?->displayName(),
                'error' => (string) $payout->failure_reason,
                'url' => route('admin.payout_batch.show', updateUrlParams([$payout->payout_batch_id])),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
