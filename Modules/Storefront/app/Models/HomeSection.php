<?php

declare(strict_types=1);

namespace Modules\Storefront\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Repositories\Transaltion\Translatable;
use Modules\Storefront\Database\Factories\HomeSectionFactory;
use Modules\Storefront\Support\StorefrontCache;

/**
 * A block of the configurable home page (FR-DSC-001). The shape of "config" depends on the
 * type: {banner_group_code} for hero / banner_row, {category_ids[]} for category tiles,
 * {collection_id, limit} for a collection, {block_code} for an HTML block, {limit} for blog posts.
 *
 * @property int $id
 * @property string $type
 * @property array<string, mixed>|null $config
 * @property int $sort_order
 * @property int $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $title
 * @property string|null $subtitle
 */
class HomeSection extends MarketplaceModel
{
    /** @use HasFactory<HomeSectionFactory> */
    use HasFactory, Translatable;

    protected $table = 'home_section';

    protected $translationForeignKey = 'home_section_id';

    /**
     * @var list<string>
     */
    public $translatedAttributes = ['title', 'subtitle'];

    protected $fillable = ['type', 'config', 'sort_order', 'status', 'starts_at', 'ends_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'sort_order' => 'integer',
            'status' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Enabled and inside the optional schedule window.
     *
     * @param  Builder<HomeSection>  $query
     */
    public function scopeActive(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();

        $query->where($this->qualifyColumn('status'), config('core.enabled'))
            ->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('starts_at'))->orWhere($this->qualifyColumn('starts_at'), '<=', $at))
            ->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('ends_at'))->orWhere($this->qualifyColumn('ends_at'), '>', $at));
    }

    public function isEnabled(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    /**
     * Drop the cached home page (section list and fragments).
     */
    public static function flushHomeCache(): void
    {
        app(StorefrontCache::class)->flush('home');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushHomeCache());
        static::deleted(fn () => self::flushHomeCache());
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }

    protected static function newFactory(): HomeSectionFactory
    {
        return HomeSectionFactory::new();
    }
}
