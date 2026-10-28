<?php

declare(strict_types=1);

namespace Modules\Logistics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Logistics\Drivers\ShiprocketDriver;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Support\CourierDriverManager;
use Modules\Seller\Models\SellerWarehouse;

/**
 * Registers one warehouse as a pickup location with the active, configured API couriers and
 * stores the location name on seller_warehouse.courier_pickup_ref (written without model
 * events, so it does not trigger another sync). $renew registers a new location name (a
 * changed pincode cannot reuse an approved location). Shiprocket must approve the address
 * and phone before pickups (plan M13 §13).
 */
class SyncPickupLocation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public int $warehouseId, public bool $renew = false) {}

    public function handle(CourierDriverManager $drivers): void
    {
        $warehouse = SellerWarehouse::query()->with('seller')->find($this->warehouseId);

        if ($warehouse === null) {
            return;
        }

        if ($this->renew && $warehouse->courier_pickup_ref !== null) {
            $warehouse->courier_pickup_ref = ShiprocketDriver::pickupLocationName($warehouse).'-'.now()->format('ymdHi');
        }

        $couriers = Courier::query()->active()->where('driver', Courier::DRIVER_SHIPROCKET)->orderBy('priority')->get();

        foreach ($couriers as $courier) {
            if (! $drivers->isConfigured($courier)) {
                continue;
            }

            $code = $drivers->for($courier)->syncPickupLocation($warehouse);
            SellerWarehouse::query()->whereKey($warehouse->id)->update(['courier_pickup_ref' => $code, 'updated_at' => now()]);
            $warehouse->courier_pickup_ref = $code;
        }
    }
}
