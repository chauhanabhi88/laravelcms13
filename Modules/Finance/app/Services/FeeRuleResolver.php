<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Carbon;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Finance\DTO\ResolvedRule;
use Modules\Finance\Models\FeeRule;
use Modules\Finance\Services\Concerns\ResolvesScopedRules;
use Modules\Seller\Models\Seller;

/**
 * Platform fee rules (plan M17 §3.2), resolved like commission rules. A fee reaches the seller
 * only when the winning rule is charged to the seller.
 */
class FeeRuleResolver
{
    use ResolvesScopedRules;

    public function for(string $code, Seller|int $seller, ?CatalogCategory $category = null, ?Carbon $on = null): ?ResolvedRule
    {
        $rule = $this->pickRule(
            FeeRule::query()->inForce($this->on($on))->where('code', $code),
            $seller instanceof Seller ? $seller->id : $seller,
            $category,
        );

        if (! $rule instanceof FeeRule) {
            return null;
        }

        return new ResolvedRule($rule->id, $rule->scope, $rule->type, (string) $rule->value, null, $rule->charged_to);
    }

    /**
     * What the seller pays for the fee: a flat amount, or a percentage of $base (the item price,
     * or the courier's actual charge for shipping). Zero when the platform absorbs it.
     */
    public function sellerCharge(string $code, Seller|int $seller, Money $base, ?CatalogCategory $category = null, ?Carbon $on = null): Money
    {
        $rule = $this->for($code, $seller, $category, $on);

        if ($rule === null || $rule->chargedTo !== FeeRule::CHARGED_TO_SELLER || $rule->isZero()) {
            return Money::zero();
        }

        return $rule->isPercent() ? $base->percent($rule->value) : Money::of($rule->value);
    }
}
