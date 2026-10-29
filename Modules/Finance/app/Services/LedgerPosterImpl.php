<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Finance\Models\FeeRule;
use Modules\Finance\Models\LedgerAdjustment;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Support\FinanceSettings;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Contracts\LedgerPoster;
use Modules\Order\Events\SellerPenaltyIncurred;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\Product;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Models\ReturnRequestItem;
use Modules\Seller\Models\Seller;

/**
 * Posting rules of the seller ledger (plan M17 §5.2). Every entry carries an idempotency key,
 * so replaying an event posts nothing new. Entries are append-only: returns, reversals and
 * corrections are new entries.
 *
 * - delivered: credit sale (price × delivered qty); debit commission (+ GST), platform fee
 *   (+ GST), TCS (GST sellers, % of taxable value), TDS (% of the sale; higher without PAN) and
 *   forward shipping when the seller pays it; payable after the return window + payout delay;
 * - RTO delivered: RTO freight when the seller pays it (no sale was ever credited);
 * - return refunded: the sale and its deductions reversed for the returned units, plus reverse
 *   shipping when a seller-charged rule says so;
 * - seller cancel / SLA breach: the penalty; accepted claim: claim credit; manual adjustments.
 */
class LedgerPosterImpl implements LedgerPoster
{
    /**
     * Ledger types reversed (with their reversal type) when units come back.
     */
    private const REVERSALS = [
        SellerLedgerEntry::TYPE_SALE => SellerLedgerEntry::TYPE_RETURN_REVERSAL,
        SellerLedgerEntry::TYPE_COMMISSION => SellerLedgerEntry::TYPE_COMMISSION_REVERSAL,
        SellerLedgerEntry::TYPE_PLATFORM_FEE => SellerLedgerEntry::TYPE_PLATFORM_FEE_REVERSAL,
        SellerLedgerEntry::TYPE_TCS => SellerLedgerEntry::TYPE_TCS_REVERSAL,
        SellerLedgerEntry::TYPE_TDS => SellerLedgerEntry::TYPE_TDS_REVERSAL,
    ];

    public function __construct(
        private readonly CommissionResolver $commissions,
        private readonly FeeRuleResolver $feeRules,
        private readonly PayoutHoldService $holds,
    ) {}

    /**
     * Order confirmed (M12 hook): no money is owed yet, but the commission rule behind each
     * item's snapshot is recorded (commission_rule_id), as of the order date.
     */
    public function postSale(SellerOrder $sellerOrder): void
    {
        $sellerOrder->loadMissing(['items', 'salesOrder', 'seller']);
        $on = $sellerOrder->salesOrder?->placed_at ?? $sellerOrder->created_at ?? Carbon::now();
        $products = Product::query()->withTrashed()->with('category')->whereIn('id', $sellerOrder->items->pluck('product_id')->unique())->get()->keyBy('id');

        foreach ($sellerOrder->items as $item) {
            if ($item->commission_rule_id !== null) {
                continue;
            }

            $rule = $this->commissions->for($sellerOrder->seller_id, $products->get($item->product_id)?->category, Carbon::parse($on));

            if ($rule !== null) {
                SalesOrderItem::query()->whereKey($item->id)->whereNull('commission_rule_id')->update(['commission_rule_id' => $rule->ruleId]);
            }
        }
    }

