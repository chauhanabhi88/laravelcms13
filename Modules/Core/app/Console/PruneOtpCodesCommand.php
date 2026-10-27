<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Models\OtpCode;

/**
 * Scheduled daily through the Cron module (see the register_prune_otp_codes_cron migration).
 */
class PruneOtpCodesCommand extends Command
{
    protected $signature = 'core:prune-otp-codes';

    protected $description = 'Delete one-time passwords older than config(core.otp.prune_after_hours) hours';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subHours((int) config('core.otp.prune_after_hours', 24));
        $deleted = 0;

        do {
            $batch = OtpCode::query()->where('created_at', '<', $cutoff)->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->components->info("Deleted {$deleted} OTP codes created before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
