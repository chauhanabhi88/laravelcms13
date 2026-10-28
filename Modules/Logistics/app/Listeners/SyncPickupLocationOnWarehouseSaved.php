<?php

declare(strict_types=1);

namespace Modules\Logistics\Listeners;

use Modules\Logistics\Jobs\SyncPickupLocation;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Support\CourierDriverManager;
use Modules\Seller\Events\SellerEvent;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;
use Throwable;

/**
 * Registers seller warehouses as pickup locations with the API couriers (plan M13 §5.3): when
 * an active seller's warehouse is created or its address changes, and for every warehouse
 * when a seller is approved. The work runs in the SyncPickupLocation job after commit; nothing
 * happens while no API courier is active and configured (local work, tests).
 */
class SyncPickupLocationOnWarehouseSaved
{
    public const ADDRESS_FIELDS = ['name', 'contact_name', 'contact_phone', 'address_line1', 'address_line2', 'landmark', 'pincode', 'status'];

    public function __construct(private readonly CourierDriverManager $drivers) {}

    public function saved(SellerWarehouse $warehouse): void
    {
        if (! $warehouse->wasRecentlyCreated && ! $warehouse->wasChanged(self::ADDRESS_FIELDS)) {
            return;
        }

        if ($warehouse->status !== (int) config('core.enabled')) {
            return;
        }

        $seller = Seller::query()->find($warehouse->seller_id);

        if ($seller === null || ! $seller->isActive()) {
            return;
        }

        $this->dispatch([$warehouse->id], $warehouse->wasRecentlyCreated || $warehouse->wasChanged('pincode'));
    }

    public function sellerApproved(SellerEvent $event): void
    {
        $ids = SellerWarehouse::query()
            ->where('seller_id', $event->seller->id)
            ->where('status', config('core.enabled'))
            ->whereNull('courier_pickup_ref')
            ->pluck('id')
            ->all();

        $this->dispatch($ids, false);
    }

    /**
     * @param  list<int>  $warehouseIds
     */
    protected function dispatch(array $warehouseIds, bool $renew): void
    {
        if ($warehouseIds === [] || ! $this->hasApiCourier()) {
            return;
        }

        foreach ($warehouseIds as $id) {
            try {
                SyncPickupLocation::dispatch((int) $id, $renew)->afterCommit();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    protected function hasApiCourier(): bool
    {
        return Courier::query()->active()->where('driver', Courier::DRIVER_SHIPROCKET)->get()
            ->contains(fn (Courier $courier): bool => $this->drivers->isConfigured($courier));
    }
}
