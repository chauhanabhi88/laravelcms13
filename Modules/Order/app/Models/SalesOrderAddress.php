<?php

declare(strict_types=1);

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * A shipping or billing address snapshot of a sales order.
 *
 * @property int $id
 * @property int $sales_order_id
 * @property string $type
 * @property string $name
 * @property string|null $phone
 * @property string $address_line1
 * @property string|null $address_line2
 * @property string|null $landmark
 * @property string $pincode
 * @property int|null $city_id
 * @property string|null $city_name
 * @property int|null $state_id
 * @property string|null $state_name
 * @property int $is_end_customer
 * @property Carbon|null $created_at
 */
class SalesOrderAddress extends MarketplaceModel
{
    public const TYPE_SHIPPING = 'shipping';

    public const TYPE_BILLING = 'billing';

    public const UPDATED_AT = null;

    protected $table = 'sales_order_address';

    protected $fillable = [
        'sales_order_id',
        'type',
        'name',
        'phone',
        'address_line1',
        'address_line2',
        'landmark',
        'pincode',
        'city_id',
        'city_name',
        'state_id',
        'state_name',
        'is_end_customer',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'city_id' => 'integer',
            'state_id' => 'integer',
            'is_end_customer' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function isEndCustomer(): bool
    {
        return $this->is_end_customer === (int) config('core.yes');
    }

    /**
     * One-line address for lists, labels and notifications.
     */
    public function summary(): string
    {
        return collect([$this->address_line1, $this->address_line2, $this->landmark, $this->city_name, $this->state_name, $this->pincode])
            ->filter(fn (?string $part): bool => $part !== null && trim($part) !== '')
            ->implode(', ');
    }
}
