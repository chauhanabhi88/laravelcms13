<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Order\Models\LabelBatch;
use Modules\Order\Services\OrderFulfillmentService;

/**
 * Builds one label batch (FR-FUL-004): courier labels, packing slips and tax invoices. Runs
 * once (a failed batch is marked failed; the seller generates a new one) with the PDF time
 * budget of plan M14 §13.
 */
class GenerateLabelBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $labelBatchId) {}

    public function handle(OrderFulfillmentService $fulfilment): void
    {
        $batch = LabelBatch::query()->find($this->labelBatchId);

        if ($batch !== null) {
            $fulfilment->buildLabelBatch($batch);
        }
    }
}
