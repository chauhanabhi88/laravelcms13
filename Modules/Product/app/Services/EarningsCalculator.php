<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Core\Support\Money;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Seller\Models\Seller;

/**
 * Price & earnings calculator (FR-SCT-008): what a seller nets for one unit sold at a
 * GST-inclusive price.
 *
 * Commission and platform fee come from the FeeCalculator (0 % until M17). Forward shipping,
 * TCS and TDS come from the FeeCalculator too once it reports them; until then shipping is
 * estimated from the rate card, TCS is product.calculator.tcs_percent of the taxable value
 * and TDS product.calculator.tds_percent of the gross price.
 *
 * net = price − commission − platform fee − shipping − TCS − TDS (GST is inside the price
 * and shown for information: the seller remits it from the price).
 *
 * @phpstan-type Estimate array{price: Money, gst_percent: string, taxable: Money, gst: Money, commission: Money, platform_fee: Money, shipping: Money, tcs: Money, tds: Money, total_deductions: Money, net: Money}
 */
class EarningsCalculator
{
    public function __construct(
        protected PricingService $pricing,
        protected FeeCalculator $fees,
        protected CategoryDefaults $defaults,
    ) {}

    /**
     * @param  string|null  $gstPercent  null = the category's GST rate
     * @return Estimate
     */
    public function estimate(Money $price, CatalogCategory $category, Seller $seller, int $weightGrams, ?string $gstPercent = null): array
    {
        $gstPercent = $gstPercent !== null && $gstPercent !== '' ? $gstPercent : (string) ($this->defaults->for($category)['gst_percent'] ?? '0');
        $tax = $this->pricing->taxBreakup($price, $gstPercent, false);
        $fees = $this->fees->fees($price, $category, $seller);
        $fee = fn (string $code): Money => $fees[$code] ?? Money::zero();

        $shipping = $fee('logistics')->isPositive() ? $fee('logistics') : $this->shippingEstimate($weightGrams);
        $tcs = $fee('tcs')->isPositive() ? $fee('tcs') : $tax['taxable']->percent((string) config('product.calculator.tcs_percent', '1'));
        $tds = $fee('tds')->isPositive() ? $fee('tds') : $price->percent((string) config('product.calculator.tds_percent', '0.1'));

        $deductions = $fee('commission')->plus($fee('platform_fee'))->plus($shipping)->plus($tcs)->plus($tds);

        return [
            'price' => $price,
            'gst_percent' => number_format((float) $gstPercent, 2, '.', ''),
            'taxable' => $tax['taxable'],
            'gst' => $tax['tax'],
            'commission' => $fee('commission'),
            'platform_fee' => $fee('platform_fee'),
            'shipping' => $shipping,
            'tcs' => $tcs,
            'tds' => $tds,
            'total_deductions' => $deductions,
            'net' => $price->minus($deductions),
        ];
    }

    /**
     * Forward shipping for a parcel from the rate card: admin > Settings > Logistics
     * "rate_card" (M13) when set, else config product.calculator.rate_card.
     */
    public function shippingEstimate(int $weightGrams): Money
    {
        $card = $this->rateCard();
        $slabs = $card['slabs'];

        if ($slabs === []) {
            return Money::zero();
        }

        $weightGrams = max(1, $weightGrams);

        foreach ($slabs as $slab) {
            if ($weightGrams <= $slab['up_to_grams']) {
                return Money::of($slab['charge']);
            }
        }

        $last = end($slabs);
        $step = max(1, $card['additional_grams']);
        $extraSteps = intdiv($weightGrams - $last['up_to_grams'] + $step - 1, $step);

        return Money::of($last['charge'])->plus(Money::of($card['additional_charge'])->times($extraSteps));
    }

    /**
     * Money amounts as "123.45" strings, for JSON.
     *
     * @param  Estimate  $estimate
     * @return array<string, string>
     */
    public static function toArray(array $estimate): array
    {
        return array_map(fn ($value): string => $value instanceof Money ? $value->toDecimal() : (string) $value, $estimate);
    }

    /**
     * @return array{slabs: list<array{up_to_grams: int, charge: string}>, additional_grams: int, additional_charge: string}
     */
    protected function rateCard(): array
    {
        $configured = function_exists('settings') ? rescue(fn () => settings('logistics', 'rate_card'), null, false) : null;
        $card = is_array($configured) && ! empty($configured['slabs']) ? $configured : (array) config('product.calculator.rate_card', []);

        $slabs = array_values(array_filter(array_map(fn ($slab): array => [
            'up_to_grams' => (int) ($slab['up_to_grams'] ?? 0),
            'charge' => (string) ($slab['charge'] ?? '0'),
        ], (array) ($card['slabs'] ?? [])), fn (array $slab): bool => $slab['up_to_grams'] > 0));

        usort($slabs, fn (array $a, array $b): int => $a['up_to_grams'] <=> $b['up_to_grams']);

        return [
            'slabs' => $slabs,
            'additional_grams' => (int) ($card['additional_grams'] ?? 1000),
            'additional_charge' => (string) ($card['additional_charge'] ?? '0'),
        ];
    }
}
