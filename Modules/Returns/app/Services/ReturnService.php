<?php

declare(strict_types=1);

namespace Modules\Returns\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Services\SequenceService;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\ShipmentService;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderStatusService;
use Modules\Payment\Models\Payment;
use Modules\Product\Services\InventoryService;
use Modules\Returns\DTO\ReturnSubmission;
use Modules\Returns\Events\ReturnRequested;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Models\ReturnRequestImage;
use Modules\Returns\Models\ReturnRequestItem;
use Modules\Returns\Support\ReturnNotifier;
use Modules\Returns\Support\ReturnPhotoStore;
use Modules\Returns\Support\ReturnSettings;
use Throwable;

/**
 * The return lifecycle (plan M15 §5.3–§5.5, SRS §7.3):
 *
 * create → requested (auto-approved by the rules, else held for CX) → approved → a reverse
 * shipment through Logistics → pickup_scheduled → picked_up (trusted customers are refunded,
 * or their exchange shipped, here) → received → the seller's QC: passed (restock, then refund
 * or exchange order) or failed (CX refunds anyway / partly, or closes; the seller may claim).
 *
 * Every status change is a locked transaction; courier, gateway and notification calls run
 * after commit. The order items and the seller order follow: items become return_requested
 * while a return holds them, returned / exchanged once accepted back; the seller order is
 * return_requested while returns are open, then returned (nothing left) or delivered again.
 */
class ReturnService
{
    public function __construct(
        private readonly ReturnEligibilityService $eligibility,
        private readonly ReturnRefundService $refunds,
        private readonly ExchangeService $exchanges,
        private readonly ReturnClaimService $claims,
        private readonly OrderStatusService $orderStatus,
        private readonly InventoryService $inventory,
        private readonly ShipmentService $shipments,
        private readonly SequenceService $sequences,
        private readonly ReturnPhotoStore $photos,
        private readonly ReturnNotifier $notifier,
    ) {}

    /**
     * Raise a return (FR-RET-003). Photos are draft paths of this customer's uploads.
     *
     * @throws ReturnException
     */
    public function create(Customer $customer, ReturnSubmission $submission): ReturnRequest
    {
        $this->validateSubmission($customer, $submission);

        /** @var ReturnRequest $return */
        $return = DB::transaction(function () use ($customer, $submission): ReturnRequest {
            $sellerOrder = SellerOrder::query()->lockForUpdate()->find($submission->sellerOrderId);

            if ($sellerOrder === null) {
                throw ReturnException::notAllowed();
            }

            $eligible = $this->eligibility->eligibleItems($sellerOrder, $customer);

            if ($eligible->isEmpty()) {
                throw new ReturnException(trans('returns::front.errors.not_eligible'), 'not_eligible');
            }

            $quantities = [];

            foreach ($submission->items as $itemId => $line) {
                $candidate = $eligible->get((int) $itemId);
                $qty = (int) $line['quantity'];

                if ($candidate === null) {
                    throw ReturnException::field('items', 'item_not_returnable');
                }

                if ($qty < 1 || $qty > $candidate->maxQty) {
                    throw ReturnException::field('items', 'qty_invalid', ['max' => (string) $candidate->maxQty]);
                }

                if (! $candidate->allows($submission->type)) {
                    throw ReturnException::field('type', 'type_not_allowed');
                }

                if ($submission->type === ReturnRequest::TYPE_EXCHANGE && ! $candidate->allowsExchangeTo((int) $line['exchange_variant_id'])) {
                    throw ReturnException::field('items', 'exchange_variant_invalid');
                }

                $quantities[(int) $itemId] = $qty;
            }

            $order = SalesOrder::query()->with('shippingAddress')->findOrFail($sellerOrder->sales_order_id);
            [$mode, $beneficiary] = $this->refundDestination($order, $submission);
            $quote = $this->refunds->quote($sellerOrder, $quantities, $submission->reasonCode);
            $isReturn = $submission->type === ReturnRequest::TYPE_RETURN;
            $returnNo = $this->sequences->returnNumber();

            $return = ReturnRequest::query()->create([
                'return_no' => $returnNo,
                'sales_order_id' => $order->id,
                'seller_order_id' => $sellerOrder->id,
                'customer_id' => $customer->id,
                'seller_id' => $sellerOrder->seller_id,
                'type' => $submission->type,
                'status' => ReturnRequest::STATUS_REQUESTED,
                'reason_code' => $submission->reasonCode,
                'comment' => $submission->comment !== null && trim($submission->comment) !== '' ? mb_substr(trim($submission->comment), 0, 1000) : null,
                'refund_mode' => $mode,
                'refund_beneficiary' => $beneficiary,
                'refund_amount' => $isReturn ? $quote->total() : Money::zero(),
                'delivery_refund' => $isReturn ? $quote->delivery : Money::zero(),
                'refund_on' => ReturnRequest::REFUND_ON_QC,
                'pickup_address' => $this->pickupAddress($customer, $order, $submission->pickupAddressId),
                'auto_approved' => (int) config('core.no'),
                'needs_attention' => (int) config('core.no'),
            ]);
            $return->statusHistories()->create([
                'from_status' => null,
                'to_status' => ReturnRequest::STATUS_REQUESTED,
                'actor_type' => 'customer',
                'actor_id' => $customer->id,
            ]);

            foreach ($quantities as $itemId => $qty) {
                ReturnRequestItem::query()->create([
                    'return_request_id' => $return->id,
                    'sales_order_item_id' => $itemId,
                    'quantity' => $qty,
                    'exchange_variant_id' => $isReturn ? null : (int) $submission->items[$itemId]['exchange_variant_id'],
                    'unit_refund' => $quote->units[$itemId],
                    'line_refund' => $quote->lines[$itemId],
                    'status' => ReturnRequestItem::STATUS_PENDING,
                ]);
            }

            foreach ($submission->photos as $photo) {
                ReturnRequestImage::query()->create([
                    'return_request_id' => $return->id,
                    'path' => $this->photos->moveTo($photo['path'], $returnNo),
                    'width' => $photo['width'] ?? null,
                    'height' => $photo['height'] ?? null,
                ]);
            }

            SalesOrderItem::query()
                ->whereKey(array_keys($quantities))
                ->where('status', SalesOrderItem::STATUS_ACTIVE)
                ->update(['status' => SalesOrderItem::STATUS_RETURN_REQUESTED, 'updated_at' => Carbon::now()]);

            if ($sellerOrder->status === SellerOrder::STATUS_DELIVERED) {
                $this->orderStatus->transition($sellerOrder, SellerOrder::STATUS_RETURN_REQUESTED, trans('returns::returns.history.requested', ['return_no' => $returnNo]), $customer, null, ['return_no' => $returnNo]);
            }

            ReturnRequested::dispatch($return);

            return $return;
        });

        $this->notifier->requested($return);

        try {
            if ($this->qualifiesForAutoApproval($return)) {
                $this->approve($return);
            } else {
                $this->notifier->held($return);
            }
        } catch (Throwable $e) {
            // The request stands; CX approves it from the console.
            report($e);
            $this->flag($return, trans('returns::returns.attention.approval_failed'));
        }

        return $return->refresh();
    }

