<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Product\Services\EarningsCalculator;
use Modules\Product\Support\Listing\CategoryPicker;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Price & earnings calculator (FR-SCT-008): page and JSON endpoint (also used live by the
 * wizard's pricing step).
 */
class CalculatorController extends SellerPanelController
{
    public function __construct(
        private readonly EarningsCalculator $calculator,
        private readonly CategoryPicker $categories,
    ) {}

    public function index(): View
    {
        $seller = $this->seller()->loadMissing('defaultWarehouse');

        return view('product::seller.calculator', [
            'sellerStoreName' => $seller->displayName(),
            'categoryOptions' => $this->categories->options(),
            'pincode' => $seller->defaultWarehouse?->pincode,
            'tcsPercent' => config('product.calculator.tcs_percent'),
            'tdsPercent' => config('product.calculator.tds_percent'),
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'catalog_category_id' => ['required', 'integer'],
            'price' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'weight_grams' => ['required', 'integer', 'min:1', 'max:100000'],
            'gst_percent' => ['nullable', Rule::in(array_map('strval', (array) config('product.gst_rates')))],
            'pincode' => ['nullable', 'digits:6'],
        ], [], [
            'catalog_category_id' => trans('product::seller.calculator.category'),
            'price' => trans('product::seller.calculator.price'),
            'weight_grams' => trans('product::seller.calculator.weight'),
        ]);

        if (! $this->categories->isListable((int) $validated['catalog_category_id'])) {
            return response()->json(['message' => trans('product::seller.wizard.category.invalid'), 'errors' => ['catalog_category_id' => [trans('product::seller.wizard.category.invalid')]]], 422);
        }

        $price = Money::of((string) $validated['price']);

        if (! $price->isPositive()) {
            return response()->json(['message' => trans('product::product.pricing.price_positive'), 'errors' => ['price' => [trans('product::product.pricing.price_positive')]]], 422);
        }

        $estimate = $this->calculator->estimate(
            $price,
            CatalogCategory::query()->findOrFail((int) $validated['catalog_category_id']),
            $this->seller(),
            (int) $validated['weight_grams'],
            $validated['gst_percent'] ?? null,
        );

        return response()->json([
            'data' => EarningsCalculator::toArray($estimate),
            'formatted' => array_map(fn ($value): string => $value instanceof Money ? $value->format(true) : (string) $value, $estimate),
        ]);
    }
}
