<?php

declare(strict_types=1);

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A DPDP data request (FR-CUS-008). Deletions wait out the grace period, then the
 * AnonymiseCustomer job processes them.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $type
 * @property string $status
 * @property Carbon $requested_at
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $processed_at
 * @property string|null $notes
 */
class CustomerDataRequest extends Model
{
    public const TYPE_DELETION = 'deletion';

    public const TYPE_EXPORT = 'export';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'customer_data_request';

    protected $fillable = ['customer_id', 'type', 'status', 'requested_at', 'scheduled_for', 'processed_at', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'requested_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    /**
     * Deletion requests whose grace period is over.
     *
     * @param  Builder<CustomerDataRequest>  $query
     */
    public function scopeDueDeletions(Builder $query): void
    {
        $query->where('type', self::TYPE_DELETION)
            ->where('status', self::STATUS_PENDING)
            ->where('scheduled_for', '<=', Carbon::now());
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
