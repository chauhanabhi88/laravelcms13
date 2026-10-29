<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Promotion\Database\Factories\FlashDealFactory;
use Modules\Storefront\Models\HomeSection;

/**
 * A time-boxed sale on chosen variants (FR-PRM-003, plan M18 §3.3). Saving or deleting a deal
 * drops the cached home page, so the countdown section and card prices follow at once.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $banner
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FlashDeal extends MarketplaceModel
{
    /** @use HasFactory<FlashDealFactory> */
    use HasFactory;

    public const STATE_SCHEDULED = 'scheduled';

    public const STATE_RUNNING = 'running';

    public const STATE_ENDED = 'ended';

    public const STATE_DISABLED = 'disabled';

    protected $table = 'flash_deal';

    protected $fillable = ['name', 'starts_at', 'ends_at', 'banner', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => 'integer',
        ];
    }

    /**
     * @return HasMany<FlashDealItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FlashDealItem::class, 'flash_deal_id');
    }

    /**
     * Enabled and running at $at (both ends of the window are required for a deal).
     *
     * @param  Builder<FlashDeal>  $query
     */
    public function scopeRunning(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();

        $query->where($this->qualifyColumn('status'), config('core.enabled'))
            ->where($this->qualifyColumn('starts_at'), '<=', $at)
            ->where($this->qualifyColumn('ends_at'), '>', $at);
    }

    public function isEnabled(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    public function state(?Carbon $at = null): string
    {
        $at ??= now();

        return match (true) {
            ! $this->isEnabled() => self::STATE_DISABLED,
            $this->starts_at === null || $this->starts_at->greaterThan($at) => self::STATE_SCHEDULED,
            $this->ends_at === null || $this->ends_at->lessThanOrEqualTo($at) => self::STATE_ENDED,
            default => self::STATE_RUNNING,
        };
    }

    protected static function booted(): void
    {
        static::saved(fn () => HomeSection::flushHomeCache());
        static::deleted(fn () => HomeSection::flushHomeCache());
    }

    protected static function newFactory(): FlashDealFactory
    {
        return FlashDealFactory::new();
    }
}
