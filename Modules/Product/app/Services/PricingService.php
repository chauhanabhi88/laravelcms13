<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use InvalidArgumentException;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Core\Support\Money;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Product\DTO\VariantData;
use Modules\Product\Exceptions\InvalidPricing;
use Modules\Product\Support\SettlementEstimate;
use Modules\Seller\Models\Seller;

/**
 * Pricing rules (FR-PRD-007): selling price within MRP and the category's minimum, the
 * anti fake-MRP guard, discount %, the GST split of an inclusive price and the seller's
 * settlement estimate. All arithmetic is in integer paise (Money).
 */
class PricingService
{
    public function __construct(
        protected CategoryDefaults $defaults,
        protected FeeCalculator $fees,
    ) {}

    /**
     * @param  string  $prefix  error key prefix, e.g. "variants.2." gives "variants.2.price"
     *
     * @throws InvalidPricing
     */
    public function validate(VariantData $variant, CatalogCategory $category, string $prefix = ''): void
    {
        $this->validatePrices($variant->price, $variant->mrp, $category, $prefix);
    }

    /**
     * The same rules for a bare price / MRP pair (bulk price update, M05).
     *
     * @throws InvalidPricing
     */
    public function validatePrices(Money $price, Money $mrp, CatalogCategory $category, string $prefix = ''): void
    {
        if (! $mrp->isPositive()) {
            throw new InvalidPricing($prefix.'mrp', trans('product::product.pricing.mrp_positive'));
        }

        if (! $price->isPositive()) {
            throw new InvalidPricing($prefix.'price', trans('product::product.pricing.price_positive'));
        }

        if ($price->compare($mrp) > 0) {
            throw new InvalidPricing($prefix.'price', trans('product::product.pricing.price_above_mrp', ['mrp' => $mrp->format()]));
        }

        $minimum = $this->defaults->for($category)['min_selling_price'];
        if ($minimum !== null && Money::of($minimum)->isPositive() && $price->compare(Money::of($minimum)) < 0) {
            throw new InvalidPricing($prefix.'price', trans('product::product.pricing.price_below_minimum', ['minimum' => Money::of($minimum)->format()]));
        }

        $guard = $this->mrpMultiplierGuard();
        if ($guard > 0 && $mrp->compare($price->times($guard)) > 0) {
            throw new InvalidPricing($prefix.'mrp', trans('product::product.pricing.mrp_too_high', ['times' => $guard]));
        }
    }

    /**
     * Whole-percent discount of $price on $mrp, rounded down ("75% off").
     */
    public function discountPercent(Money $price, Money $mrp): int
    {
        if (! $mrp->isPositive() || $price->compare($mrp) >= 0) {
            return 0;
        }

        return intdiv(($mrp->paise - max(0, $price->paise)) * 100, $mrp->paise);
    }

    /**
     * Split a GST-inclusive amount: taxable = inclusive × 100 / (100 + GST %), rounded half
     * up to the paisa; tax = inclusive − taxable, as CGST + SGST (intra-state, the odd
     * paisa to CGST) or IGST (inter-state). The parts always add up to $inclusive.
     *
     * @return array{taxable: Money, tax: Money, cgst: Money, sgst: Money, igst: Money}
     */
    public function taxBreakup(Money $inclusive, string|int $gstPercent, bool $interState): array
    {
        // Money parses the decimal exactly, so "18.00" becomes 1800 hundredths of a percent.
        $rate = Money::of((string) $gstPercent)->paise;

        if ($rate < 0) {
            throw new InvalidArgumentException('The GST rate cannot be negative.');
        }

        $taxable = Money::paise($this->divideHalfUp($inclusive->paise * 10000, 10000 + $rate));
        $tax = $inclusive->minus($taxable);

        if ($interState) {
            return ['taxable' => $taxable, 'tax' => $tax, 'cgst' => Money::zero(), 'sgst' => Money::zero(), 'igst' => $tax];
        }

        ['cgst' => $cgst, 'sgst' => $sgst] = $tax->allocate(['cgst' => 1, 'sgst' => 1]);

        return ['taxable' => $taxable, 'tax' => $tax, 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => Money::zero()];
    }

    /**
     * What the seller nets for one unit at $price after the FeeCalculator's deductions.
     */
    public function estimateSellerSettlement(Money $price, CatalogCategory $category, Seller $seller): SettlementEstimate
    {
        $fees = $this->fees->fees($price, $category, $seller);
        $net = array_reduce($fees, fn (Money $net, Money $fee): Money => $net->minus($fee), $price);

        return new SettlementEstimate($price, $fees, $net);
    }

    public function mrpMultiplierGuard(): int
    {
        return max(0, (int) config('product.mrp_multiplier_guard', 10));
    }

    /**
     * Integer division rounded half away from zero.
     */
    protected function divideHalfUp(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($remainder !== 0 && 2 * abs($remainder) >= $denominator) {
            $quotient += $numerator < 0 ? -1 : 1;
        }

        return $quotient;
    }
}
