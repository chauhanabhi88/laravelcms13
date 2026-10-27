<?php

declare(strict_types=1);

namespace Modules\Product\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Services\CatalogImportService;
use Modules\Product\Services\CatalogUploadService;
use Throwable;

/**
 * Processes a new-catalog upload on the "imports" queue (CatalogImportService).
 */
class ProcessCatalogUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $uploadId)
    {
        $this->onQueue((string) config('product.import_queue', 'imports'));
    }

    public function handle(CatalogImportService $importer): void
    {
        $upload = CatalogUpload::query()->find($this->uploadId);

        if ($upload !== null) {
            $importer->process($upload);
        }
    }

    /**
     * A timeout or crash outside the importer's own error handling.
     */
    public function failed(?Throwable $exception): void
    {
        $upload = CatalogUpload::query()->find($this->uploadId);

        if ($upload !== null && ! $upload->isFinished()) {
            app(CatalogUploadService::class)->fail($upload, trans('product::seller.uploads.file_errors.processing'));
        }
    }
}
