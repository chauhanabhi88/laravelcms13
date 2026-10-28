<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

/**
 * What a courier rule is matched against (plan M13 §5.2): the route, the package and the
 * order. $zone and $stateId describe the destination pincode.
 */
final readonly class AssignmentContext
{
    public function __construct(
        public string $originPincode,
        public string $destinationPincode,
        public ?string $zone,
        public ?int $stateId,
        public int $weightGrams,
        public string $paymentMode,
        public ?int $sellerId = null,
        public ?int $categoryId = null,
    ) {}

    public function isCod(): bool
    {
        return $this->paymentMode === 'cod';
    }
}
