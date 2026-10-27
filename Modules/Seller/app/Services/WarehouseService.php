<?php

declare(strict_types=1);

namespace Modules\Seller\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Directory\Repositories\DirectoryPincodeRepository;
use Modules\Seller\Exceptions\SellerException;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;

/**
 * Pickup addresses: city and state come from the pincode master, and one warehouse per
 * seller is the default.
 */
class WarehouseService
{
    public const FIELDS = ['name', 'contact_name', 'contact_phone', 'address_line1', 'address_line2', 'landmark', 'pincode'];

    public function __construct(private readonly DirectoryPincodeRepository $pincodes) {}

    /**
     * The pincode's first office, or null when the pincode is unknown.
     */
    public function lookup(string $pincode): ?DirectoryPincode
    {
        return preg_match('/^[1-9][0-9]{5}$/', $pincode) === 1 ? $this->pincodes->findByPincode($pincode) : null;
    }

    /**
     * A pickup pincode must exist in the master and be serviceable.
     */
    public function isPickupPincode(string $pincode): bool
    {
        return $this->lookup($pincode)?->isServiceable() ?? false;
    }

    /**
     * Create ($warehouse = null) or update a warehouse. The first warehouse, or one saved
     * with $makeDefault, becomes the default.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(Seller $seller, array $data, ?SellerWarehouse $warehouse = null, bool $makeDefault = false): SellerWarehouse
    {
        $pincode = $this->lookup((string) ($data['pincode'] ?? ''));

        if ($pincode === null || ! $pincode->isServiceable()) {
            throw new SellerException(trans('seller::validation.pincode.not_serviceable'));
        }

        return DB::transaction(function () use ($seller, $data, $warehouse, $makeDefault, $pincode): SellerWarehouse {
            $attributes = Arr::only($data, self::FIELDS) + [
                'city_id' => $pincode->city_id,
                'state_id' => $pincode->state_id,
                'city_name' => $pincode->city?->name ?: $pincode->district,
                'state_name' => $pincode->state?->name,
                'latitude' => $pincode->latitude,
                'longitude' => $pincode->longitude,
            ];

            $warehouse ??= new SellerWarehouse(['seller_id' => $seller->id, 'status' => config('core.enabled')]);
            $warehouse->fill($attributes);

            $isFirst = ! SellerWarehouse::query()->where('seller_id', $seller->id)->whereKeyNot($warehouse->id ?? 0)->exists();
            if ($makeDefault || $isFirst) {
                $warehouse->is_default = (int) config('core.yes');
            } elseif (! $warehouse->exists) {
                $warehouse->is_default = (int) config('core.no');
            }

            $warehouse->save();

            if ($warehouse->isDefault()) {
                $this->clearOtherDefaults($warehouse);
            }

            return $warehouse;
        });
    }

    public function makeDefault(SellerWarehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse): void {
            $warehouse->update(['is_default' => config('core.yes'), 'status' => config('core.enabled')]);
            $this->clearOtherDefaults($warehouse);
        });
    }

    /**
     * Delete a warehouse; the default one cannot be deleted while it is the only one left
     * or before another is made the default.
     */
    public function delete(SellerWarehouse $warehouse): void
    {
        if ($warehouse->isDefault()) {
            throw new SellerException(trans('seller::panel.warehouses.messages.cannot_delete_default'));
        }

        $warehouse->delete();
    }

    private function clearOtherDefaults(SellerWarehouse $warehouse): void
    {
        SellerWarehouse::query()
            ->where('seller_id', $warehouse->seller_id)
            ->whereKeyNot($warehouse->id)
            ->where('is_default', config('core.yes'))
            ->update(['is_default' => config('core.no')]);
    }
}
