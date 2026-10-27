<?php

declare(strict_types=1);

namespace Modules\Storefront\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Repositories\Transaltion\Translatable;
use Modules\Storefront\Database\Factories\ProductCollectionFactory;

/**
 * A curated product set: "rule" collections filter the search index by their rules,
 * "manual" ones list product ids in display order. Shown at /collection/{slug} and in home
 * page collection sections.
 *
 * @property int $id
 * @property string $slug
 * @property string $type
 * @property array{category_ids?: list<int>, max_price?: int|float|string|null, min_discount?: int|null, min_rating?: int|float|null, brand_ids?: list<int>, seller_ids?: list<int>}|null $rules
 * @property list<int>|null $product_ids
 * @property string $sort
 * @property string|null $banner_image
 * @property int $status
 * @property string|null $name
 * @property string|null $description
 */
class ProductCollection extends MarketplaceModel
{
    /** @use HasFactory<ProductCollectionFactory> */
    use HasFactory, Translatable;

    public const TYPE_RULE = 'rule';

    public const TYPE_MANUAL = 'manual';

    /**
     * Collection sort => listing sort.
     */
    public const SORTS = [
        'popularity' => 'relevance',
        'new' => 'new',
        'price_asc' => 'price_asc',
        'discount' => 'discount',
    ];

    protected $table = 'product_collection';

    protected $translationForeignKey = 'product_collection_id';

    /**
     * @var list<string>
     */
    public $translatedAttributes = ['name', 'description'];

    protected $fillable = ['slug', 'type', 'rules', 'product_ids', 'sort', 'banner_image', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'product_ids' => 'array',
            'status' => 'integer',
        ];
    }

    /**
     * @param  Builder<ProductCollection>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), config('core.enabled'));
    }

    public function isEnabled(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    public function isManual(): bool
    {
        return $this->type === self::TYPE_MANUAL;
    }

    /**
     * @return list<int>
     */
    public function manualProductIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $this->product_ids))));
    }

    public function listingSort(): string
    {
        return self::SORTS[$this->sort] ?? 'relevance';
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_image ? getImageUrl(['module' => (string) config('storefront.upload.collection'), 'image' => $this->banner_image]) : null;
    }

    protected static function booted(): void
    {
        // Home collection sections render this collection.
        static::saved(fn () => HomeSection::flushHomeCache());
        static::deleted(fn () => HomeSection::flushHomeCache());
    }

    protected static function newFactory(): ProductCollectionFactory
    {
        return ProductCollectionFactory::new();
    }
}