    /**
     * The seller order reached the customer. Returns the number of entries created.
     */
    public function postDelivered(SellerOrder $sellerOrder): int
    {
        $sellerOrder->loadMissing(['items', 'seller']);
        $seller = $sellerOrder->seller;

        if ($seller === null) {
            return 0;
        }

        $eligibleOn = $this->deliveryEligibleOn($sellerOrder);
        $status = $this->holds->initialStatus($seller, $eligibleOn);
        $hasPan = $seller->pan_last4 !== null && $seller->pan_last4 !== '';
        $tdsRate = $hasPan ? FinanceSettings::tdsRate() : FinanceSettings::tdsRateWithoutPan();
        $tcsRate = $seller->isGstRegistered() ? FinanceSettings::tcsRate() : '0';
        $created = 0;

        return DB::transaction(function () use ($sellerOrder, $seller, $eligibleOn, $status, $tdsRate, $tcsRate, &$created): int {
            foreach ($sellerOrder->items as $item) {
                $qty = $item->quantity - $item->cancelled_qty;

                if ($item->isCancelled() || $qty <= 0) {
                    continue;
                }

                $base = [
                    'seller_id' => $seller->id,
                    'seller_order_id' => $sellerOrder->id,
                    'sales_order_item_id' => $item->id,
                    'eligible_on' => $eligibleOn,
                    'status' => $status,
                ];
                $label = ['item' => $item->name, 'qty' => $qty, 'order' => $sellerOrder->sub_order_no];
                $sale = $item->price->times($qty);
                $taxable = self::prorate($item->taxable_value, $qty, $item->quantity);
                $commission = self::prorate($item->commission_amount, $qty, $item->quantity);
                $platformFee = $this->feeRules->sellerCharge(FeeRule::CODE_FIXED_CLOSING_FEE, $seller, $item->price)->times($qty)
                    ->plus($this->feeRules->sellerCharge(FeeRule::CODE_PAYMENT_GATEWAY_FEE, $seller, $item->price)->times($qty));

                $created += (int) $this->post($base + [
                    'type' => SellerLedgerEntry::TYPE_SALE,
                    'direction' => SellerLedgerEntry::CREDIT,
                    'amount' => $sale,
                    'base_amount' => $taxable,
                    'description' => trans('finance::ledger.descriptions.sale', $label),
                    'meta' => ['qty' => $qty],
                    'idempotency_key' => 'sale:item:'.$item->id,
                ]);

                if ($commission->isPositive()) {
                    $created += (int) $this->post($base + [
                        'type' => SellerLedgerEntry::TYPE_COMMISSION,
                        'direction' => SellerLedgerEntry::DEBIT,
                        'amount' => $commission,
                        'tax_amount' => $this->commissions->gstOn($commission),
                        'base_amount' => $sale,
                        'description' => trans('finance::ledger.descriptions.commission', $label),
                        'meta' => ['qty' => $qty, 'rule_id' => $item->commission_rule_id],
                        'idempotency_key' => 'commission:item:'.$item->id,
                    ]);
                }

                if ($platformFee->isPositive()) {
                    $created += (int) $this->post($base + [
                        'type' => SellerLedgerEntry::TYPE_PLATFORM_FEE,
                        'direction' => SellerLedgerEntry::DEBIT,
                        'amount' => $platformFee,
                        'tax_amount' => $this->commissions->gstOn($platformFee),
                        'base_amount' => $sale,
                        'description' => trans('finance::ledger.descriptions.platform_fee', $label),
                        'meta' => ['qty' => $qty],
                        'idempotency_key' => 'platform_fee:item:'.$item->id,
                    ]);
                }

                $tcs = $taxable->percent($tcsRate);

                if ($tcs->isPositive()) {
                    $created += (int) $this->post($base + [
                        'type' => SellerLedgerEntry::TYPE_TCS,
                        'direction' => SellerLedgerEntry::DEBIT,
                        'amount' => $tcs,
                        'base_amount' => $taxable,
                        'description' => trans('finance::ledger.descriptions.tcs', $label + ['rate' => $tcsRate]),
                        'meta' => ['qty' => $qty, 'rate' => $tcsRate, 'inter_state' => $item->igst->isPositive()],
                        'idempotency_key' => 'tcs:item:'.$item->id,
                    ]);
                }

                $tds = $sale->percent($tdsRate);

                if ($tds->isPositive()) {
                    $created += (int) $this->post($base + [
                        'type' => SellerLedgerEntry::TYPE_TDS,
                        'direction' => SellerLedgerEntry::DEBIT,
                        'amount' => $tds,
                        'base_amount' => $sale,
                        'description' => trans('finance::ledger.descriptions.tds', $label + ['rate' => $tdsRate]),
                        'meta' => ['qty' => $qty, 'rate' => $tdsRate],
                        'idempotency_key' => 'tds:item:'.$item->id,
                    ]);
                }
            }

            $forward = $this->forwardShipment($sellerOrder->id);
            $shipping = $forward === null ? Money::zero() : $this->shippingCharge(
                FeeRule::CODE_FORWARD_SHIPPING,
                FinanceSettings::sellerPaysForwardShipping(),
                $seller,
                $forward->freight_charge->plus($forward->cod_charge),
            );

            if ($shipping->isPositive()) {
                $created += (int) $this->post([
                    'seller_id' => $seller->id,
                    'seller_order_id' => $sellerOrder->id,
                    'type' => SellerLedgerEntry::TYPE_LOGISTICS_FORWARD,
                    'direction' => SellerLedgerEntry::DEBIT,
                    'amount' => $shipping,
                    'description' => trans('finance::ledger.descriptions.logistics_forward', ['order' => $sellerOrder->sub_order_no, 'awb' => $forward?->awb]),
                    'eligible_on' => $eligibleOn,
                    'status' => $status,
                    'idempotency_key' => 'logistics_forward:so:'.$sellerOrder->id,
                ]);
            }

            return $created;
        });
    }

