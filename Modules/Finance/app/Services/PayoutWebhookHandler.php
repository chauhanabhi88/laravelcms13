<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\Log;
use Modules\Finance\Drivers\RazorpayXPayoutGateway;
use Modules\Finance\DTO\PayoutResult;
use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Finance\Models\SellerPayout;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\RefundService;

/**
 * RazorpayX payout.* webhooks (plan M17 §5.3), received by the Seller module's signed
 * /webhooks/razorpayx endpoint: the payout id finds a seller payout, a reseller withdrawal or
 * a COD refund. Repeated deliveries are no-ops because each target only moves forward.
 */
class PayoutWebhookHandler
{
    public const EVENTS = ['payout.processed', 'payout.failed', 'payout.reversed', 'payout.rejected', 'payout.updated'];

    public function __construct(
        private readonly PayoutService $payouts,
        private readonly ResellerWalletService $wallet,
        private readonly RefundService $refunds,
    ) {}

    /**
     * @param  array<string, mixed>  $entity  the payout entity
     * @return string|null what was updated (seller_payout, reseller_withdrawal, payment_refund)
     */
    public function handle(string $event, array $entity): ?string
    {
        if (! in_array($event, self::EVENTS, true) || empty($entity['id'])) {
            return null;
        }

        $result = RazorpayXPayoutGateway::fromEntity($entity);
        $id = (string) $entity['id'];

        // payout.reversed on a paid payout: the money came back.
        if ($event === 'payout.reversed') {
            $result = new PayoutResult(PayoutResult::FAILED, $id, null, $result->error ?? 'Payout reversed', $entity);
        }

        if (($payout = SellerPayout::query()->where('gateway_payout_id', $id)->first()) !== null) {
            $this->payouts->applyResult($payout, $result);

            return 'seller_payout';
        }

        if (($withdrawal = ResellerWithdrawal::query()->where('gateway_payout_id', $id)->first()) !== null) {
            $this->wallet->applyResult($withdrawal, $result);

            return 'reseller_withdrawal';
        }

        if (($refund = PaymentRefund::query()->where('payout_id', $id)->first()) !== null) {
            match ($result->status) {
                PayoutResult::PAID => $this->refunds->markProcessed($refund, $result->utr),
                PayoutResult::FAILED => $this->refunds->markFailed($refund, (string) $result->error),
                default => null,
            };

            return 'payment_refund';
        }

        Log::channel('integrations')->info('razorpayx payout webhook for an unknown payout', ['payout_id' => $id, 'event' => $event]);

        return null;
    }
}
