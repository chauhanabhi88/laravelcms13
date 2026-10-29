<?php

declare(strict_types=1);

namespace Modules\Finance\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Finance\Contracts\PayoutGateway;
use Modules\Finance\DTO\PayoutRequest;
use Modules\Finance\DTO\PayoutResult;
use Modules\Finance\Exceptions\PayoutGatewayUnavailable;

/**
 * RazorpayX payouts (plan M17 §5.3) over Http::, reusing the Seller module's RazorpayX keys.
 *
 * POST /v1/payouts with the X-Payout-Idempotency header (the payout's idempotency key), so a
 * retried request never pays twice. A seller's fund account from the penny drop is reused;
 * other beneficiaries go through the composite payout (contact + fund account inline). Mode:
 * UPI for a VPA, IMPS up to finance.razorpayx.imps_limit rupees, NEFT above. The final state
 * arrives on the payout.processed / payout.failed / payout.reversed webhooks.
 */
class RazorpayXPayoutGateway implements PayoutGateway
{
    private const STATUS_MAP = [
        'processed' => PayoutResult::PAID,
        'reversed' => PayoutResult::FAILED,
        'rejected' => PayoutResult::FAILED,
        'failed' => PayoutResult::FAILED,
        'cancelled' => PayoutResult::FAILED,
    ];

    /**
     * @param  array<string, mixed>  $config  config('seller.razorpayx')
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'razorpayx';
    }

    public function isAutomatic(): bool
    {
        return true;
    }

    public function pay(PayoutRequest $request): PayoutResult
    {
        if (empty($this->config['key_id']) || empty($this->config['key_secret']) || empty($this->config['account_number'])) {
            throw new PayoutGatewayUnavailable('RazorpayX keys or account number are not configured.');
        }

        try {
            $response = $this->client()
                ->withHeaders(['X-Payout-Idempotency' => $request->idempotencyKey])
                ->post('/v1/payouts', $this->payload($request));
        } catch (ConnectionException $e) {
            throw new PayoutGatewayUnavailable($e->getMessage(), previous: $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new PayoutGatewayUnavailable('RazorpayX answered HTTP '.$response->status());
        }

        if ($response->clientError()) {
            return PayoutResult::failed((string) ($response->json('error.description') ?? $response->reason()));
        }

        return self::fromEntity((array) $response->json());
    }

    /**
     * Read a payout entity (API response or webhook payload).
     *
     * @param  array<string, mixed>  $payout
     */
    public static function fromEntity(array $payout): PayoutResult
    {
        $id = isset($payout['id']) ? (string) $payout['id'] : null;
        $status = self::STATUS_MAP[(string) ($payout['status'] ?? '')] ?? PayoutResult::PROCESSING;
        $utr = isset($payout['utr']) && $payout['utr'] !== '' ? (string) $payout['utr'] : null;
        $error = (string) ($payout['status_details']['description'] ?? $payout['failure_reason'] ?? $payout['error']['description'] ?? 'Payout '.($payout['status'] ?? 'failed'));

        return new PayoutResult($status, $id, $utr, $status === PayoutResult::FAILED ? $error : null, $payout);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(PayoutRequest $request): array
    {
        $beneficiary = $request->beneficiary;
        $payload = [
            'account_number' => (string) $this->config['account_number'],
            'amount' => $request->amount->paise,
            'currency' => 'INR',
            'mode' => $this->mode($request),
            'purpose' => $request->purpose,
            'queue_if_low_balance' => (bool) config('finance.razorpayx.queue_if_low_balance', true),
            'reference_id' => mb_substr($request->reference, 0, 40),
            'narration' => mb_substr((string) preg_replace('/[^A-Za-z0-9 ]/', ' ', $request->narration ?: $request->reference), 0, 30),
            'notes' => $request->notes,
        ];

        if (! empty($beneficiary['fund_account_id'])) {
            return $payload + ['fund_account_id' => (string) $beneficiary['fund_account_id']];
        }

        $contact = [
            'name' => (string) ($beneficiary['name'] ?? 'Beneficiary'),
            'type' => $request->purpose === PayoutRequest::PURPOSE_PAYOUT ? 'vendor' : 'customer',
            'reference_id' => mb_substr($request->contactReference ?: $request->reference, 0, 40),
        ];

        $payload['fund_account'] = $request->isVpa()
            ? ['account_type' => 'vpa', 'vpa' => ['address' => (string) $beneficiary['vpa']], 'contact' => $contact]
            : ['account_type' => 'bank_account', 'bank_account' => [
                'name' => (string) ($beneficiary['name'] ?? ''),
                'ifsc' => (string) ($beneficiary['ifsc'] ?? ''),
                'account_number' => (string) ($beneficiary['account_number'] ?? ''),
            ], 'contact' => $contact];

        return $payload;
    }

    protected function mode(PayoutRequest $request): string
    {
        if ($request->isVpa()) {
            return 'UPI';
        }

        $impsLimit = (int) config('finance.razorpayx.imps_limit', 500000);

        return $request->amount->paise <= $impsLimit * 100 ? 'IMPS' : 'NEFT';
    }

    protected function client(): PendingRequest
    {
        return Http::integration('razorpayx')
            ->baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://api.razorpay.com'), '/'))
            ->withBasicAuth((string) $this->config['key_id'], (string) $this->config['key_secret'])
            ->asJson();
    }
}
