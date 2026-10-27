<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Database\Factories\CatalogCategoryFactory;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Repositories\Transaltion\Translatable;

/**
 * A node of the marketplace category tree. Products attach only to leaves (is_leaf = 1).
 *
 * path / depth / is_leaf are maintained by CategoryTreeService; never write them directly.
 * Commerce defaults left null are inherited from the nearest ancestor (CategoryDefaults).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $path
 * @property int $depth
 * @property string $slug
 * @property string|null $image
 * @property string|null $icon
 * @property string|null $banner_image
 * @property int $is_leaf
 * @property string|null $gst_percent
 * @property string|null $hsn_code
 * @property int|null $return_window_days
 * @property string|null $return_type
 * @property int|null $dispatch_sla_hours
 * @property int|null $size_chart_id
 * @property string|null $min_selling_price
 * @property int $sort_order
 * @property int $show_in_menu
 * @property int $status
 * @property string|null $name
 * @property string|null $description
 */
class CatalogCategory extends MarketplaceModel
{
    /** @use HasFactory<CatalogCategoryFactory> */
    use HasFactory, SoftDeletes, Translatable;

    /**
     * Commerce defaults a category inherits from its ancestors when left blank.
     */
    public const INHERITABLE_FIELDS = [
        'gst_percent',
        'hsn_code',
        'return_window_days',
        'return_type',
        'dispatch_sla_hours',
        'size_chart_id',
        'min_selling_price',
    ];

    protected $table = 'catalog_category';

    protected $translationForeignKey = 'catalog_category_id';

    /**
     * @var list<string>
     */
    public $translatedAttributes = ['name', 'description', 'meta_title', 'meta_description', 'meta_keywords'];

    protected $fillable = [
        'parent_id',
        'slug',
        'image',
        'icon',
        'banner_image',
        'gst_percent',
        'hsn_code',
        'return_window_days',
        'return_type',
        'dispatch_sla_hours',
        'size_chart_id',
        'min_selling_price',
        'sort_order',
        'show_in_menu',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'depth' => 'integer',
            'is_leaf' => 'integer',
            'gst_percent' => 'decimal:2',
            'return_window_days' => 'integer',
            'dispatch_sla_hours' => 'integer',
            'size_chart_id' => 'integer',
            'min_selling_price' => 'decimal:2',
            'sort_order' => 'integer',
            'show_in_menu' => 'integer',
            'status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CatalogCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<CatalogSizeChart, $this>
     */
    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(CatalogSizeChart::class, 'size_chart_id');
    }

    /**
     * Attribute mappings defined on this category itself (not the inherited ones).
     *
     * @return HasMany<CatalogCategoryAttribute, $this>
     */
    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(CatalogCategoryAttribute::class, 'catalog_category_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<CatalogCategory>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), config('core.enabled'));
    }

    /**
     * Ids on the materialised path, root first, this category last.
     *
     * @return list<int>
     */
    public function pathIds(): array
    {
        return array_values(array_map('intval', array_filter(explode('/', (string) $this->path), 'strlen')));
    }

    /**
     * Ancestor ids, root first (excludes this category).
     *
     * @return list<int>
     */
    public function ancestorIds(): array
    {
        return array_values(array_filter($this->pathIds(), fn (int $id): bool => $id !== $this->id));
    }

    public function isLeaf(): bool
    {
        return $this->is_leaf === (int) config('core.yes');
    }

    public function isEnabled(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    protected static function newFactory(): CatalogCategoryFactory
    {
        return CatalogCategoryFactory::new();
    }
}
