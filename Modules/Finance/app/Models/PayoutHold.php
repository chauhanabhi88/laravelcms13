<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

/**
 * A manual payout hold placed by Finance (FR-FIN-009). Automatic holds (bank re-verification,
 * suspension, open claims) are derived by PayoutHoldService, not stored here.
 *
 * @property int $id
 * @property int $seller_id
 * @property string $reason
 * @property int|null $placed_by
 * @property Carbon $placed_at
 * @property int|null $released_by
 * @property Carbon|null $released_at
 * @property string|null $release_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PayoutHold extends MarketplaceModel
{
    protected $table = 'seller_payout_hold';

    protected $fillable = [
        'seller_id',
        'reason',
        'placed_by',
        'placed_at',
        'released_by',
        'released_at',
        'release_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'placed_by' => 'integer',
            'placed_at' => 'datetime',
            'released_by' => 'integer',
            'released_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function placer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    /**
     * @param  Builder<PayoutHold>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('released_at'));
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }
}
