<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Logistics\DTO\ServiceabilityRequest;
use Modules\Logistics\DTO\ServiceabilityResult;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Support\CourierDriverManager;
use Modules\Logistics\Support\LogisticsSettings;

/**
 * Carrier quotes per route (FR-LOG-004), cached for serviceability_cache_hours under
 * "svc:{courier}:{from}:{to}:{weight bucket}:{cod}" as plain arrays (cache-safe). Failures are
 * not cached; they throw CourierException.
 */
class ServiceabilityService
{
    public function __construct(private readonly CourierDriverManager $drivers) {}

    /**
     * @throws CourierException
     */
    public function check(Courier $courier, ServiceabilityRequest $request, bool $useCache = true): ServiceabilityResult
    {
        if (! $useCache) {
            return $this->drivers->for($courier)->serviceability($request);
        }

        $key = $request->cacheKey($courier->code);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return ServiceabilityResult::fromArray($cached);
        }

        $result = $this->drivers->for($courier)->serviceability($request);
        Cache::put($key, $result->toArray(), now()->addHours(LogisticsSettings::serviceabilityCacheHours()));

        return $result;
    }
}
