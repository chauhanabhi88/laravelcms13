<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Http\Requests\Seller\InventoryUpdateRequest;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Repositories\SellerCatalogRepository;
use Modules\Product\Services\VariantQuickEditService;
use Modules\Product\Support\Listing\CategoryPicker;
use Modules\Product\Support\OptionLabels;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inventory screen (FR-SCT-006): every variant with available / reserved stock and its
 * threshold, filters (low stock, out of stock, category, SKU / name), inline edit of stock,
 * threshold and status, and a CSV export.
 */
class InventoryController extends SellerPanelController
{
    public function __construct(
        private readonly SellerCatalogRepository $catalog,
        private readonly VariantQuickEditService $quickEdit,
        private readonly OptionLabels $labels,
        private readonly CategoryPicker $categories,
    ) {}

    public function index(Request $request): View
    {
        return view('product::seller.inventory.index', $this->gridData($request) + [
            'sellerStoreName' => $this->seller()->displayName(),
            'categoryOptions' => $this->categories->options(),
            'lowStockCount' => $this->catalog->lowStockCount($this->seller()->id),
        ]);
    }

    public function filters(Request $request): JsonResponse
    {
        return response()->json(['html' => view('product::seller.inventory.partials.grid', $this->gridData($request))->render()]);
    }

    public function update(InventoryUpdateRequest $request): JsonResponse
    {
        try {
            $variant = $this->quickEdit->update($request->variant(), $request->changes(), $this->sellerUser());
        } catch (ProductValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => array_map(fn (string $message): array => [$message], $e->errors()),
            ], 422);
        }

        return response()->json([
            'message' => trans('product::seller.inventory.messages.saved'),
            'variant' => [
                'id' => $variant->id,
                'stock' => $variant->stock,
                'reserved' => $variant->reserved,
                'available' => $variant->available,
                'low_stock_threshold' => $variant->low_stock_threshold,
                'status' => $variant->status,
                'mrp' => $variant->mrp->toDecimal(),
                'price' => $variant->price->toDecimal(),
                'low' => $variant->isLowStock(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filtersFrom($request);
        $variants = $this->catalog->inventoryExport($this->seller()->id, $filters);

        return response()->streamDownload(function () use ($variants): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map(fn (string $column): string => trans('product::seller.inventory.columns.'.$column), ['product', 'variant', 'seller_sku', 'sku', 'mrp', 'price', 'stock', 'reserved', 'available', 'threshold', 'status']));

            foreach ($variants->chunk(500) as $chunk) {
                $labels = $this->labels->for($chunk->flatMap(fn (ProductVariant $variant) => $variant->attributeValues->pluck('attribute_option_id'))->all());

                foreach ($chunk as $variant) {
                    fputcsv($out, [
                        (string) $variant->product?->name,
                        OptionLabels::combination($this->optionsOf($variant), $labels),
                        $variant->seller_sku,
                        $variant->sku,
                        $variant->mrp->toDecimal(),
                        $variant->price->toDecimal(),
                        $variant->stock,
                        $variant->reserved,
                        $variant->available,
                        $variant->low_stock_threshold,
                        $variant->isEnabled() ? trans('product::seller.inventory.enabled') : trans('product::seller.inventory.disabled'),
                    ]);
                }
            }

            fclose($out);
        }, 'inventory-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request): array
    {
        $filters = $this->filtersFrom($request);
        /** @var LengthAwarePaginator<int, ProductVariant> $variants */
        $variants = $this->catalog->inventory($this->seller()->id, $filters, (int) config('product.seller_per_page', 20) * 2);
        $labels = $this->labels->for($variants->getCollection()->flatMap(fn (ProductVariant $variant) => $variant->attributeValues->pluck('attribute_option_id'))->all());

        return [
            'filters' => $filters,
            'variants' => $variants,
            'variantLabels' => $variants->getCollection()->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => OptionLabels::combination($this->optionsOf($variant), $labels)])->all(),
        ];
    }

    /**
     * @return array{q: string, stock: string|null, category: int|null}
     */
    private function filtersFrom(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'stock' => in_array($request->query('stock'), ['low', 'out'], true) ? (string) $request->query('stock') : null,
            'category' => $request->filled('category') ? (int) $request->query('category') : null,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function optionsOf(ProductVariant $variant): array
    {
        return $variant->attributeValues->mapWithKeys(fn ($value): array => [(int) $value->attribute_id => (int) $value->attribute_option_id])->all();
    }
}
