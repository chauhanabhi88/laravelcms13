<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Concerns\HasStateMachine;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Repositories\Transaltion\Translatable;
use Modules\Core\Support\Money;
use Modules\Product\Database\Factories\ProductFactory;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Events\ProductStatusChanged;
use Modules\Seller\Models\Seller;

/**
 * A seller's product: content, category and tax details. Buyers purchase its variants.
 *
 * Writes go through ProductFactoryService (content, variants, images), InventoryService
 * (stock) and ProductModerationService (status, via transitionTo()). The price, stock and
 * image columns are maintained by ProductAggregateService; never write them directly.
 *
 * @property int $id
 * @property int $seller_id
 * @property int $catalog_category_id
 * @property int|null $catalog_brand_id
 * @property string $slug
 * @property ProductStatus $status
 * @property int $has_pending_changes
 * @property array<string, mixed>|null $pending_changes
 * @property Carbon|null $submitted_at
 * @property string|null $qc_notes
 * @property int|null $qc_reviewed_by
 * @property Carbon|null $qc_reviewed_at
 * @property string|null $hsn_code
 * @property string $gst_percent
 * @property string $country_of_origin
 * @property array{name?: string, address?: string}|null $manufacturer_details
 * @property array{name?: string, address?: string}|null $packer_details
 * @property array{name?: string, address?: string}|null $importer_details
 * @property int|null $return_window_days
 * @property string|null $return_type
 * @property int $is_cod_allowed
 * @property string|null $search_keywords
 * @property string|null $primary_image
 * @property Money $min_price
 * @property Money $max_price
 * @property Money $min_mrp
 * @property int $max_discount_percent
 * @property int $total_stock
 * @property string $rating_avg
 * @property int $rating_count
 * @property int $sold_count
 * @property int $view_count
 * @property int $seller_active
 * @property Carbon|null $published_at
 * @property string|null $name
 * @property string|null $description
 * @property list<string>|null $highlights
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class Product extends MarketplaceModel
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasStateMachine, SoftDeletes, Translatable;

    /**
     * @var array<string, list<string>>
     */
    protected static array $transitions = [
        'draft' => ['pending_qc'],
        'pending_qc' => ['live', 'rejected', 'blocked'],
        'rejected' => ['pending_qc'],
        'live' => ['paused', 'blocked'],
        'paused' => ['live', 'blocked'],
        'blocked' => ['pending_qc'],
    ];

    /**
     * Every transition announces ProductStatusChanged (to-status => event class).
     *
     * @var array<string, class-string>
     */
    protected array $transitionEvents = [
        'pending_qc' => ProductStatusChanged::class,
        'live' => ProductStatusChanged::class,
        'rejected' => ProductStatusChanged::class,
        'paused' => ProductStatusChanged::class,
        'blocked' => ProductStatusChanged::class,
    ];

    protected $table = 'product';

    protected $translationForeignKey = 'product_id';

    /**
     * @var list<string>
     */
    public $translatedAttributes = ['name', 'description', 'highlights', 'meta_title', 'meta_description'];

    protected $fillable = [
        'seller_id',
        'catalog_category_id',
        'catalog_brand_id',
        'slug',
        'status',
        'has_pending_changes',
        'pending_changes',
        'submitted_at',
        'qc_notes',
        'qc_reviewed_by',
        'qc_reviewed_at',
        'hsn_code',
        'gst_percent',
        'country_of_origin',
        'manufacturer_details',
        'packer_details',
        'importer_details',
        'return_window_days',
        'return_type',
        'is_cod_allowed',
        'search_keywords',
        'seller_active',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'catalog_category_id' => 'integer',
            'catalog_brand_id' => 'integer',
            'status' => ProductStatus::class,
            'has_pending_changes' => 'integer',
            'pending_changes' => 'array',
            'submitted_at' => 'datetime',
            'qc_reviewed_by' => 'integer',
            'qc_reviewed_at' => 'datetime',
            'gst_percent' => 'decimal:2',
            'manufacturer_details' => 'array',
            'packer_details' => 'array',
            'importer_details' => 'array',
            'return_window_days' => 'integer',
            'is_cod_allowed' => 'integer',
            'min_price' => MoneyCast::class,
            'max_price' => MoneyCast::class,
            'min_mrp' => MoneyCast::class,
            'max_discount_percent' => 'integer',
            'total_stock' => 'integer',
            'rating_count' => 'integer',
            'sold_count' => 'integer',
            'view_count' => 'integer',
            'seller_active' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /**
     * @return BelongsTo<CatalogBrand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(CatalogBrand::class, 'catalog_brand_id');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('position')->orderBy('id');
    }

    /**
     * Specification attribute values (one row per selected option, or one text row).
     *
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'product_id')->orderBy('id');
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }

    /**
     * Buyable on the storefront: live and the seller not suspended. Out-of-stock products
     * stay visible (shown as "Out of stock").
     *
     * @param  Builder<Product>  $query
     */
    public function scopeVisibleOnStorefront(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), ProductStatus::Live->value)
            ->where($this->qualifyColumn('seller_active'), config('core.yes'));
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }

    /**
     * Awaiting a moderator: new submissions, and published products with content edits.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeInQcQueue(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where($this->qualifyColumn('status'), ProductStatus::PendingQc->value)
                ->orWhere(function (Builder $query): void {
                    $query->whereIn($this->qualifyColumn('status'), [ProductStatus::Live->value, ProductStatus::Paused->value])
                        ->where($this->qualifyColumn('has_pending_changes'), config('core.yes'));
                });
        });
    }

    public function isPublished(): bool
    {
        return $this->status->isPublished();
    }

    public function hasPendingChanges(): bool
    {
        return $this->has_pending_changes === (int) config('core.yes');
    }

    public function isInQcQueue(): bool
    {
        return $this->status === ProductStatus::PendingQc || ($this->isPublished() && $this->hasPendingChanges());
    }

    public function isVisibleOnStorefront(): bool
    {
        return $this->status === ProductStatus::Live && $this->seller_active === (int) config('core.yes');
    }

    public function isCodAllowed(): bool
    {
        return $this->is_cod_allowed === (int) config('core.yes');
    }

    /**
     * Hours spent in the QC queue so far (null when not queued).
     */
    public function qcAgeHours(): ?int
    {
        if (! $this->isInQcQueue() || $this->submitted_at === null) {
            return null;
        }

        return (int) $this->submitted_at->diffInHours(now(), true);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
