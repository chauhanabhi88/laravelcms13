<?php

declare(strict_types=1);

namespace Modules\Logistics\Drivers;

use Illuminate\Support\Carbon;
use Modules\Core\Support\Money;
use Modules\Logistics\Contracts\CourierDriver;
use Modules\Logistics\DTO\AwbResult;
use Modules\Logistics\DTO\CarrierQuote;
use Modules\Logistics\DTO\PickupResult;
use Modules\Logistics\DTO\ProviderShipment;
use Modules\Logistics\DTO\ServiceabilityRequest;
use Modules\Logistics\DTO\ServiceabilityResult;
use Modules\Logistics\DTO\TrackingEvent;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Models\Shipment;
use Modules\Seller\Models\SellerWarehouse;

/**
 * Offline courier for tests and local work (a container singleton). By default every pincode
 * is serviceable by two carriers (FAST: ₹60, 2 days; CHEAP: ₹45, 5 days; FAST recommended);
 * tests replace the quote, queue scans for track(), or make the next call fail.
 */
class FakeCourierDriver implements CourierDriver
{
    public ?ServiceabilityResult $quote = null;

    /**
     * @var list<TrackingEvent>
     */
    public array $events = [];

    public ?string $failWith = null;

    /**
     * @var list<array{method: string, args: array<int, mixed>}>
     */
    public array $calls = [];

    public function defaultQuote(): ServiceabilityResult
    {
        return new ServiceabilityResult([
            new CarrierQuote('11', 'Fake Fast', Money::of('60'), Money::of('25'), 2, true),
            new CarrierQuote('22', 'Fake Cheap', Money::of('45'), Money::of('20'), 5, true),
        ], '11');
    }

    public function serviceability(ServiceabilityRequest $request): ServiceabilityResult
    {
        $this->record(__FUNCTION__, [$request]);

        return ($this->quote ?? $this->defaultQuote())->withCod($request->cod);
    }

    public function createShipment(Shipment $shipment): ProviderShipment
    {
        $this->record(__FUNCTION__, [$shipment->id]);

        return new ProviderShipment('FO-'.$shipment->id, 'FS-'.$shipment->id);
    }

    public function assignAwb(Shipment $shipment, ?string $carrierId): AwbResult
    {
        $this->record(__FUNCTION__, [$shipment->id, $carrierId]);
        $quote = $carrierId !== null ? ($this->quote ?? $this->defaultQuote())->find($carrierId) : null;

        return new AwbResult('FAKE'.str_pad((string) $shipment->id, 8, '0', STR_PAD_LEFT), $carrierId, $quote?->name ?? 'Fake Courier', null, 'https://track.example.test/'.$shipment->id);
    }

    public function generateLabel(array $shipments): ?string
    {
        $this->record(__FUNCTION__, [count($shipments)]);

        return 'https://labels.example.test/'.implode('-', array_map(fn (Shipment $shipment): int => $shipment->id, $shipments)).'.pdf';
    }

    public function generateManifest(array $shipments): ?string
    {
        $this->record(__FUNCTION__, [count($shipments)]);

        return 'https://manifests.example.test/'.implode('-', array_map(fn (Shipment $shipment): int => $shipment->id, $shipments)).'.pdf';
    }

    public function schedulePickup(array $shipments, Carbon $date): PickupResult
    {
        $this->record(__FUNCTION__, [count($shipments), $date->toDateString()]);

        return new PickupResult(true, $date->copy(), 'PICKUP-'.count($shipments));
    }

    public function track(Shipment $shipment): array
    {
        $this->record(__FUNCTION__, [$shipment->id]);

        return $this->events;
    }

    public function cancel(Shipment $shipment): bool
    {
        $this->record(__FUNCTION__, [$shipment->id]);

        return true;
    }

    public function createReturn(Shipment $reverse): ProviderShipment
    {
        $this->record(__FUNCTION__, [$reverse->id]);

        return new ProviderShipment('FR-'.$reverse->id, 'FRS-'.$reverse->id);
    }

    public function syncPickupLocation(SellerWarehouse $warehouse): string
    {
        $this->record(__FUNCTION__, [$warehouse->id]);

        return 'FAKE-WH-'.$warehouse->id;
    }

    public function ndrAction(Shipment $shipment, string $action, array $payload = []): bool
    {
        $this->record(__FUNCTION__, [$shipment->id, $action, $payload]);

        return true;
    }

    public function called(string $method): int
    {
        return count(array_filter($this->calls, fn (array $call): bool => $call['method'] === $method));
    }

    /**
     * @param  array<int, mixed>  $args
     *
     * @throws CourierException
     */
    protected function record(string $method, array $args): void
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        if ($this->failWith !== null) {
            $message = $this->failWith;
            $this->failWith = null;

            throw new CourierException($message);
        }
    }
}
