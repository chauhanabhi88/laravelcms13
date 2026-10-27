<?php

declare(strict_types=1);

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One saved product. The table has a composite primary key (customer_id, product_id), which
 * Eloquent cannot address, so writes go through Modules\Customer\Services\WishlistService
 * with the query builder.
 *
 * @property int $customer_id
 * @property int $product_id
 * @property Carbon $created_at
 */
class CustomerWishlist extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $table = 'customer_wishlist';

    protected $primaryKey = 'product_id';

    protected $fillable = ['customer_id', 'product_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'product_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
