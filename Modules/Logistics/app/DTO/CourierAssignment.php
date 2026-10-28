<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\CourierRule;

/**
 * The courier (and, for an aggregator, the carrier) chosen for a shipment, and why.
 */
final readonly class CourierAssignment
{
    public function __construct(
        public Courier $courier,
        public ?CourierRule $rule,
        public string $strategy,
        public ?CarrierQuote $quote,
    ) {}
}
