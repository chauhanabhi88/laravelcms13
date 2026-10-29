<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Returns\Events\ReturnClaimDecided;
use Modules\Returns\Models\ReturnClaim;
use Throwable;

/**
 * An accepted seller claim (M15) is credited to the seller's ledger (plan M17 §5.2).
 */
class PostClaimCredit
{
    public function __construct(private readonly LedgerPosterImpl $ledger) {}

    public function handle(ReturnClaimDecided $event): void
    {
        try {
            $this->ledger->postClaimCredit(ReturnClaim::query()->findOrFail($event->claim->id));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
