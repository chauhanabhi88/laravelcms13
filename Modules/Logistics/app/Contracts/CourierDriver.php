<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

use Illuminate\Support\Carbon;
use Modules\Logistics\DTO\AwbResult;
use Modules\Logistics\DTO\PickupResult;
use Modules\Logistics\DTO\ProviderShipment;
use Modules\Logistics\DTO\ServiceabilityRequest;
use Modules\Logistics\DTO\ServiceabilityResult;
use Modules\Logistics\DTO\TrackingEvent;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Models\Shipment;
use Modules\Seller\Models\SellerWarehouse;

/**
 * One courier integration (FR-LOG-001). Drivers: shiprocket (aggregator API), manual (Logistics
 * Ops enters AWBs and scans by hand) and fake (offline, tests). Provider failures throw
 * CourierException; drivers never write our tables, the services do.
 */
interface CourierDriver
{
    /**
     * @throws CourierException
     */
    public function serviceability(ServiceabilityRequest $request): ServiceabilityResult;

    /**
     * Register the forward shipment (order) with the provider.
     *
     * @throws CourierException
     */
    public function createShipment(Shipment $shipment): ProviderShipment;

    /**
     * Assign the tracking number, with $carrierId when the aggregator lets us choose.
     *
     * @throws CourierException
     */
    public function assignAwb(Shipment $shipment, ?string $carrierId): AwbResult;

    /**
     * @param  list<Shipment>  $shipments
     * @return string|null the label PDF URL (null when the driver has none)
     *
     * @throws CourierException
     */
    public function generateLabel(array $shipments): ?string;

    /**
     * @param  list<Shipment>  $shipments
     * @return string|null the manifest PDF URL (null when the driver has none)
     *
     * @throws CourierException
     */
    public function generateManifest(array $shipments): ?string;

    /**
     * @param  list<Shipment>  $shipments
     *
     * @throws CourierException
     */
    public function schedulePickup(array $shipments, Carbon $date): PickupResult;

    /**
     * The scans known to the provider, oldest first.
     *
     * @return list<TrackingEvent>
     *
     * @throws CourierException
     */
    public function track(Shipment $shipment): array;

    /**
     * @throws CourierException
     */
    public function cancel(Shipment $shipment): bool;

    /**
     * Book a return pickup (customer → seller warehouse) for a reverse shipment (M15).
     *
     * @throws CourierException
     */
    public function createReturn(Shipment $reverse): ProviderShipment;

    /**
     * Register a seller warehouse as a pickup location; returns the location code.
     *
     * @throws CourierException
     */
    public function syncPickupLocation(SellerWarehouse $warehouse): string;

    /**
     * Pass an NDR decision to the courier: re-attempt (optionally with new contact details)
     * or return to origin.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws CourierException
     */
    public function ndrAction(Shipment $shipment, string $action, array $payload = []): bool;
}
