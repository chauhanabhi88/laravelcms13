<?php

declare(strict_types=1);

namespace Modules\Storefront\Models;

use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * A product a signed-in customer viewed. Composite key (customer_id, product_id): written
 * only through RecentlyViewedService (upsert + trim).
 *
 * @property int $customer_id
 * @property int $product_id
 * @property Carbon $viewed_at
 */
class CustomerRecentView extends MarketplaceModel
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'customer_recent_view';

    protected $primaryKey = 'customer_id';

    protected $fillable = ['customer_id', 'product_id', 'viewed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'product_id' => 'integer',
            'viewed_at' => 'datetime',
        ];
    }
}
