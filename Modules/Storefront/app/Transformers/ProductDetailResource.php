<?php

declare(strict_types=1);

namespace Modules\Storefront\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Storefront\ViewModels\ProductPageViewModel;

/**
 * The full product page payload for the mobile API (GET /api/v1/products/{id}). Stock is
 * never exposed: "available" is capped like on the web page.
 *
 * @property ProductPageViewModel $resource
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;
        $product = $page->product;
        $chart = $page->sizeChart;

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $page->name(),
            'url' => $page->canonicalUrl(),
            'price' => $page->displayPrice()->toDecimal(),
            'mrp' => $page->displayMrp()->toDecimal(),
            'discount_percent' => ProductPageViewModel::discountPercent($page->displayPrice(), $page->displayMrp()),
            'in_stock' => $page->isInStock(),
            'cod_allowed' => $product->isCodAllowed(),
            'highlights' => $page->highlights(),
            'description' => $page->description(),
            'specifications' => $page->specs,
            'manufacturer' => $page->manufacturerRows(),
            'returns' => [
                'days' => $page->returnPolicy->days,
                'type' => $page->returnPolicy->type,
                'summary' => $page->returnPolicy->summary(),
            ],
            'brand' => $page->brand === null ? null : [
                'id' => $page->brand['id'],
                'name' => $page->brand['name'],
                'verified' => $page->brand['verified'],
            ],
            'category' => $page->categoryTrail,
            'seller' => $page->sellerCard(),
            'axes' => $page->axes,
            'variants' => $page->variants,
            'images' => (object) $page->images,
            'default_variant_id' => $page->selectedVariant?->id,
            'size_chart' => $chart === null ? null : [
                'name' => $chart->name,
                'unit' => $chart->unit,
                'columns' => $chart->columns,
                'rows' => $chart->rows,
            ],
            'reviews' => $page->reviews->toArray(),
            'low_stock_threshold' => $page->payload()['lowStockThreshold'],
        ];
    }
}
