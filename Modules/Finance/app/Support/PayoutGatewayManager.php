<?php

declare(strict_types=1);

namespace Modules\Finance\Support;

use InvalidArgumentException;
use Modules\Finance\Contracts\PayoutGateway;
use Modules\Finance\Drivers\FakePayoutGateway;
use Modules\Finance\Drivers\ManualPayoutGateway;
use Modules\Finance\Drivers\RazorpayXPayoutGateway;

/**
 * Resolves payout rails by name; the default is admin > Settings > Finance "payout_gateway".
 */
class PayoutGatewayManager
{
    public function default(): PayoutGateway
    {
        return $this->for(FinanceSettings::payoutGateway());
    }

    public function for(string $name): PayoutGateway
    {
        return match ($name) {
            'manual' => app(ManualPayoutGateway::class),
            'fake' => app(FakePayoutGateway::class),
            'razorpayx' => new RazorpayXPayoutGateway((array) config('seller.razorpayx', [])),
            default => throw new InvalidArgumentException("Unknown payout gateway [{$name}]."),
        };
    }
}
