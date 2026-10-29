<?php

declare(strict_types=1);

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Modules\Finance\Services\TaxDocumentService;

/**
 * On the 1st of each month (plan M17 §5.5): the platform's fee invoices for the previous
 * month (none when no commission or fee was charged).
 */
class GenerateMonthlyFeeInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public ?string $period = null) {}

    public function handle(TaxDocumentService $documents): int
    {
        return count($documents->issueFeeInvoices($this->period ?? Carbon::now()->subMonthNoOverflow()->format('Y-m')));
    }
}
