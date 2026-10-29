<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Order\Events\SellerPenaltyIncurred;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * Seller cancellation or SLA breach (M14): the penalty entry, 0 by default (plan M17 §5.2).
 */
class PostPenalty
{
    public function __construct(private readonly LedgerPosterImpl $ledger) {}

    public function handle(SellerPenaltyIncurred $event): void
    {
        try {
            $this->ledger->postPenalty(SellerOrder::query()->findOrFail($event->sellerOrder->id), $event->kind, $event->reason);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
