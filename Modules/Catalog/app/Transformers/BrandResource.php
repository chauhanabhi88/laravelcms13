<?php

declare(strict_types=1);

namespace Modules\Catalog\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CatalogBrand;

/**
 * @mixin CatalogBrand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo ? getImageUrl(['module' => config('catalog.upload.brand'), 'image' => $this->logo]) : null,
            'is_verified' => $this->isVerified(),
        ];
    }
}
