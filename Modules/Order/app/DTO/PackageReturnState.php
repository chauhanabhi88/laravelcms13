<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

use Illuminate\Support\Carbon;

/**
 * After-sales state of one seller order on the customer's order pages (filled by Returns).
 */
final readonly class PackageReturnState
{
    /**
     * @param  list<array{return_no: string, type: string, status: string, label: string, chip: string, url: string}>  $returns
     */
    public function __construct(
        public int $sellerOrderId,
        public ?string $createUrl,
        public ?Carbon $windowEndsAt,
        public bool $exchangeOnly,
        public array $returns,
    ) {}

    public function canReturn(): bool
    {
        return $this->createUrl !== null;
    }
}
