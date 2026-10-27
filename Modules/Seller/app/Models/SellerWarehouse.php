<?php

declare(strict_types=1);

namespace Modules\Seller\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\MarketplaceModel;
use Modules\Seller\Database\Factories\SellerWarehouseFactory;

/**
 * A pickup address. City and state come from the pincode master and are also kept as
 * name snapshots, so labels and manifests (M13/M14) read without joins.
 *
 * @property int $id
 * @property int $seller_id
 * @property string $name
 * @property string $contact_name
 * @property string $contact_phone
 * @property string $address_line1
 * @property string|null $address_line2
 * @property string|null $landmark
 * @property string $pincode
 * @property int|null $city_id
 * @property int|null $state_id
 * @property string|null $city_name
 * @property string|null $state_name
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int $is_default
 * @property string|null $courier_pickup_ref
 * @property int $status
 * @property-read Seller $seller
 */
class SellerWarehouse extends MarketplaceModel
{
    /** @use HasFactory<SellerWarehouseFactory> */
    use HasFactory;

    protected $table = 'seller_warehouse';

    protected $fillable = [
        'seller_id',
        'name',
        'contact_name',
        'contact_phone',
        'address_line1',
        'address_line2',
        'landmark',
        'pincode',
        'city_id',
        'state_id',
        'city_name',
        'state_name',
        'latitude',
        'longitude',
        'is_default',
        'courier_pickup_ref',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'city_id' => 'integer',
            'state_id' => 'integer',
            'is_default' => 'integer',
            'status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function isDefault(): bool
    {
        return $this->is_default === (int) config('core.yes');
    }

    /**
     * One-line address for lists and admin screens.
     */
    public function fullAddress(): string
    {
        return collect([$this->address_line1, $this->address_line2, $this->landmark, $this->city_name, $this->state_name, $this->pincode])
            ->filter(fn (?string $part): bool => $part !== null && trim($part) !== '')
            ->implode(', ');
    }

    protected static function newFactory(): SellerWarehouseFactory
    {
        return SellerWarehouseFactory::new();
    }
}
