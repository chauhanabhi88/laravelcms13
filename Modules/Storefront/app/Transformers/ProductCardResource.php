<?php

declare(strict_types=1);

namespace Modules\Storefront\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\Money;

/**
 * A product card DTO (ProductCardPresenter) for the mobile API.
 *
 * @property array<string, mixed> $resource
 */
class ProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $card = $this->resource;
        /** @var Money $price */
        $price = $card['price'];
        /** @var Money $mrp */
        $mrp = $card['mrp'];
        $discount = $mrp->isPositive() && $mrp->compare($price) > 0 ? intdiv(($mrp->paise - $price->paise) * 100, $mrp->paise) : 0;

        return [
            'id' => $card['id'],
            'slug' => $card['slug'],
            'name' => $card['name'],
            'url' => $card['url'],
            'image' => $card['image'],
            'hover_image' => $card['image_alt2'],
            'price' => $price->toDecimal(),
            'mrp' => $mrp->toDecimal(),
            'discount_percent' => $discount,
            'rating' => $card['rating'],
            'rating_count' => $card['rating_count'],
            'free_delivery' => $card['free_delivery'],
            'cod' => $card['cod'],
            'badge' => $card['badge'],
            'in_stock' => $card['in_stock'],
        ];
    }
}