    /**
     * An undelivered package came back (RTO). The sale was never credited; only RTO freight
     * is charged when the seller pays it.
     */
    public function postRto(Shipment $shipment): int
    {
        $sellerOrder = SellerOrder::query()->with('seller')->find($shipment->seller_order_id);
        $seller = $sellerOrder?->seller;

        if ($sellerOrder === null || $seller === null || $shipment->direction !== Shipment::DIRECTION_FORWARD) {
            return 0;
        }

        $charge = $this->shippingCharge(FeeRule::CODE_RTO_SHIPPING, FinanceSettings::sellerPaysRtoShipping(), $seller, $shipment->freight_charge->plus($shipment->rto_charge));

        if (! $charge->isPositive()) {
            return 0;
        }

        return (int) $this->post([
            'seller_id' => $seller->id,
            'seller_order_id' => $sellerOrder->id,
            'type' => SellerLedgerEntry::TYPE_LOGISTICS_RTO,
            'direction' => SellerLedgerEntry::DEBIT,
            'amount' => $charge,
            'description' => trans('finance::ledger.descriptions.logistics_rto', ['order' => $sellerOrder->sub_order_no, 'awb' => $shipment->awb]),
            'eligible_on' => Carbon::today(),
            'status' => $this->holds->initialStatus($seller, Carbon::today()),
            'idempotency_key' => 'logistics_rto:so:'.$sellerOrder->id,
        ]);
    }

    /**
     * A return was refunded: reverse the sale and its deductions for the returned units. The
     * reversal is payable with the sale while that is still pending (they net out in one
     * payout); after the sale was paid out, it is deducted from the next payout.
     */
    public function postReturnRefunded(ReturnRequest $return): int
    {
        $return->loadMissing(['items', 'sellerOrder', 'seller']);
        $seller = $return->seller;

        if ($seller === null || $return->type !== ReturnRequest::TYPE_RETURN) {
            return 0;
        }

        return DB::transaction(function () use ($return, $seller): int {
            $created = 0;

            foreach ($return->items as $returnItem) {
                $created += $this->reverseItem($return, $returnItem, $seller);
            }

            $reverse = $return->reverse_shipment_id === null ? null : Shipment::query()->find($return->reverse_shipment_id);
            $charge = $reverse === null ? Money::zero() : $this->feeRules->sellerCharge(FeeRule::CODE_REVERSE_SHIPPING, $seller, $reverse->freight_charge);

            if ($charge->isPositive()) {
                $created += (int) $this->post([
                    'seller_id' => $seller->id,
                    'seller_order_id' => $return->seller_order_id,
                    'return_request_id' => $return->id,
                    'type' => SellerLedgerEntry::TYPE_LOGISTICS_REVERSE,
                    'direction' => SellerLedgerEntry::DEBIT,
                    'amount' => $charge,
                    'description' => trans('finance::ledger.descriptions.logistics_reverse', ['return' => $return->return_no]),
                    'eligible_on' => Carbon::today(),
                    'status' => $this->holds->initialStatus($seller, Carbon::today()),
                    'idempotency_key' => 'logistics_reverse:ret:'.$return->id,
                ]);
            }

            return $created;
        });
    }

