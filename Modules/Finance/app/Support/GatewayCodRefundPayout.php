<?php

declare(strict_types=1);

namespace Modules\Finance\Support;

use Modules\Finance\DTO\PayoutRequest;
use Modules\Finance\DTO\PayoutResult;
use Modules\Finance\Exceptions\PayoutGatewayUnavailable;
use Modules\Payment\Contracts\CodRefundPayout;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\RefundService;

/**
 * COD refunds to the buyer's UPI id or bank account through the payout gateway (M11 hook,
 * FR-PAY-006). With the manual gateway nothing is sent: the refund stays pending for Finance
 * to mark paid with the bank UTR, exactly as before M17. The refund's own idempotency key is
 * the payout's, so a retry never pays twice.
 */
class GatewayCodRefundPayout implements CodRefundPayout
{
    public function __construct(private readonly PayoutGatewayManager $gateways) {}

    public function payout(PaymentRefund $refund): ?string
    {
        $gateway = $this->gateways->default();

        if (! $gateway->isAutomatic()) {
            return null;
        }

        $beneficiary = (array) $refund->beneficiary;
        $request = new PayoutRequest(
            reference: 'REFUND-'.$refund->id,
            amount: $refund->amount,
            idempotencyKey: (string) $refund->idempotency_key,
            beneficiary: isset($beneficiary['vpa'])
                ? ['type' => 'vpa', 'name' => (string) ($beneficiary['account_holder'] ?? 'Customer'), 'vpa' => (string) $beneficiary['vpa']]
                : ['type' => 'bank', 'name' => (string) ($beneficiary['account_holder'] ?? ''), 'account_number' => (string) ($beneficiary['account_number'] ?? ''), 'ifsc' => (string) ($beneficiary['ifsc'] ?? '')],
            purpose: PayoutRequest::PURPOSE_REFUND,
            narration: 'Refund '.$refund->id,
            contactReference: 'refund-'.$refund->id,
            notes: ['refund_id' => (string) $refund->id],
        );

        try {
            $result = $gateway->pay($request);
        } catch (PayoutGatewayUnavailable $e) {
            report($e);

            return null;
        }

        if ($result->status === PayoutResult::FAILED) {
            app(RefundService::class)->markFailed($refund, (string) $result->error);

            return null;
        }

        return $result->gatewayPayoutId;
    }
}
