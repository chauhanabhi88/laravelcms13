<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Core\Support\Money;
use Modules\Finance\Models\FeeRule;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Seller\Models\Seller;

/**
 * The Product module's FeeCalculator backed by the commission engine (replaces the M04
 * ZeroFeeCalculator). Used by the seller's earnings calculator (M05) and by checkout to
 * snapshot commission per order item (M12).
 *
 * Per unit at the GST-inclusive price: commission (+ GST on it), platform fee (closing and
 * payment gateway fees charged to the seller) and forward shipping when a flat seller-paid
 * rule exists. TCS and TDS are withheld at settlement on the delivered amounts, so they stay
 * zero here and the calculator keeps estimating them from its configured rates.
 */
class FeeCalculatorImpl implements FeeCalculator
{
    public function __construct(
        private readonly CommissionResolver $commissions,
        private readonly FeeRuleResolver $feeRules,
        private readonly CategoryDefaults $categoryDefaults,
    ) {}

    public function fees(Money $price, CatalogCategory $category, Seller $seller): array
    {
        $gstPercent = (string) ($this->categoryDefaults->for($category)['gst_percent'] ?? '0');
        $commission = $this->commissions->perUnit($price, $this->commissions->for($seller, $category), $gstPercent);
        $platformFee = $this->feeRules->sellerCharge(FeeRule::CODE_FIXED_CLOSING_FEE, $seller, $price, $category)
            ->plus($this->feeRules->sellerCharge(FeeRule::CODE_PAYMENT_GATEWAY_FEE, $seller, $price, $category));
        $forward = $this->feeRules->for(FeeRule::CODE_FORWARD_SHIPPING, $seller, $category);

        return [
            'commission' => $commission,
            'platform_fee' => $platformFee->plus($this->commissions->gstOn($commission))->plus($this->commissions->gstOn($platformFee)),
            'logistics' => $forward !== null && $forward->chargedTo === FeeRule::CHARGED_TO_SELLER && ! $forward->isPercent() ? Money::of($forward->value) : Money::zero(),
            'tcs' => Money::zero(),
            'tds' => Money::zero(),
        ];
    }
}
