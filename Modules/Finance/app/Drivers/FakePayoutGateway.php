<?php

declare(strict_types=1);

namespace Modules\Finance\Drivers;

use Modules\Finance\Contracts\PayoutGateway;
use Modules\Finance\DTO\PayoutRequest;
use Modules\Finance\DTO\PayoutResult;
use Modules\Finance\Exceptions\PayoutGatewayUnavailable;

/**
 * Offline payout rail for tests and local runs (a container singleton). Records every
 * request; answers "processing" with a stable payout id per idempotency key unless told to
 * fail, be unavailable or pay at once.
 */
class FakePayoutGateway implements PayoutGateway
{
    /**
     * @var list<PayoutRequest>
     */
    public array $requests = [];

    /**
     * processing | paid | failed | unavailable
     */
    public string $mode = PayoutResult::PROCESSING;

    public string $failureReason = 'Beneficiary account is closed';

    public function name(): string
    {
        return 'fake';
    }

    public function isAutomatic(): bool
    {
        return true;
    }

    public function pay(PayoutRequest $request): PayoutResult
    {
        $this->requests[] = $request;

        if ($this->mode === 'unavailable') {
            throw new PayoutGatewayUnavailable('Fake payout gateway is unavailable.');
        }

        $id = self::payoutIdFor($request->idempotencyKey);

        return match ($this->mode) {
            PayoutResult::PAID => PayoutResult::paid($id, 'UTRFAKE'.strtoupper(substr(md5($id), 0, 10))),
            PayoutResult::FAILED => PayoutResult::failed($this->failureReason, $id),
            default => PayoutResult::processing($id),
        };
    }

    public static function payoutIdFor(string $idempotencyKey): string
    {
        return 'pout_fake_'.substr(md5($idempotencyKey), 0, 14);
    }

    public function lastRequest(): ?PayoutRequest
    {
        return $this->requests === [] ? null : $this->requests[array_key_last($this->requests)];
    }
}