    /**
     * The auto-approval rules (plan M15 §5.4): setting on, a reason that does not need review,
     * value within the cap and the customer's recent return rate within the limit.
     */
    public function qualifiesForAutoApproval(ReturnRequest $return): bool
    {
        if (! ReturnSettings::autoApprove() || in_array($return->reason_code, (array) config('returns.review_reasons', []), true)) {
            return false;
        }

        if ($this->value($return)->compare(ReturnSettings::autoApproveMaxValue()) > 0) {
            return false;
        }

        return $this->returnRate($return->customer_id, $return->id) <= ReturnSettings::autoApproveMaxReturnRate();
    }

    /**
     * Returns raised in the look-back window (other than $exceptReturnId, cancelled ones not
     * counted) as a percentage of the packages delivered in it.
     */
    public function returnRate(int $customerId, ?int $exceptReturnId = null): int
    {
        $since = Carbon::now()->subDays(max(1, (int) config('returns.return_rate_days', 90)));

        $returns = ReturnRequest::query()
            ->forCustomer($customerId)
            ->where('created_at', '>=', $since)
            ->where('status', '!=', ReturnRequest::STATUS_CANCELLED)
            ->when($exceptReturnId !== null, fn ($query) => $query->whereKeyNot($exceptReturnId))
            ->count();

        if ($returns === 0) {
            return 0;
        }

        $delivered = SellerOrder::query()
            ->whereIn('sales_order_id', SalesOrder::query()->select('id')->where('customer_id', $customerId))
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', $since)
            ->count();

        return $delivered === 0 ? 100 : (int) floor($returns * 100 / $delivered);
    }

    /**
     * Refunded at pickup instead of after QC (plan M15 §5.5): the setting is on, the customer
     * has enough delivered packages and a return rate within the limit.
     */
    public function isTrusted(int $customerId, ?int $exceptReturnId = null): bool
    {
        if (! ReturnSettings::refundOnPickupForTrusted()) {
            return false;
        }

        $delivered = SellerOrder::query()
            ->whereIn('sales_order_id', SalesOrder::query()->select('id')->where('customer_id', $customerId))
            ->whereNotNull('delivered_at')
            ->count();

        return $delivered >= ReturnSettings::trustedMinDeliveredOrders()
            && $this->returnRate($customerId, $exceptReturnId) <= ReturnSettings::autoApproveMaxReturnRate();
    }

