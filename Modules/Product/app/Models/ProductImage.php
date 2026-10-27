<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\MarketplaceModel;

/**
 * A product image file (stored flat in the product upload folder). attribute_option_id
 * marks a colour-specific image; the general image with the lowest position is primary.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $attribute_option_id
 * @property string $path
 * @property int $position
 * @property int|null $width
 * @property int|null $height
 * @property string|null $content_hash
 * @property string|null $alt
 */
class ProductImage extends MarketplaceModel
{
    protected $table = 'product_image';

    protected $fillable = ['product_id', 'attribute_option_id', 'path', 'position', 'width', 'height', 'content_hash', 'alt'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'attribute_option_id' => 'integer',
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function url(): ?string
    {
        return getImageUrl(['module' => (string) config('product.upload.folder'), 'image' => $this->path, 'defualt-image' => true]);
    }
}
