<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string|null $reason
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
class StatusHistory extends MarketplaceModel
{
    public const UPDATED_AT = null;

    protected $table = 'status_history';

    protected $fillable = [
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'reason',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