    /**
     * Approve (automatically when $actor is null) and book the reverse pickup.
     *
     * @throws ReturnException
     */
    public function approve(ReturnRequest $return, ?Model $actor = null, ?string $note = null): ReturnRequest
    {
        DB::transaction(function () use ($return, $actor, $note): void {
            $locked = $this->lock($return, [ReturnRequest::STATUS_REQUESTED]);

            $locked->forceFill([
                'refund_on' => $this->isTrusted($locked->customer_id, $locked->id) ? ReturnRequest::REFUND_ON_PICKUP : ReturnRequest::REFUND_ON_QC,
                'auto_approved' => (int) config($actor === null ? 'core.yes' : 'core.no'),
                'decided_by' => $actor === null ? 'system' : 'admin',
                'decided_by_id' => $actor?->getKey(),
                'decided_at' => Carbon::now(),
                'decision_note' => $this->note($note),
            ]);
            $locked->transitionTo(ReturnRequest::STATUS_APPROVED, $this->note($note) ?? ($actor === null ? trans('returns::returns.history.auto_approved') : null), [], $actor);
        });

        $this->schedulePickup($return->refresh(), $actor);
        $this->notifier->approved($return->refresh());

        return $return;
    }

    /**
     * Book the reverse shipment (customer → seller warehouse) through Logistics. A courier
     * failure keeps the return approved and flags it for CX, who retry from the console.
     */
    public function schedulePickup(ReturnRequest $return, ?Model $actor = null): ReturnRequest
    {
        $return->refresh();

        if ($return->status !== ReturnRequest::STATUS_APPROVED) {
            throw new ReturnException(trans('returns::front.errors.invalid_state'), 'invalid_state');
        }

        $lock = Cache::lock('returns:pickup:'.$return->id, 120);

        if (! $lock->get()) {
            throw new ReturnException(trans('returns::front.errors.busy'), 'busy');
        }

        try {
            $shipment = $this->reverseShipment($return);
            $warning = null;

            try {
                $shipment = $this->shipments->bookReverse($shipment, $actor);
            } catch (ShipmentException|CourierException $e) {
                // The provider has the return order; the AWB can follow (Logistics Ops / retry).
                report($e);
                $warning = trans('returns::returns.attention.awb_pending', ['error' => $e->getMessage()]);
            }

            if ($warning === null && ! $shipment->isBooked()) {
                $warning = trans('returns::returns.attention.awb_pending', ['error' => (string) $shipment->attention_reason]);
            }

            DB::transaction(function () use ($return, $shipment, $warning, $actor): void {
                $locked = $this->lock($return, [ReturnRequest::STATUS_APPROVED]);
                $locked->forceFill([
                    'reverse_shipment_id' => $shipment->id,
                    'pickup_scheduled_at' => $shipment->pickup_scheduled_at ?? $this->nextWorkingDay(),
                    'needs_attention' => (int) config($warning === null ? 'core.no' : 'core.yes'),
                    'attention_reason' => $warning !== null ? mb_substr($warning, 0, 255) : null,
                ]);
                $locked->transitionTo(ReturnRequest::STATUS_PICKUP_SCHEDULED, null, array_filter(['awb' => $shipment->awb, 'shipment_id' => $shipment->id]), $actor);
            });
        } catch (ReturnException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            $this->flag($return, trans('returns::returns.attention.pickup_failed', ['error' => $e->getMessage()]));
        } finally {
            $lock->release();
        }

        return $return->refresh();
    }

    /**
     * @throws ReturnException
     */
    public function reject(ReturnRequest $return, string $reason, ?Model $actor = null): ReturnRequest
    {
        $reason = $this->note($reason) ?? trans('returns::returns.history.rejected');

        DB::transaction(function () use ($return, $reason, $actor): void {
            $locked = $this->lock($return, [ReturnRequest::STATUS_REQUESTED, ReturnRequest::STATUS_APPROVED]);
            $locked->forceFill([
                'decided_by' => $actor === null ? 'system' : 'admin',
                'decided_by_id' => $actor?->getKey(),
                'decided_at' => Carbon::now(),
                'decision_note' => $reason,
                'closed_at' => Carbon::now(),
            ]);
            $locked->transitionTo(ReturnRequest::STATUS_REJECTED, $reason, [], $actor);
            $this->settleItems($locked, false);
        });

        $this->cancelReverseShipment($return->refresh(), $reason);
        $this->notifier->rejected($return, $reason);

        return $return;
    }

