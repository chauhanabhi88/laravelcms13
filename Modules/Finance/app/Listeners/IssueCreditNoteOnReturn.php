<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\TaxDocumentService;
use Modules\Returns\Events\ReturnRefunded;
use Modules\Returns\Models\ReturnRequest;
use Throwable;

/**
 * A refunded return gets a credit note referencing the original tax invoice (plan M17 §5.5).
 */
class IssueCreditNoteOnReturn
{
    public function __construct(private readonly TaxDocumentService $documents) {}

    public function handle(ReturnRefunded $event): void
    {
        try {
            $this->documents->issueCreditNote(ReturnRequest::query()->findOrFail($event->returnRequest->id));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
