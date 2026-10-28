<?php

declare(strict_types=1);

namespace Modules\Payment\Services;

use Illuminate\Support\Carbon;
use Modules\Core\Support\Money;
use Modules\Payment\Contracts\SettlementReport;
use Modules\Payment\Exceptions\GatewayException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentReconItem;
use Modules\Payment\Models\PaymentRefund;

/**
 * Daily settlement reconciliation (FR-PAY-008, plan M11 §5.5): each settled payment and
 * refund from the gateway's recon report is matched to our record by gateway id; missing
 * records, amount differences and unexpected states become payment_recon_item rows.
 * Re-running a day adds nothing new for mismatches already recorded.
 */
class SettlementReconciliationService
{
    public function __construct(private readonly SettlementReport $report) {}

    public function isConfigured(): bool
    {
        return $this->report->isConfigured();
    }

    /**
     * @return int newly recorded mismatches
     *
     * @throws GatewayException
     */
    public function reconcile(Carbon $date): int
    {
        $created = 0;

        foreach ($this->report->settledOn($date) as $item) {
            $issue = match ($item['type']) {
                PaymentReconItem::TYPE_PAYMENT => $this->checkPayment($item['entity_id'], $item['amount_paise']),
                PaymentReconItem::TYPE_REFUND => $this->checkRefund($item['entity_id'], $item['amount_paise']),
                default => null,
            };

            if ($issue === null || $item['entity_id'] === '') {
                continue;
            }

            [$code, $localAmount, $note] = $issue;

            $row = PaymentReconItem::query()->firstOrCreate(
                ['entity_type' => $item['type'], 'entity_id' => $item['entity_id'], 'issue' => $code],
                [
                    'settlement_date' => $date->toDateString(),
                    'settlement_id' => $item['settlement_id'],
                    'gateway_amount' => Money::paise($item['amount_paise']),
                    'local_amount' => $localAmount,
                    'note' => $note,
                ],
            );

            if ($row->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @return array{0: string, 1: Money|null, 2: string|null}|null
     */
    protected function checkPayment(string $gatewayPaymentId, int $amountPaise): ?array
    {
        $payment = Payment::query()->where('gateway_payment_id', $gatewayPaymentId)->first();

        if ($payment === null) {
            return [PaymentReconItem::ISSUE_MISSING_LOCAL, null, null];
        }

        if ($payment->amountInPaise() !== $amountPaise) {
            return [PaymentReconItem::ISSUE_AMOUNT_MISMATCH, $payment->amount, 'Order '.$payment->order_no];
        }

        if (! $payment->isCaptured()) {
            return [PaymentReconItem::ISSUE_STATUS_MISMATCH, $payment->amount, 'Our status: '.$payment->status];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: Money|null, 2: string|null}|null
     */
    protected function checkRefund(string $gatewayRefundId, int $amountPaise): ?array
    {
        $refund = PaymentRefund::query()->where('gateway_refund_id', $gatewayRefundId)->first();

        if ($refund === null) {
            return [PaymentReconItem::ISSUE_MISSING_LOCAL, null, null];
        }

        if ($refund->amount->paise !== abs($amountPaise)) {
            return [PaymentReconItem::ISSUE_AMOUNT_MISMATCH, $refund->amount, 'Refund #'.$refund->id];
        }

        if ($refund->status !== PaymentRefund::STATUS_PROCESSED) {
            return [PaymentReconItem::ISSUE_STATUS_MISMATCH, $refund->amount, 'Our status: '.$refund->status];
        }

        return null;
    }
}
