<?php

declare(strict_types=1);

namespace Modules\Review\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Review\Support\ReviewPhotoStore;

/**
 * A customer photo of a review (public disk, re-encoded on upload, with a thumbnail).
 *
 * @property int $id
 * @property int $product_review_id
 * @property string $path
 * @property string|null $thumb_path
 * @property int|null $width
 * @property int|null $height
 * @property int $position
 * @property Carbon|null $created_at
 */
class ProductReviewImage extends MarketplaceModel
{
    public const UPDATED_AT = null;

    protected $table = 'product_review_image';

    protected $fillable = ['product_review_id', 'path', 'thumb_path', 'width', 'height', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_review_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }

    public function url(): ?string
    {
        return ReviewPhotoStore::url($this->path);
    }

    public function thumbUrl(): ?string
    {
        return ReviewPhotoStore::url($this->thumb_path ?? $this->path);
    }
}
