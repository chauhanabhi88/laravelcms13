<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Services\SequenceService;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Finance\DTO\PayoutRequest;
use Modules\Finance\DTO\PayoutResult;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Exceptions\PayoutGatewayUnavailable;
use Modules\Finance\Models\ResellerWalletEntry;
use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Finance\Support\FinanceSettings;
use Modules\Finance\Support\PayoutGatewayManager;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Returns\Models\ReturnRequest;

/**
 * Reseller margin wallet (FR-FIN-007, plan M17 §5.8).
 *
 * - Delivered COD reseller order: a margin_credit per item, pending until the return window
 *   ends, then available (release()).
 * - Refunded return: the returned units' margin is reversed.
 * - Withdraw (min ₹100) to UPI or bank: a withdrawal debit at once, the transfer through the
 *   payout gateway; a failed transfer credits the amount back.
 *
 * @phpstan-type WalletBalances array{available: Money, pending: Money, paid: Money}
 */
class ResellerWalletService
{
    public function __construct(
        private readonly PayoutGatewayManager $gateways,
        private readonly SequenceService $sequences,
    ) {}

    public function creditMargins(SellerOrder $sellerOrder): int
    {
        $sellerOrder->loadMissing(['salesOrder', 'items']);
        $order = $sellerOrder->salesOrder;

        if (! $order instanceof SalesOrder || ! $order->isResellerOrder() || ! $order->isCod()) {
            return 0;
        }

        $eligibleOn = ($sellerOrder->return_window_ends_at ?? $sellerOrder->delivered_at ?? Carbon::now())->copy()->startOfDay();
        $created = 0;

        foreach ($sellerOrder->items as $item) {
            /** @var SalesOrderItem $item */
            $qty = $item->quantity - $item->cancelled_qty;

            if ($item->isCancelled() || $qty <= 0 || ! $item->reseller_margin->isPositive()) {
                continue;
            }

            $created += (int) $this->post([
                'customer_id' => $order->customer_id,
                'type' => ResellerWalletEntry::TYPE_MARGIN_CREDIT,
                'direction' => ResellerWalletEntry::CREDIT,
                'amount' => LedgerPosterImpl::prorate($item->reseller_margin, $qty, $item->quantity),
                'sales_order_item_id' => $item->id,
                'description' => trans('finance::wallet.descriptions.margin_credit', ['item' => $item->name, 'order' => $order->order_no]),
                'status' => $eligibleOn->greaterThan(Carbon::today()) ? ResellerWalletEntry::STATUS_PENDING : ResellerWalletEntry::STATUS_AVAILABLE,
                'eligible_on' => $eligibleOn,
                'idempotency_key' => 'margin:item:'.$item->id,
            ]);
        }

        return $created;
    }

    /**
     * Reverse the margin of the returned units.
     */
    public function reverseForReturn(ReturnRequest $return): int
    {
        $return->loadMissing('items');
        $created = 0;

        foreach ($return->items as $returnItem) {
            $credit = ResellerWalletEntry::query()->where('idempotency_key', 'margin:item:'.$returnItem->sales_order_item_id)->first();
            $item = SalesOrderItem::query()->find($returnItem->sales_order_item_id);

            if ($credit === null || $item === null) {
                continue;
            }

            // Returns never exceed the delivered units, so each return item reverses its own share.
            $qty = max(1, $item->quantity - $item->cancelled_qty);
            $amount = LedgerPosterImpl::prorate($credit->amount, min($returnItem->quantity, $qty), $qty);

            if (! $amount->isPositive()) {
                continue;
            }

            $created += (int) $this->post([
                'customer_id' => $credit->customer_id,
                'type' => ResellerWalletEntry::TYPE_REVERSAL,
                'direction' => ResellerWalletEntry::DEBIT,
                'amount' => $amount,
                'sales_order_item_id' => $item->id,
                'description' => trans('finance::wallet.descriptions.reversal', ['return' => $return->return_no]),
                'status' => $credit->status === ResellerWalletEntry::STATUS_PENDING ? ResellerWalletEntry::STATUS_PENDING : ResellerWalletEntry::STATUS_AVAILABLE,
                'eligible_on' => $credit->status === ResellerWalletEntry::STATUS_PENDING ? $credit->eligible_on : Carbon::today(),
                'idempotency_key' => 'margin_reversal:ritem:'.$returnItem->id,
            ]);
        }

        return $created;
    }

    /**
     * Pending entries whose return window ended become available.
     */
    public function release(?Carbon $today = null): int
    {
        return ResellerWalletEntry::query()
            ->where('status', ResellerWalletEntry::STATUS_PENDING)
            ->whereDate('eligible_on', '<=', ($today ?? Carbon::today())->toDateString())
            ->update(['status' => ResellerWalletEntry::STATUS_AVAILABLE]);
    }

