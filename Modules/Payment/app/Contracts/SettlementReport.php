<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Illuminate\Support\Carbon;
use Modules\Payment\Exceptions\GatewayException;

/**
 * Settled transactions of one day from the gateway (Razorpay settlement recon).
 */
interface SettlementReport
{
    public function isConfigured(): bool;

    /**
     * @return list<array{type: string, entity_id: string, amount_paise: int, settlement_id: string|null}>
     *
     * @throws GatewayException
     */
    public function settledOn(Carbon $date): array;
}
