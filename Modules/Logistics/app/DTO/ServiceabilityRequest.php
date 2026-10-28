<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

/**
 * Can a package of $weightGrams go from $originPincode to $destinationPincode (with COD)?
 * $timeoutSeconds caps the provider call (the PDP estimator must answer fast).
 */
final readonly class ServiceabilityRequest
{
    public function __construct(
        public string $originPincode,
        public string $destinationPincode,
        public int $weightGrams,
        public bool $cod,
        public ?string $declaredValue = null,
        public ?float $timeoutSeconds = null,
    ) {}

    /**
     * The weight rounded up to the bucket (cache key and quote weight).
     */
    public function bucketedWeightGrams(): int
    {
        $bucket = max(1, (int) config('logistics.weight_bucket_grams', 500));

        return (int) (ceil(max(1, $this->weightGrams) / $bucket) * $bucket);
    }

    public function cacheKey(string $courierCode): string
    {
        return implode(':', ['logistics', 'svc', $courierCode, $this->originPincode, $this->destinationPincode, $this->bucketedWeightGrams(), $this->cod ? 1 : 0]);
    }
}