    /**
     * The customer withdraws the request before the courier collects it.
     *
     * @throws ReturnException
     */
    public function cancel(ReturnRequest $return, ?Model $actor = null, ?string $reason = null): ReturnRequest
    {
        $return->refresh();
        $shipment = $return->reverse_shipment_id !== null ? Shipment::query()->find($return->reverse_shipment_id) : null;

        if (! $return->isCancellableByCustomer() || ($shipment !== null && ! $shipment->isPrePickup() && $shipment->status !== Shipment::STATUS_CANCELLED)) {
            throw new ReturnException(trans('returns::front.errors.not_cancellable'), 'not_cancellable');
        }

        $this->cancelReverseShipment($return, $reason ?? trans('returns::returns.history.cancelled'));

        DB::transaction(function () use ($return, $actor, $reason): void {
            $locked = $this->lock($return, ReturnRequest::CANCELLABLE_STATUSES);
            $locked->forceFill(['cancelled_at' => Carbon::now(), 'closed_at' => Carbon::now(), 'needs_attention' => (int) config('core.no')]);
            $locked->transitionTo(ReturnRequest::STATUS_CANCELLED, $this->note($reason), [], $actor);
            $this->settleItems($locked, false);
        });

        return $return->refresh();
    }

    /**
     * A reverse shipment moved (ShipmentStatusChanged): picked up → picked_up, delivered to the
     * seller → received, cancelled before pickup → approved again (CX reschedules), lost → CX.
     */
    public function onReverseShipmentStatus(Shipment $shipment, string $from, string $to): void
    {
        if ($shipment->direction !== Shipment::DIRECTION_REVERSE || $shipment->return_request_id === null) {
            return;
        }

        $return = ReturnRequest::query()->find($shipment->return_request_id);

        if ($return === null || $return->reverse_shipment_id !== $shipment->id) {
            return;
        }

        match ($to) {
            Shipment::STATUS_DELIVERED => $this->onReceived($return),
            Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_OUT_FOR_DELIVERY, Shipment::STATUS_NDR => $this->onPickedUp($return),
            Shipment::STATUS_CANCELLED => $this->onPickupCancelled($return),
            Shipment::STATUS_LOST, Shipment::STATUS_RTO_INITIATED => $this->flag($return, trans('returns::returns.attention.lost')),
            default => null,
        };
    }

    /**
     * The courier collected the package. Trusted customers are refunded (or their exchange
     * order created) now.
     */
    public function onPickedUp(ReturnRequest $return): void
    {
        $moved = DB::transaction(function () use ($return): bool {
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== ReturnRequest::STATUS_PICKUP_SCHEDULED) {
                return false;
            }

            $locked->forceFill(['picked_up_at' => Carbon::now()]);
            $locked->transitionTo(ReturnRequest::STATUS_PICKED_UP, null, [], null, 'courier');

            return true;
        });

        if (! $moved) {
            return;
        }

        $return->refresh();

        if ($return->refundsOnPickup()) {
            $return->isExchange() ? $this->exchangeOnPickup($return) : $this->startRefund($return);
        }

