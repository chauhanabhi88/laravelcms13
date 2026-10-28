<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

/**
 * A courier's label PDF covering some of the seller orders of a label batch.
 */
final readonly class ProviderLabel
{
    /**
     * @param  list<int>  $sellerOrderIds
     */
    public function __construct(
        public string $courier,
        public ?string $url,
        public array $sellerOrderIds,
    ) {}
}
