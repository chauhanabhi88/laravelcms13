<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use Modules\Core\Support\Money;
use Modules\Product\Services\PricingService;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;

/**
 * GST on an order line (FR-ORD-007): prices are GST inclusive, so the line amount is split
 * into taxable value + tax, as CGST + SGST when the goods ship within one state and IGST
 * otherwise. The place of supply is the shipping state; the origin is the seller's pickup
 * warehouse state (else the seller's registered state).
 */
class TaxCalculator
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * @return array{taxable: Money, tax: Money, cgst: Money, sgst: Money, igst: Money}
     */
    public function forLine(Money $lineAmount, string|int $gstPercent, bool $interState): array
    {
        return $this->pricing->taxBreakup($lineAmount, $gstPercent, $interState);
    }

    /**
     * Inter-state unless both states are known and equal: by id when both have one, else by
     * name. An unknown origin or destination is treated as inter-state (IGST).
     */
    public function isInterState(?SellerWarehouse $warehouse, ?Seller $seller, ?int $shipStateId, ?string $shipStateName): bool
    {
        $originId = $warehouse?->state_id ?? $seller?->state_id;
        $originName = $warehouse?->state_name;

        if ($originId !== null && $shipStateId !== null) {
            return $originId !== $shipStateId;
        }

        $normalise = fn (?string $name): string => mb_strtolower(trim((string) $name));

        if ($normalise($originName) !== '' && $normalise($shipStateName) !== '') {
            return $normalise($originName) !== $normalise($shipStateName);
        }

        return true;
    }
}
