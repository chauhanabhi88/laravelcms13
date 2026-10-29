<?php

declare(strict_types=1);

namespace Modules\Product\DTO;

use Carbon\CarbonInterface;
use Modules\Core\Support\Money;

/**
 * A selling price that replaces a variant's own price for a while (PriceResolver).
 * $remaining is the number of units left at this price (null = no cap).
 */
final readonly class PriceOverride
{
    public function __construct(
        public Money $price,
        public ?CarbonInterface $endsAt = null,
        public string $source = 'flash_deal',
        public ?int $sourceId = null,
        public ?int $remaining = null,
    ) {}
}
