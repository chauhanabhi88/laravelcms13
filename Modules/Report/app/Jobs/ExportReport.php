<?php

declare(strict_types=1);

namespace Modules\Report\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Report\Models\ReportExport;
use Modules\Report\Services\ReportExporter;

/**
 * Writes a large report export to the private disk and notifies the admin who asked for it.
 */
class ExportReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public readonly int $exportId) {}

    public function handle(ReportExporter $exporter): void
    {
        $export = ReportExport::query()->find($this->exportId);

        if ($export !== null) {
            $exporter->write($export);
        }
    }
}
