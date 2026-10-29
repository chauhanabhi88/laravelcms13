<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Carbon;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Finance\DTO\ResolvedRule;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Services\Concerns\ResolvesScopedRules;
use Modules\Finance\Support\FinanceSettings;
use Modules\Seller\Models\Seller;

/**
 * Commission engine (FR-FIN-001, plan M17 §5.1): the most specific active rule wins
 * (seller_category > seller > category along the path > global). Order items snapshot the
 * amount and rule id at placement, so later rule changes never alter past orders.
 */
class CommissionResolver
{
    use ResolvesScopedRules;

    public function for(Seller|int $seller, ?CatalogCategory $category, ?Carbon $on = null): ?ResolvedRule
    {
        $rule = $this->pickRule(
            CommissionRule::query()->inForce($this->on($on)),
            $seller instanceof Seller ? $seller->id : $seller,
            $category,
        );

        if (! $rule instanceof CommissionRule) {
            return null;
        }

        return new ResolvedRule($rule->id, $rule->scope, $rule->type, (string) $rule->value, $rule->applies_to);
    }

    /**
     * Commission on one unit sold at the GST-inclusive $unitPrice.
     */
    public function perUnit(Money $unitPrice, ?ResolvedRule $rule, string $gstPercent = '0'): Money
    {
        if ($rule === null || $rule->isZero() || ! $unitPrice->isPositive()) {
            return Money::zero();
        }

        if (! $rule->isPercent()) {
            return Money::of($rule->value);
        }

        $base = $rule->appliesTo === CommissionRule::APPLIES_TO_TAXABLE_VALUE
            ? self::taxableOf($unitPrice, $gstPercent !== '' ? $gstPercent : '0')
            : $unitPrice;

        return $base->percent($rule->value);
    }

    /**
     * Taxable part of a GST-inclusive amount: inclusive × 100 / (100 + GST %), rounded half up
     * to the paisa (the same split as Product's PricingService, which cannot be injected here:
     * it depends on the FeeCalculator this resolver backs).
     */
    public static function taxableOf(Money $inclusive, string $gstPercent): Money
    {
        $rate = max(0, Money::of($gstPercent)->paise);
        $numerator = $inclusive->paise * 10000;
        $denominator = 10000 + $rate;
        $quotient = intdiv($numerator, $denominator);

        return Money::paise($quotient + ((($numerator % $denominator) * 2 >= $denominator) ? 1 : 0));
    }

    /**
     * GST the platform charges the seller on a commission or fee (fee_gst_rate, 18 %).
     */
    public function gstOn(Money $fee): Money
    {
        return $fee->isPositive() ? $fee->percent(FinanceSettings::feeGstRate()) : Money::zero();
    }
}