        $this->notifier->pickedUp($return->refresh());
    }

    /**
     * Delivered to the seller's warehouse: waiting for their QC.
     */
    public function onReceived(ReturnRequest $return): void
    {
        $return->refresh();

        if ($return->status === ReturnRequest::STATUS_PICKUP_SCHEDULED) {
            $this->onPickedUp($return);
        }

        DB::transaction(function () use ($return): void {
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== ReturnRequest::STATUS_PICKED_UP) {
                return;
            }

            $locked->forceFill(['received_at' => Carbon::now()]);
            $locked->transitionTo(ReturnRequest::STATUS_RECEIVED, null, [], null, 'courier');
        });

        $return->refresh();
    }

    public function onPickupCancelled(ReturnRequest $return): void
    {
        DB::transaction(function () use ($return): void {
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== ReturnRequest::STATUS_PICKUP_SCHEDULED) {
                return;
            }

            $locked->forceFill([
                'needs_attention' => (int) config('core.yes'),
                'attention_reason' => trans('returns::returns.attention.pickup_cancelled'),
            ]);
            $locked->transitionTo(ReturnRequest::STATUS_APPROVED, trans('returns::returns.attention.pickup_cancelled'), [], null, 'courier');
        });

        $return->refresh();
    }

    /**
     * The seller's QC passed (FR-RET-006/007): restock, then the refund or the exchange order.
     *
     * @throws ReturnException
     */
    public function qcPass(ReturnRequest $return, ?string $note = null, ?Model $actor = null): ReturnRequest
    {
        DB::transaction(function () use ($return, $note, $actor): void {
            $locked = $this->lockForQc($return, $actor);

            foreach (ReturnRequestItem::query()->where('return_request_id', $locked->id)->with('orderItem')->get() as $item) {
                if ($item->orderItem !== null) {
                    $this->inventory->restock($item->orderItem->variant_id, $item->quantity, $locked, $actor, 'Return '.$locked->return_no);
                }
            }

            $locked->forceFill(['qc_at' => Carbon::now(), 'qc_note' => $this->note($note)]);
            $locked->transitionTo(ReturnRequest::STATUS_QC_PASSED, $this->note($note), [], $actor);
            $this->settleItems($locked, true);
        });

        $this->complete($return->refresh(), $actor);

        return $return->refresh();
    }

    /**
     * The seller's QC failed: CX decides the refund (forceRefund / close); the seller may claim.
     *
     * @param  array{reason: string, description?: string|null, photos?: list<UploadedFile>}|null  $claim
     *
     * @throws ReturnException
     */
    public function qcFail(ReturnRequest $return, string $note, ?Model $actor = null, ?array $claim = null): ReturnRequest
    {
        DB::transaction(function () use ($return, $note, $actor, $claim): void {
            $locked = $this->lockForQc($return, $actor);

            ReturnRequestItem::query()->where('return_request_id', $locked->id)->update(['status' => ReturnRequestItem::STATUS_REJECTED, 'updated_at' => Carbon::now()]);
            $locked->forceFill([
                'qc_at' => Carbon::now(),
                'qc_note' => $this->note($note),
                'needs_attention' => (int) config('core.yes'),
                'attention_reason' => trans('returns::returns.attention.qc_failed'),
            ]);
            $locked->transitionTo(ReturnRequest::STATUS_QC_FAILED, $this->note($note), [], $actor);

            if ($claim !== null) {
                $this->claims->openForReturn($locked, $claim['reason'], $claim['description'] ?? $note, $claim['photos'] ?? []);
            }
        });

        $return->refresh();

        if ($claim === null) {
            $this->notifier->held($return);
        }

        return $return;
    }

    /**
     * CX refunds by hand (FR-RET-008): after a failed QC ("refund anyway" / a partial amount),
     * or when the automatic refund could not start (missing COD details, exchange out of stock).
     *
     * @param  array{mode: string, beneficiary: array<string, string>}|null  $override  COD refund details
     *
     * @throws ReturnException
     */
    public function forceRefund(ReturnRequest $return, ?Money $amount = null, ?Model $actor = null, ?array $override = null, ?string $note = null): ReturnRequest
    {
        $return->refresh()->loadMissing('items');
        $allowed = [ReturnRequest::STATUS_QC_PASSED, ReturnRequest::STATUS_QC_FAILED, ReturnRequest::STATUS_REFUND_INITIATED];

        if (! in_array($return->status, $allowed, true) || ($return->status === ReturnRequest::STATUS_REFUND_INITIATED && $return->refund_status !== ReturnRequest::REFUND_FAILED)) {
            throw new ReturnException(trans('returns::front.errors.invalid_state'), 'invalid_state');
        }

        if ($return->hasRefund()) {
            throw new ReturnException(trans('returns::return_request.messages.already_refunded'), 'already_refunded');
        }

        $cap = $return->isExchange()
            ? $return->items->reduce(fn (Money $sum, ReturnRequestItem $item): Money => $sum->plus($item->line_refund), Money::zero())
            : $return->refund_amount;

        if ($amount !== null && $amount->compare($cap) > 0) {
            throw ReturnException::field('amount', 'amount_too_high', ['max' => $cap->format(true)]);
        }

        $stage = ReturnRefundService::STAGE_FORCE.'-'.$return->refunds()->count();
        $this->refunds->initiate($return, $amount ?? $cap, $stage, $override);

        DB::transaction(function () use ($return, $actor, $note): void {
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);
            $wasQcFailed = $locked->status === ReturnRequest::STATUS_QC_FAILED;
            $locked->forceFill(['needs_attention' => (int) config('core.no'), 'attention_reason' => null, 'decision_note' => $this->note($note) ?? $locked->decision_note]);
            $this->moveToRefundState($locked, $actor, $this->note($note) ?? trans('returns::returns.history.force_refund'));

            if ($wasQcFailed) {
                ReturnRequestItem::query()->where('return_request_id', $locked->id)->update(['status' => ReturnRequestItem::STATUS_ACCEPTED, 'updated_at' => Carbon::now()]);
                $this->settleItems($locked, true);
            }
        });

        return $return->refresh();
    }

    /**
     * CX closes a return whose QC failed without a refund.
     *
     * @throws ReturnException
     */
    public function close(ReturnRequest $return, ?Model $actor = null, ?string $note = null): ReturnRequest
    {
        DB::transaction(function () use ($return, $actor, $note): void {
            $locked = $this->lock($return, [ReturnRequest::STATUS_QC_FAILED]);

            if ($locked->hasRefund()) {
                throw new ReturnException(trans('returns::return_request.messages.already_refunded'), 'already_refunded');
            }

            $locked->forceFill(['closed_at' => Carbon::now(), 'needs_attention' => (int) config('core.no'), 'attention_reason' => null, 'decision_note' => $this->note($note) ?? $locked->decision_note]);
            $locked->transitionTo(ReturnRequest::STATUS_CLOSED, $this->note($note), [], $actor);
            $this->settleItems($locked, false);
        });

        return $return->refresh();
    }

    /**
     * After QC passed: an exchange gets its order (or falls back to a refund when the new size
     * is gone); a return is refunded unless that already happened at pickup.
     */
    protected function complete(ReturnRequest $return, ?Model $actor): void
    {
        if ($return->isExchange()) {
            $exchange = $this->exchanges->createExchangeOrder($return, $actor);

            if ($exchange !== null) {
                DB::transaction(function () use ($return, $actor, $exchange): void {
                    $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

                    if ($locked->status === ReturnRequest::STATUS_QC_PASSED) {
                        $locked->forceFill(['closed_at' => Carbon::now()]);
                        $locked->transitionTo(ReturnRequest::STATUS_EXCHANGE_ORDER_CREATED, null, ['sub_order_no' => $exchange->sub_order_no], $actor);
                    }
                });

                return;
            }

            $this->flag($return, trans('returns::returns.attention.exchange_unavailable'));

            // Falls back to a refund: the items count as returned, not exchanged.
            SalesOrderItem::query()
                ->whereKey(ReturnRequestItem::query()->where('return_request_id', $return->id)->pluck('sales_order_item_id')->all())
                ->where('status', SalesOrderItem::STATUS_EXCHANGED)
                ->update(['status' => SalesOrderItem::STATUS_RETURNED, 'updated_at' => Carbon::now()]);
        }

        if (! $return->refresh()->hasRefund() && ! $this->startRefund($return)) {
            return;
        }

        DB::transaction(function () use ($return, $actor): void {
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

            if ($locked->status === ReturnRequest::STATUS_QC_PASSED) {
                $this->moveToRefundState($locked, $actor);
            }
        });
    }

    /**
     * qc_passed / qc_failed → refunded (money already back) or refund_initiated.
     */
    protected function moveToRefundState(ReturnRequest $locked, ?Model $actor, ?string $reason = null): void
    {
        if ($locked->status === ReturnRequest::STATUS_REFUND_INITIATED) {
            if ($locked->refund_status === ReturnRequest::REFUND_REFUNDED) {
                $locked->transitionTo(ReturnRequest::STATUS_REFUNDED, $reason, [], $actor);
            } else {
                $locked->save();
            }

            return;
        }

        $locked->transitionTo($locked->refund_status === ReturnRequest::REFUND_REFUNDED ? ReturnRequest::STATUS_REFUNDED : ReturnRequest::STATUS_REFUND_INITIATED, $reason, [], $actor);
    }

    /**
     * Start the automatic refund; a failure flags the return for CX.
     */
    protected function startRefund(ReturnRequest $return): bool
    {
        try {
            $this->refunds->initiate($return);

            return true;
        } catch (ReturnException $e) {
            $this->flag($return, trans('returns::returns.attention.refund_not_started', ['error' => $e->getMessage()]));

            return false;
        }
    }

    protected function exchangeOnPickup(ReturnRequest $return): void
    {
        try {
            $this->exchanges->createExchangeOrder($return);
        } catch (Throwable $e) {
            // The exchange is tried again after QC.
            report($e);
        }
    }

    /**
     * Order items after the return ends: accepted → returned / exchanged (returned_qty), else
     * back to active (or still return_requested under another open return). Then the seller
     * order leaves return_requested once no return is open.
     */
    protected function settleItems(ReturnRequest $locked, bool $accepted): void
    {
        $items = ReturnRequestItem::query()->where('return_request_id', $locked->id)->get();
        $orderItems = SalesOrderItem::query()->whereKey($items->pluck('sales_order_item_id')->all())->lockForUpdate()->get()->keyBy('id');
        $stillOpen = $this->eligibility->openQuantities($orderItems->keys()->all());

        foreach ($items as $item) {
            /** @var SalesOrderItem|null $orderItem */
            $orderItem = $orderItems->get($item->sales_order_item_id);

            if ($orderItem === null) {
                continue;
            }

            if ($accepted) {
                $item->forceFill(['status' => ReturnRequestItem::STATUS_ACCEPTED])->save();
                $orderItem->returned_qty = min($orderItem->quantity - $orderItem->cancelled_qty, $orderItem->returned_qty + $item->quantity);
            } elseif ($item->status === ReturnRequestItem::STATUS_PENDING) {
                $item->forceFill(['status' => ReturnRequestItem::STATUS_REJECTED])->save();
            }

            // openQuantities() still counts this return while it is open (e.g. qc_passed).
            $otherOpen = ($stillOpen[$orderItem->id] ?? 0) - ($locked->isOpen() ? $item->quantity : 0) > 0;
            $done = $orderItem->returned_qty + $orderItem->cancelled_qty >= $orderItem->quantity;

            $orderItem->status = match (true) {
                $done => $locked->isExchange() ? SalesOrderItem::STATUS_EXCHANGED : SalesOrderItem::STATUS_RETURNED,
                $otherOpen => SalesOrderItem::STATUS_RETURN_REQUESTED,
                default => SalesOrderItem::STATUS_ACTIVE,
            };
            $orderItem->save();
        }

        $this->syncSellerOrder($locked);
    }

    /**
     * return_requested → returned (nothing left with the customer) or delivered (items kept),
     * once no return of the package is open.
     */
    protected function syncSellerOrder(ReturnRequest $locked): void
    {
        $sellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($locked->seller_order_id);

        if ($sellerOrder->status !== SellerOrder::STATUS_RETURN_REQUESTED) {
            return;
        }

        $open = ReturnRequest::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->whereKeyNot($locked->id)
            ->open()
            ->exists();

        if ($open) {
            return;
        }

        $kept = SalesOrderItem::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->whereIn('status', [SalesOrderItem::STATUS_ACTIVE, SalesOrderItem::STATUS_RETURN_REQUESTED])
            ->exists();

        $this->orderStatus->transition(
            $sellerOrder,
            $kept ? SellerOrder::STATUS_DELIVERED : SellerOrder::STATUS_RETURNED,
            trans('returns::returns.history.'.($kept ? 'items_kept' : 'all_returned'), ['return_no' => $locked->return_no]),
            null,
            'system',
            ['return_no' => $locked->return_no],
        );
    }

    /**
     * The return's live reverse shipment, or a new one.
     *
     * @throws ShipmentException|CourierException
     */
    protected function reverseShipment(ReturnRequest $return): Shipment
    {
        $existing = $return->reverse_shipment_id !== null ? Shipment::query()->find($return->reverse_shipment_id) : null;

        if ($existing !== null && $existing->status !== Shipment::STATUS_CANCELLED) {
            return $existing;
        }

        $sellerOrder = SellerOrder::query()->findOrFail($return->seller_order_id);
        $quantities = ReturnRequestItem::query()->where('return_request_id', $return->id)->pluck('quantity', 'sales_order_item_id')->map(fn ($qty): int => (int) $qty)->all();

        return $this->shipments->createReverse($sellerOrder, $quantities, $return->id, $return->pickup_address);
    }

    protected function cancelReverseShipment(ReturnRequest $return, string $reason): void
    {
        $shipment = $return->reverse_shipment_id !== null ? Shipment::query()->find($return->reverse_shipment_id) : null;

        if ($shipment === null || $shipment->status === Shipment::STATUS_CANCELLED || ! $shipment->isPrePickup()) {
            return;
        }

        try {
            $this->shipments->cancel($shipment, $reason);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @throws ReturnException
     */
    protected function validateSubmission(Customer $customer, ReturnSubmission $submission): void
    {
        if (! in_array($submission->type, ReturnRequest::TYPES, true)) {
            throw ReturnException::field('type', 'type_invalid');
        }

        if (! in_array($submission->reasonCode, (array) config('returns.reasons', []), true)) {
            throw ReturnException::field('reason_code', 'reason_invalid');
        }

        if ($submission->items === []) {
            throw ReturnException::field('items', 'items_required');
        }

        $count = count($submission->photos);

        if ($count > ReturnSettings::maxImages()) {
            throw ReturnException::field('photos', 'too_many_photos', ['max' => (string) ReturnSettings::maxImages()]);
        }

        if ($count === 0 && ReturnRequest::photosRequiredFor($submission->reasonCode)) {
            throw ReturnException::field('photos', 'photos_required');
        }

        foreach ($submission->photos as $photo) {
            if (! $this->photos->isDraftOf((string) ($photo['path'] ?? ''), $customer->id)) {
                throw ReturnException::field('photos', 'bad_image');
            }
        }
    }

    /**
     * [mode, beneficiary] stored on the return: prepaid → source; COD → the bank / UPI details
     * (required for a return, kept for an exchange's refund fallback when given).
     *
     * @return array{0: string|null, 1: array<string, string>|null}
     *
     * @throws ReturnException
     */
    protected function refundDestination(SalesOrder $order, ReturnSubmission $submission): array
    {
        $isReturn = $submission->type === ReturnRequest::TYPE_RETURN;

        if ($order->payment_method !== Payment::METHOD_COD) {
            return [$isReturn ? 'source' : null, null];
        }

        $mode = $submission->refundMode;
        $details = (array) ($submission->beneficiary ?? []);

        $beneficiary = match ($mode) {
            'bank' => filled($details['account_number'] ?? null) && filled($details['ifsc'] ?? null) ? [
                'account_holder' => mb_substr(trim((string) ($details['account_holder'] ?? '')), 0, 100),
                'account_number' => preg_replace('/\s+/', '', (string) $details['account_number']),
                'ifsc' => strtoupper(trim((string) $details['ifsc'])),
            ] : null,
            'upi' => filled($details['vpa'] ?? null) ? ['vpa' => strtolower(trim((string) $details['vpa']))] : null,
            default => null,
        };

        if ($beneficiary === null) {
            if ($isReturn) {
                throw ReturnException::field('refund_mode', 'refund_details_missing');
            }

            return [null, null];
        }

        return [$mode, $beneficiary];
    }

    /**
     * The pickup address snapshot: a saved address of the customer in the same pincode as the
     * delivery (the courier's serviceability is per pincode), else the shipping address.
     *
     * @return array<string, mixed>
     */
    protected function pickupAddress(Customer $customer, SalesOrder $order, ?int $addressId): array
    {
        $shipping = $order->shippingAddress;
        $snapshot = $shipping === null ? [] : [
            'name' => $shipping->name,
            'phone' => $shipping->phone,
            'address_line1' => $shipping->address_line1,
            'address_line2' => $shipping->address_line2,
            'landmark' => $shipping->landmark,
            'pincode' => $shipping->pincode,
            'city_name' => $shipping->city_name,
            'state_name' => $shipping->state_name,
            'state_id' => $shipping->state_id,
        ];

        if ($addressId === null) {
            return $snapshot;
        }

        $address = CustomerAddress::query()->where('customer_id', $customer->id)->find($addressId);

        if ($address === null || $shipping === null || (string) $address->pincode !== (string) $shipping->pincode) {
            throw ReturnException::field('pickup_address_id', 'pickup_address_invalid');
        }

        return [
            'name' => $address->name ?: $shipping->name,
            'phone' => $address->phone ?: $shipping->phone,
            'address_line1' => $address->address_line1,
            'address_line2' => $address->address_line2,
            'landmark' => $address->landmark,
            'pincode' => $address->pincode,
            'city_name' => $address->city_name ?: $shipping->city_name,
            'state_name' => $address->state_name ?: $shipping->state_name,
            'state_id' => $address->state_id ?: $shipping->state_id,
        ];
    }

    /**
     * Lock the return and require one of $statuses.
     *
     * @param  list<string>  $statuses
     *
     * @throws ReturnException
     */
    protected function lock(ReturnRequest $return, array $statuses): ReturnRequest
    {
        $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

        if (! in_array($locked->status, $statuses, true)) {
            throw new ReturnException(trans('returns::front.errors.invalid_state'), 'invalid_state');
        }

        return $locked;
    }

    /**
     * Lock for the seller's QC: picked_up (the courier has not marked it delivered yet) moves
     * to received first.
     *
     * @throws ReturnException
     */
    protected function lockForQc(ReturnRequest $return, ?Model $actor): ReturnRequest
    {
        $locked = $this->lock($return, ReturnRequest::QC_STATUSES);

        if ($locked->status === ReturnRequest::STATUS_PICKED_UP) {
            $locked->forceFill(['received_at' => Carbon::now()]);
            $locked->transitionTo(ReturnRequest::STATUS_RECEIVED, null, [], $actor);
        }

        return $locked;
    }

    protected function flag(ReturnRequest $return, string $reason): void
    {
        ReturnRequest::query()->whereKey($return->id)->update([
            'needs_attention' => (int) config('core.yes'),
            'attention_reason' => mb_substr($reason, 0, 255),
            'updated_at' => Carbon::now(),
        ]);

        $return->refresh();
    }

    /**
     * What the return is worth for the auto-approval cap.
     */
    protected function value(ReturnRequest $return): Money
    {
        if (! $return->isExchange()) {
            return $return->refund_amount;
        }

        return ReturnRequestItem::query()->where('return_request_id', $return->id)->get()
            ->reduce(fn (Money $sum, ReturnRequestItem $item): Money => $sum->plus($item->line_refund), Money::zero());
    }

    protected function note(?string $note): ?string
    {
        return $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null;
    }

    protected function nextWorkingDay(): Carbon
    {
        $date = Carbon::tomorrow()->setTime(10, 0);

        return $date->isSunday() ? $date->addDay() : $date;
    }
}
