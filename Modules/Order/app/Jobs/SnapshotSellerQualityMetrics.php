<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Modules\Order\Services\SellerQualityService;

/**
 * Nightly (FR-FUL-008): the per-seller quality counts of a day (yesterday by default).
 */
class SnapshotSellerQualityMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public ?string $date = null) {}

    public function handle(SellerQualityService $quality): int
    {
        return $quality->snapshot($this->date !== null ? Carbon::parse($this->date) : Carbon::yesterday());
    }
}