    /**
     * Seller cancellation or SLA breach (M14 SellerPenaltyIncurred): the penalty from
     * admin > Settings > Finance, or a seller-charged fee rule. Zero by default.
     */
    public function postPenalty(SellerOrder $sellerOrder, string $kind, string $reason = ''): int
    {
        $seller = $sellerOrder->seller()->first();

        if ($seller === null) {
            return 0;
        }

        $sla = $kind === SellerPenaltyIncurred::KIND_SLA_BREACH;
        $amount = $sla ? FinanceSettings::slaBreachPenalty() : FinanceSettings::sellerCancelPenalty();

        if (! $amount->isPositive()) {
            $amount = $this->feeRules->sellerCharge($sla ? FeeRule::CODE_SLA_BREACH_PENALTY : FeeRule::CODE_SELLER_CANCEL_PENALTY, $seller, $sellerOrder->items_subtotal);
        }

        if (! $amount->isPositive()) {
            return 0;
        }

        return (int) $this->post([
            'seller_id' => $seller->id,
            'seller_order_id' => $sellerOrder->id,
            'type' => SellerLedgerEntry::TYPE_PENALTY,
            'direction' => SellerLedgerEntry::DEBIT,
            'amount' => $amount,
            'description' => trans('finance::ledger.descriptions.penalty_'.($sla ? 'sla' : 'cancel'), ['order' => $sellerOrder->sub_order_no]),
            'meta' => ['kind' => $kind, 'reason' => mb_substr($reason, 0, 200)],
            'eligible_on' => Carbon::today(),
            'status' => $this->holds->initialStatus($seller, Carbon::today()),
            'idempotency_key' => 'penalty:'.$kind.':so:'.$sellerOrder->id,
        ]);
    }

    /**
     * An accepted seller claim (M15) credits its resolution amount.
     */
    public function postClaimCredit(ReturnClaim $claim): int
    {
        if ($claim->status !== ReturnClaim::STATUS_ACCEPTED || $claim->resolution_amount === null || ! $claim->resolution_amount->isPositive()) {
            return 0;
        }

        $seller = Seller::query()->find($claim->seller_id);

        if ($seller === null) {
            return 0;
        }

        return (int) $this->post([
            'seller_id' => $seller->id,
            'seller_order_id' => $claim->seller_order_id,
            'return_request_id' => $claim->return_request_id,
            'type' => SellerLedgerEntry::TYPE_CLAIM_CREDIT,
            'direction' => SellerLedgerEntry::CREDIT,
            'amount' => $claim->resolution_amount,
            'description' => trans('finance::ledger.descriptions.claim_credit', ['claim' => $claim->id]),
            'eligible_on' => Carbon::today(),
            'status' => $this->holds->initialStatus($seller, Carbon::today()),
            'idempotency_key' => 'claim_credit:claim:'.$claim->id,
        ]);
    }

    /**
     * An approved manual adjustment.
     */
    public function postAdjustment(LedgerAdjustment $adjustment, ?Model $approver = null): SellerLedgerEntry
    {
        $seller = Seller::query()->findOrFail($adjustment->seller_id);

        $this->post([
            'seller_id' => $seller->id,
            'seller_order_id' => $adjustment->seller_order_id,
            'type' => SellerLedgerEntry::TYPE_ADJUSTMENT,
            'direction' => $adjustment->direction,
            'amount' => $adjustment->amount,
            'description' => mb_substr(trans('finance::ledger.descriptions.adjustment', ['reason' => $adjustment->reason]), 0, 255),
            'meta' => ['adjustment_id' => $adjustment->id, 'requested_by' => $adjustment->requested_by],
            'eligible_on' => Carbon::today(),
            'status' => $this->holds->initialStatus($seller, Carbon::today()),
            'idempotency_key' => 'adjustment:'.$adjustment->id,
            'created_by_type' => 'admin',
            'created_by_id' => $approver?->getKey(),
        ]);

        return SellerLedgerEntry::query()->where('idempotency_key', 'adjustment:'.$adjustment->id)->firstOrFail();
    }

    /**
     * Insert an entry unless its idempotency key exists. True when it was created.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function post(array $attributes): bool
    {
        $entry = SellerLedgerEntry::query()->createOrFirst(
            ['idempotency_key' => $attributes['idempotency_key']],
            $attributes + [
                'tax_amount' => Money::zero(),
                'posted_at' => Carbon::now(),
                'created_by_type' => 'system',
            ],
        );

        return $entry->wasRecentlyCreated;
    }

    /**
     * $part / $whole of $amount, rounded half up to the paisa.
     */
    public static function prorate(Money $amount, int $part, int $whole): Money
    {
        if ($whole <= 0 || $part >= $whole) {
            return $amount;
        }

        if ($part <= 0) {
            return Money::zero();
        }

        $numerator = $amount->paise * $part;
        $quotient = intdiv($numerator, $whole);
        $remainder = $numerator % $whole;

        return Money::paise($quotient + (abs($remainder) * 2 >= $whole ? ($numerator < 0 ? -1 : 1) : 0));
    }