    /**
     * @return WalletBalances
     */
    public function balances(int $customerId): array
    {
        $entries = ResellerWalletEntry::query()->forCustomer($customerId)->get(['type', 'direction', 'amount', 'status']);
        $sum = fn (callable $filter): Money => $entries->filter($filter)->reduce(fn (Money $carry, ResellerWalletEntry $entry): Money => $carry->plus($entry->signedAmount()), Money::zero());

        return [
            'available' => $sum(fn (ResellerWalletEntry $entry): bool => $entry->status !== ResellerWalletEntry::STATUS_PENDING),
            'pending' => $sum(fn (ResellerWalletEntry $entry): bool => $entry->status === ResellerWalletEntry::STATUS_PENDING),
            'paid' => $entries
                ->filter(fn (ResellerWalletEntry $entry): bool => $entry->type === ResellerWalletEntry::TYPE_WITHDRAWAL && $entry->status === ResellerWalletEntry::STATUS_PAID)
                ->reduce(fn (Money $carry, ResellerWalletEntry $entry): Money => $carry->plus($entry->amount), Money::zero()),
        ];
    }

    /**
     * @param  array<string, string>  $beneficiary  ['vpa' => …] or ['account_holder', 'account_number', 'ifsc']
     *
     * @throws FinanceException
     */
    public function withdraw(Customer $customer, Money $amount, string $mode, array $beneficiary): ResellerWithdrawal
    {
        if (! $customer->isReseller()) {
            throw new FinanceException(trans('finance::wallet.errors.not_reseller'), 'not_reseller');
        }

        $minimum = FinanceSettings::resellerMinWithdrawal();

        if ($amount->compare($minimum) < 0) {
            throw new FinanceException(trans('finance::wallet.errors.below_minimum', ['minimum' => $minimum->format()]), 'below_minimum');
        }

        $withdrawal = DB::transaction(function () use ($customer, $amount, $mode, $beneficiary): ResellerWithdrawal {
            Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($amount->compare($this->balances($customer->id)['available']) > 0) {
                throw new FinanceException(trans('finance::wallet.errors.insufficient'), 'insufficient');
            }

            $withdrawal = ResellerWithdrawal::query()->create([
                'customer_id' => $customer->id,
                'withdrawal_no' => $this->sequences->next('withdrawal:'.Carbon::now()->format('ym'), 'WDL-'.Carbon::now()->format('ym').'-'),
                'amount' => $amount,
                'mode' => $mode === ResellerWithdrawal::MODE_UPI ? ResellerWithdrawal::MODE_UPI : ResellerWithdrawal::MODE_BANK,
                'beneficiary' => $beneficiary,
                'beneficiary_label' => self::beneficiaryLabel($beneficiary),
                'status' => ResellerWithdrawal::STATUS_PENDING,
                'gateway' => FinanceSettings::payoutGateway(),
                'idempotency_key' => (string) Str::uuid(),
            ]);

            $this->post([
                'customer_id' => $customer->id,
                'type' => ResellerWalletEntry::TYPE_WITHDRAWAL,
                'direction' => ResellerWalletEntry::DEBIT,
                'amount' => $amount,
                'withdrawal_id' => $withdrawal->id,
                'description' => trans('finance::wallet.descriptions.withdrawal', ['no' => $withdrawal->withdrawal_no]),
                'status' => ResellerWalletEntry::STATUS_AVAILABLE,
                'eligible_on' => Carbon::today(),
                'idempotency_key' => 'withdrawal:'.$withdrawal->id,
            ]);

            return $withdrawal;
        });

        return $this->send($withdrawal);
    }

    public function send(ResellerWithdrawal $withdrawal): ResellerWithdrawal
    {
        if ($withdrawal->status !== ResellerWithdrawal::STATUS_PENDING) {
            return $withdrawal;
        }

        $beneficiary = (array) $withdrawal->beneficiary;
        $customer = Customer::query()->find($withdrawal->customer_id);
        $request = new PayoutRequest(
            reference: $withdrawal->withdrawal_no,
            amount: $withdrawal->amount,
            idempotencyKey: $withdrawal->idempotency_key,
            beneficiary: $withdrawal->mode === ResellerWithdrawal::MODE_UPI
                ? ['type' => 'vpa', 'name' => (string) $customer?->displayName(), 'vpa' => (string) ($beneficiary['vpa'] ?? '')]
                : ['type' => 'bank', 'name' => (string) ($beneficiary['account_holder'] ?? ''), 'account_number' => (string) ($beneficiary['account_number'] ?? ''), 'ifsc' => (string) ($beneficiary['ifsc'] ?? '')],
            purpose: PayoutRequest::PURPOSE_PAYOUT,
            narration: 'Earnings '.$withdrawal->withdrawal_no,
            contactReference: 'customer-'.$withdrawal->customer_id,
            notes: ['withdrawal_no' => $withdrawal->withdrawal_no],
        );

        try {
            $result = $this->gateways->for((string) ($withdrawal->gateway ?: FinanceSettings::payoutGateway()))->pay($request);
        } catch (PayoutGatewayUnavailable $e) {
            report($e);

            return $withdrawal;
        }

        return $this->applyResult($withdrawal, $result);
    }

