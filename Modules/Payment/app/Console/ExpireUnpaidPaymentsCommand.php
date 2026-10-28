<?php

declare(strict_types=1);

namespace Modules\Payment\Console;

use Illuminate\Console\Command;
use Modules\Payment\Jobs\ExpireUnpaidPayments;
use Modules\Payment\Services\PaymentService;

/**
 * Runs the payment expiry sweep inline (every 5 minutes through the Cron module), so a
 * missing queue worker never leaves unpaid orders holding stock.
 */
class ExpireUnpaidPaymentsCommand extends Command
{
    protected $signature = 'payment:expire-unpaid';

    protected $description = 'Fail prepaid payments past their retry window and cancel COD payments with an unverified OTP';

    public function handle(PaymentService $payments): int
    {
        $result = (new ExpireUnpaidPayments)->handle($payments);

        $this->info('Expired payments: '.json_encode($result));

        return self::SUCCESS;
    }
}
