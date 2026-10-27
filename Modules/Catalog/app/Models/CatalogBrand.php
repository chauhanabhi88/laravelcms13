<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\CatalogBrandFactory;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Repositories\Transaltion\Translatable;

/**
 * A brand products may be listed under. Sellers request new brands (M05): such a row has
 * requested_by_seller_id set and status 2 until an admin approves it.
 *
 * @property int $id
 * @property string $slug
 * @property string|null $logo
 * @property int $is_verified
 * @property int|null $size_chart_id
 * @property int|null $requested_by_seller_id
 * @property int $status
 * @property string|null $name
 * @property string|null $description
 */
class CatalogBrand extends MarketplaceModel
{
    /** @use HasFactory<CatalogBrandFactory> */
    use HasFactory, Translatable;

    protected $table = 'catalog_brand';

    protected $translationForeignKey = 'catalog_brand_id';

    /**
     * @var list<string>
     */
    public $translatedAttributes = ['name', 'description'];

    protected $fillable = [
        'slug',
        'logo',
        'is_verified',
        'size_chart_id',
        'requested_by_seller_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'integer',
            'size_chart_id' => 'integer',
            'requested_by_seller_id' => 'integer',
            'status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogSizeChart, $this>
     */
    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(CatalogSizeChart::class, 'size_chart_id');
    }

    /**
     * @param  Builder<CatalogBrand>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), config('core.enabled'));
    }

    /**
     * @param  Builder<CatalogBrand>  $query
     */
    public function scopePendingRequests(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('requested_by_seller_id'))
            ->where($this->qualifyColumn('status'), config('core.disabled'));
    }

    public function isPendingRequest(): bool
    {
        return $this->requested_by_seller_id !== null && $this->status === (int) config('core.disabled');
    }

    public function isVerified(): bool
    {
        return $this->is_verified === (int) config('core.yes');
    }

    protected static function newFactory(): CatalogBrandFactory
    {
        return CatalogBrandFactory::new();
    }
}
