<?php

declare(strict_types=1);

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Finance\Services\CodRemittanceService;
use Modules\Finance\Support\ShiprocketRemittanceSource;
use Throwable;

/**
 * Daily (plan M17 §5.7): pull the aggregator's COD remittances and reconcile them. Without
 * API credentials nothing is fetched; Finance uploads the remittance CSV instead.
 */
class ImportCodRemittances implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(ShiprocketRemittanceSource $source, CodRemittanceService $remittances): int
    {
        try {
            $rows = $source->fetch();
        } catch (Throwable $e) {
            Log::channel('integrations')->warning('cod remittance import failed', ['error' => $e->getMessage()]);

            return 0;
        }

        return count($remittances->import($rows));
    }
}
