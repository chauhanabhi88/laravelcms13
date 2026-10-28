<?php

declare(strict_types=1);

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * An internal admin note on a sales order (never shown to the customer).
 *
 * @property int $id
 * @property int $sales_order_id
 * @property int|null $user_id
 * @property string|null $author_name
 * @property string $note
 * @property Carbon|null $created_at
 */
class SalesOrderNote extends MarketplaceModel
{
    public const UPDATED_AT = null;

    protected $table = 'sales_order_note';

    protected $fillable = ['sales_order_id', 'user_id', 'author_name', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
