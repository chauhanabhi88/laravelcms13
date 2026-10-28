<?php

declare(strict_types=1);

namespace Modules\Payment\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Services\NotificationHub;
use Modules\Payment\Services\SettlementReconciliationService;
use Throwable;

/**
 * Daily (FR-PAY-008): reconcile one day's Razorpay settlements (default: yesterday) and
 * alert Finance (admin.payment.recon_mismatch) when new mismatches were found.
 */
class ReconcileRazorpaySettlements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [300, 900];

    public function __construct(public ?string $date = null) {}

    public function handle(SettlementReconciliationService $reconciliation, NotificationHub $hub): int
    {
        if (! $reconciliation->isConfigured()) {
            Log::info('payment:reconcile skipped, Razorpay keys are not configured');

            return 0;
        }

        $date = $this->date === null ? Carbon::yesterday() : Carbon::parse($this->date)->startOfDay();
        $found = $reconciliation->reconcile($date);

        if ($found > 0) {
            try {
                $hub->eventForAdmins('admin.payment.recon_mismatch', [
                    'date' => $date->toDateString(),
                    'count' => $found,
                    'url' => route('admin.payment_recon.index', updateUrlParams()),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $found;
    }
}