    /**
     * Return window end (set by M13 on delivery) + payout delay days.
     */
    public function deliveryEligibleOn(SellerOrder $sellerOrder): Carbon
    {
        $windowEnds = $sellerOrder->return_window_ends_at;

        if ($windowEnds === null) {
            $days = (int) $sellerOrder->items->reject(fn (SalesOrderItem $item): bool => $item->isCancelled())->max('return_window_days');
            $windowEnds = ($sellerOrder->delivered_at ?? Carbon::now())->copy()->addDays(max(0, $days));
        }

        return $windowEnds->copy()->startOfDay()->addDays(FinanceSettings::payoutDelayDays());
    }

    protected function reverseItem(ReturnRequest $return, ReturnRequestItem $returnItem, Seller $seller): int
    {
        $originals = SellerLedgerEntry::query()
            ->where('sales_order_item_id', $returnItem->sales_order_item_id)
            ->whereIn('type', array_keys(self::REVERSALS))
            ->get()
            ->keyBy('type');
        $sale = $originals->get(SellerLedgerEntry::TYPE_SALE);

        if (! $sale instanceof SellerLedgerEntry) {
            return 0;
        }

        $saleQty = max(1, (int) ($sale->meta['qty'] ?? 1));
        $alreadyReturned = $this->returnedQty($returnItem->sales_order_item_id, $returnItem->id);
        $qty = min($returnItem->quantity, $saleQty - $alreadyReturned);

        if ($qty <= 0) {
            return 0;
        }

        [$eligibleOn, $status] = $sale->status === SellerLedgerEntry::STATUS_PENDING && $sale->eligible_on !== null
            ? [$sale->eligible_on->copy(), SellerLedgerEntry::STATUS_PENDING]
            : [Carbon::today(), $this->holds->initialStatus($seller, Carbon::today())];

        $share = fn (?Money $amount): ?Money => $amount === null ? null
            : self::prorate($amount, $alreadyReturned + $qty, $saleQty)->minus(self::prorate($amount, $alreadyReturned, $saleQty));
        $created = 0;

        /** @var Collection<string, SellerLedgerEntry> $originals */
        foreach ($originals as $type => $original) {
            $amount = $share($original->amount);

            if (! $amount->isPositive()) {
                continue;
            }

            $reversalType = self::REVERSALS[$type];
            $created += (int) $this->post([
                'seller_id' => $seller->id,
                'seller_order_id' => $original->seller_order_id,
                'sales_order_item_id' => $original->sales_order_item_id,
                'return_request_id' => $return->id,
                'type' => $reversalType,
                'direction' => $original->isCredit() ? SellerLedgerEntry::DEBIT : SellerLedgerEntry::CREDIT,
                'amount' => $amount,
                'tax_amount' => $share($original->tax_amount),
                'base_amount' => $share($original->base_amount),
                'description' => trans('finance::ledger.descriptions.'.$reversalType, ['return' => $return->return_no, 'qty' => $qty]),
                'meta' => array_filter(['qty' => $qty, 'rate' => $original->meta['rate'] ?? null, 'inter_state' => $original->meta['inter_state'] ?? null], fn ($value): bool => $value !== null),
                'eligible_on' => $eligibleOn,
                'status' => $status,
                'idempotency_key' => $reversalType.':ritem:'.$returnItem->id,
            ]);
        }

        return $created;
    }

    /**
     * Units of the order item already reversed by other returns.
     */
    protected function returnedQty(int $orderItemId, int $exceptReturnItemId): int
    {
        return (int) SellerLedgerEntry::query()
            ->where('sales_order_item_id', $orderItemId)
            ->where('type', SellerLedgerEntry::TYPE_RETURN_REVERSAL)
            ->where('idempotency_key', '!=', SellerLedgerEntry::TYPE_RETURN_REVERSAL.':ritem:'.$exceptReturnItemId)
            ->get(['meta'])
            ->sum(fn (SellerLedgerEntry $entry): int => (int) ($entry->meta['qty'] ?? 0));
    }

    protected function forwardShipment(int $sellerOrderId): ?Shipment
    {
        return Shipment::query()
            ->where('seller_order_id', $sellerOrderId)
            ->where('direction', Shipment::DIRECTION_FORWARD)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Shipping the seller pays: the courier's actual charge when the Finance setting says the
     * seller pays it, else a seller-charged fee rule (flat, or % of the actual charge).
     */
    protected function shippingCharge(string $code, bool $sellerPaysActual, Seller $seller, Money $actual): Money
    {
        if ($sellerPaysActual) {
            return $actual;
        }

        return $this->feeRules->sellerCharge($code, $seller, $actual);
    }
}
