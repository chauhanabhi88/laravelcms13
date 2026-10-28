<?php

declare(strict_types=1);

namespace Modules\Payment\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Payment\Services\PaymentService;

/**
 * FR-PAY-005, every 5 minutes through the Cron module: prepaid payments past their retry
 * window get one last gateway check (a late capture wins), the rest fail; COD payments whose
 * OTP was never verified are cancelled. Each failure fires PaymentFailed for M12.
 */
class ExpireUnpaidPayments implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 300;

    /**
     * @return array{captured: int, failed: int, cod_cancelled: int, skipped: int}
     */
    public function handle(PaymentService $payments): array
    {
        $result = $payments->expireStale();

        if (array_sum($result) > 0) {
            Log::info('payment:expire-unpaid', $result);
        }

        return $result;
    }
}