    public function applyResult(ResellerWithdrawal $withdrawal, PayoutResult $result, ?Model $actor = null): ResellerWithdrawal
    {
        return match ($result->status) {
            PayoutResult::PAID => $this->markPaid($withdrawal, (string) $result->utr, $result->gatewayPayoutId),
            PayoutResult::FAILED => $this->markFailed($withdrawal, (string) $result->error, $result->gatewayPayoutId),
            default => $this->markProcessing($withdrawal, $result->gatewayPayoutId),
        };
    }

    public function markPaid(ResellerWithdrawal $withdrawal, string $utr, ?string $gatewayPayoutId = null): ResellerWithdrawal
    {
        DB::transaction(function () use ($withdrawal, $utr, $gatewayPayoutId): void {
            $locked = ResellerWithdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if (! $locked->isOpen()) {
                return;
            }

            $locked->forceFill([
                'status' => ResellerWithdrawal::STATUS_PAID,
                'utr' => mb_substr(trim($utr), 0, 40) ?: $locked->utr,
                'gateway_payout_id' => $gatewayPayoutId ?? $locked->gateway_payout_id,
                'failure_reason' => null,
                'paid_at' => Carbon::now(),
            ])->save();

            ResellerWalletEntry::query()->where('withdrawal_id', $locked->id)->where('type', ResellerWalletEntry::TYPE_WITHDRAWAL)
                ->update(['status' => ResellerWalletEntry::STATUS_PAID]);
        });

        return $withdrawal->refresh();
    }

    /**
     * The transfer failed: the amount is credited back to the wallet.
     */
    public function markFailed(ResellerWithdrawal $withdrawal, string $reason, ?string $gatewayPayoutId = null): ResellerWithdrawal
    {
        DB::transaction(function () use ($withdrawal, $reason, $gatewayPayoutId): void {
            $locked = ResellerWithdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if (! $locked->isOpen()) {
                return;
            }

            $locked->forceFill([
                'status' => ResellerWithdrawal::STATUS_FAILED,
                'failure_reason' => mb_substr($reason !== '' ? $reason : 'Payout failed', 0, 255),
                'gateway_payout_id' => $gatewayPayoutId ?? $locked->gateway_payout_id,
            ])->save();

            $this->post([
                'customer_id' => $locked->customer_id,
                'type' => ResellerWalletEntry::TYPE_REVERSAL,
                'direction' => ResellerWalletEntry::CREDIT,
                'amount' => $locked->amount,
                'withdrawal_id' => $locked->id,
                'description' => trans('finance::wallet.descriptions.withdrawal_failed', ['no' => $locked->withdrawal_no]),
                'status' => ResellerWalletEntry::STATUS_AVAILABLE,
                'eligible_on' => Carbon::today(),
                'idempotency_key' => 'withdrawal_reversal:'.$locked->id,
            ]);
        });

        return $withdrawal->refresh();
    }

    /**
     * "ab•••@okbank" or "A/c ••1234".
     *
     * @param  array<string, string>  $beneficiary
     */
    public static function beneficiaryLabel(array $beneficiary): string
    {
        if (isset($beneficiary['vpa'])) {
            [$user, $handle] = array_pad(explode('@', (string) $beneficiary['vpa'], 2), 2, '');

            return mb_substr(mb_substr($user, 0, 2).'•••@'.$handle, 0, 60);
        }

        return 'A/c ••'.substr((string) ($beneficiary['account_number'] ?? ''), -4);
    }

    protected function markProcessing(ResellerWithdrawal $withdrawal, ?string $gatewayPayoutId): ResellerWithdrawal
    {
        ResellerWithdrawal::query()
            ->whereKey($withdrawal->id)
            ->whereIn('status', [ResellerWithdrawal::STATUS_PENDING, ResellerWithdrawal::STATUS_PROCESSING])
            ->update([
                'status' => ResellerWithdrawal::STATUS_PROCESSING,
                'gateway_payout_id' => $gatewayPayoutId ?? $withdrawal->gateway_payout_id,
                'updated_at' => Carbon::now(),
            ]);

        return $withdrawal->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function post(array $attributes): bool
    {
        return ResellerWalletEntry::query()->createOrFirst(['idempotency_key' => $attributes['idempotency_key']], $attributes)->wasRecentlyCreated;
    }
}
