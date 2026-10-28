<?php

declare(strict_types=1);

namespace Modules\Returns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Returns\Models\ReturnClaim;

/**
 * Admin accepted or rejected a seller's claim (after commit). M17 credits an accepted claim's
 * resolution_amount to the seller's ledger (claim_credit).
 */
class ReturnClaimDecided implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public ReturnClaim $claim) {}
}
