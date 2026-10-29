<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\TaxDocumentService;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * The seller order was accepted and numbered (ready_to_ship, M12 §5.7): its tax invoice
 * (plan M17 §5.5).
 */
class GenerateTaxInvoiceOnReadyToShip
{
    public function __construct(private readonly TaxDocumentService $documents) {}

    public function handle(SellerOrderStatusChanged $event): void
    {
        if ($event->to !== SellerOrder::STATUS_READY_TO_SHIP) {
            return;
        }

        try {
            $this->documents->issueTaxInvoice(SellerOrder::query()->findOrFail($event->sellerOrder->id));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
