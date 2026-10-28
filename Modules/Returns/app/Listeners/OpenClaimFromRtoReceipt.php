<?php

declare(strict_types=1);

namespace Modules\Returns\Listeners;

use Modules\Order\Events\ReturnReceiptConfirmed;
use Modules\Returns\Services\ReturnClaimService;
use Throwable;

/**
 * M14 hand-off: an RTO package the seller received damaged or wrong becomes a claim in the
 * admin claims queue, with the note and photos from the order desk.
 */
class OpenClaimFromRtoReceipt
{
    public function __construct(private readonly ReturnClaimService $claims) {}

    public function handle(ReturnReceiptConfirmed $event): void
    {
        if (! $event->raisesClaim() || $event->automatic) {
            return;
        }

        try {
            $this->claims->openForRto($event->sellerOrder, $event->condition, $event->note, $event->photos);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
