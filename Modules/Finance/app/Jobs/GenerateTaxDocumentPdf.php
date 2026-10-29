<?php

declare(strict_types=1);

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Finance\Models\TaxDocument;
use Modules\Finance\Services\TaxDocumentService;

/**
 * Render a tax invoice, credit note or fee invoice PDF onto the private disk (FR-FIN-006).
 */
class GenerateTaxDocumentPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $documentId) {}

    public function handle(TaxDocumentService $documents): void
    {
        $document = TaxDocument::query()->find($this->documentId);

        if ($document !== null && ! $document->isGenerated()) {
            $documents->render($document);
        }
    }
}
