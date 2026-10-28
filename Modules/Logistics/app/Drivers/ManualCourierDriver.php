<?php

declare(strict_types=1);

namespace Modules\Logistics\Drivers;

use Illuminate\Support\Carbon;
use Modules\Core\Support\Money;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Logistics\Contracts\CourierDriver;
use Modules\Logistics\DTO\AwbResult;
use Modules\Logistics\DTO\CarrierQuote;
use Modules\Logistics\DTO\PickupResult;
use Modules\Logistics\DTO\ProviderShipment;
use Modules\Logistics\DTO\ServiceabilityRequest;
use Modules\Logistics\DTO\ServiceabilityResult;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\ManualPickupLocation;
use Modules\Seller\Models\SellerWarehouse;

/**
 * Logistics Ops run the courier by hand (plan M13 §1, the fallback when the API is down):
 * serviceability comes from the pincode master, no AWB is assigned automatically (Ops enter
 * it with admin.shipment.manual_awb) and scans are entered with admin.shipment.manual_status.
 */
class ManualCourierDriver implements CourierDriver
{
    public const CARRIER_ID = 'manual';

    public function serviceability(ServiceabilityRequest $request): ServiceabilityResult
    {
        $destination = DirectoryPincode::query()->where('pincode', $request->destinationPincode)->orderBy('id')->first();

        if ($destination === null || ! $destination->isServiceable()) {
            return ServiceabilityResult::none();
        }

        return new ServiceabilityResult([
            new CarrierQuote(self::CARRIER_ID, trans('logistics::courier.labels.manual_carrier'), Money::zero(), Money::zero(), null, $destination->allowsCod()),
        ], self::CARRIER_ID);
    }

    public function createShipment(Shipment $shipment): ProviderShipment
    {
        return new ProviderShipment('MAN-'.$shipment->id, 'MAN-'.$shipment->id);
    }

    public function assignAwb(Shipment $shipment, ?string $carrierId): AwbResult
    {
        // Logistics Ops enter the AWB by hand.
        return new AwbResult(null);
    }

    public function generateLabel(array $shipments): ?string
    {
        return null;
    }

    public function generateManifest(array $shipments): ?string
    {
        return null;
    }

    public function schedulePickup(array $shipments, Carbon $date): PickupResult
    {
        return new PickupResult(true, $date->copy());
    }

    public function track(Shipment $shipment): array
    {
        return [];
    }

    public function cancel(Shipment $shipment): bool
    {
        return true;
    }

    public function createReturn(Shipment $reverse): ProviderShipment
    {
        return new ProviderShipment('MAN-R'.$reverse->id, 'MAN-R'.$reverse->id);
    }

    public function syncPickupLocation(SellerWarehouse $warehouse): string
    {
        return ManualPickupLocation::code($warehouse);
    }

    public function ndrAction(Shipment $shipment, string $action, array $payload = []): bool
    {
        return true;
    }
}
