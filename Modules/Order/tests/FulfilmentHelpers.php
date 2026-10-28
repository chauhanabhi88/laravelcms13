<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\TrackingService;
use Modules\Order\Models\LabelBatch;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderFulfillmentService;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;

require_once __DIR__.'/../../Logistics/tests/Helpers.php';

/*
| Shared helpers for the M14 order desk tests (require_once'd: Pest test files with same-named
| global functions would collide). Orders go through the real checkout, logistics and the
| fake courier; documents land on a fake private disk; courier label / manifest downloads
| answer from Http::fake().
*/

if (! function_exists('fulfilmentSetup')) {
    function fulfilmentSetup(): Courier
    {
        $courier = logisticsSetup();
        Storage::fake((string) config('order.fulfilment.documents_disk', 'private'));
        Http::fake([
            'labels.example.test/*' => Http::response('%PDF-1.4 courier label', 200, ['Content-Type' => 'application/pdf']),
            'manifests.example.test/*' => Http::response('%PDF-1.4 courier manifest', 200, ['Content-Type' => 'application/pdf']),
        ]);

        return $courier;
    }

    function fulfilment(): OrderFulfillmentService
    {
        return app(OrderFulfillmentService::class);
    }

    /**
     * The seller's owner login (supplier panel user).
     */
    function fulfilmentOwner(Seller $seller): SellerUser
    {
        return SellerUser::factory()->for($seller)->create();
    }

    /**
     * A confirmed COD order of $seller in pending_acceptance: [order, seller order, shipment].
     *
     * @param  array<string, mixed>  $variant
     * @return array{0: SalesOrder, 1: SellerOrder, 2: Shipment}
     */
    function pendingFulfilmentOrder(?Seller $seller = null, array $variant = [], int $quantity = 1): array
    {
        return logisticsOrder($variant, $quantity, $seller);
    }

    /**
     * An accepted (booked, ready_to_ship) seller order.
     */
    function readyFulfilmentOrder(?Seller $seller = null, array $variant = []): SellerOrder
    {
        [, $sellerOrder] = pendingFulfilmentOrder($seller, $variant);
        fulfilment()->accept($sellerOrder);

        return $sellerOrder->fresh();
    }

    /**
     * A ready order with its label printed (label batch built synchronously).
     */
    function labelledFulfilmentOrder(?Seller $seller = null): SellerOrder
    {
        $sellerOrder = readyFulfilmentOrder($seller);
        fulfilment()->generateLabels($sellerOrder->seller()->firstOrFail(), [$sellerOrder]);

        return $sellerOrder->fresh();
    }

    function packedFulfilmentOrder(?Seller $seller = null): SellerOrder
    {
        $sellerOrder = labelledFulfilmentOrder($seller);
        fulfilment()->markPacked([$sellerOrder]);

        return $sellerOrder->fresh();
    }

    /**
     * Move the seller order's forward shipment through courier scans (mapped statuses).
     *
     * @param  list<string>  $statuses
     */
    function fulfilmentScans(SellerOrder $sellerOrder, array $statuses): void
    {
        $shipment = Shipment::query()->forward()->where('seller_order_id', $sellerOrder->id)->where('status', '!=', Shipment::STATUS_CANCELLED)->firstOrFail();
        $at = now()->subHours(count($statuses));

        app(TrackingService::class)->apply($shipment, array_map(function (string $status) use (&$at) {
            $at = $at->copy()->addMinutes(30);

            return scan($status, $at->toDateTimeString());
        }, $statuses));
    }

    /**
     * A seller order whose package came back to the seller (rto_delivered).
     */
    function rtoDeliveredOrder(?Seller $seller = null): SellerOrder
    {
        $sellerOrder = packedFulfilmentOrder($seller);
        fulfilmentScans($sellerOrder, ['picked_up', 'in_transit', 'rto_initiated', 'rto_in_transit', 'rto_delivered']);

        return $sellerOrder->fresh();
    }

    function latestLabelBatch(Seller|int $seller): LabelBatch
    {
        return LabelBatch::query()->where('seller_id', $seller instanceof Seller ? $seller->id : $seller)->orderByDesc('id')->firstOrFail();
    }
}
