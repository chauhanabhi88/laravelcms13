<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $channel
 * @property string $identifier
 * @property string $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property string|null $ip
 * @property Carbon $created_at
 */
class OtpCode extends MarketplaceModel
{
    public const UPDATED_AT = null;

    protected $table = 'otp_code';

    protected $fillable = [
        'channel',
        'identifier',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'ip',
    ];

    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Codes that can still be verified.
     *
     * @param  Builder<OtpCode>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', Carbon::now());
    }

    /**
     * @param  Builder<OtpCode>  $query
     */
    public function scopeFor(Builder $query, string $identifier, string $purpose): void
    {
        $query->where('identifier', $identifier)->where('purpose', $purpose);
    }
}
