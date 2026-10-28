<?php

declare(strict_types=1);

namespace Modules\Order\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Order\DTO\BookingOutcome;
use Modules\Order\DTO\ManifestOutcome;
use Modules\Order\DTO\ProviderLabel;
use Modules\Order\DTO\ShipmentSummary;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SellerOrder;

/**
 * What the seller order desk (M14, OrderFulfillmentService) needs from the courier side: book
 * the forward shipment (AWB), read shipment state, print the courier labels, hand packed
 * orders over in a pickup batch (manifest + pickup) and confirm an RTO's condition.
 *
 * Bound to NullFulfilmentLogistics (bindIf) until the Logistics module binds its own, the
 * same split as ShipmentPlanner: Logistics depends on Order, never the other way round.
 */
interface FulfilmentLogistics
{
    /**
     * Book the seller order's forward shipment with the courier the rules pick. On success the
     * seller order is ready_to_ship (Logistics moves it). Never throws for a courier failure:
     * the outcome says whether a retry makes sense.
     */
    public function book(SellerOrder $sellerOrder, ?Model $actor = null): BookingOutcome;

    /**
     * The current forward shipment of each seller order that has one.
     *
     * @param  list<int>  $sellerOrderIds
     * @return array<int, ShipmentSummary> keyed by seller order id
     */
    public function shipments(array $sellerOrderIds): array;

    /**
     * Courier label PDFs for booked seller orders, one call per courier.
     *
     * @param  list<SellerOrder>  $sellerOrders
     * @return list<ProviderLabel>
     *
     * @throws OrderException
     */
    public function labels(array $sellerOrders, ?Model $actor = null): array;

    /**
     * A pickup batch (manifest + scheduled pickup) for packed seller orders of one warehouse
     * and one courier.
     *
     * @param  list<SellerOrder>  $sellerOrders
     *
     * @throws OrderException
     */
    public function createManifest(array $sellerOrders, Carbon $pickupDate, ?Model $actor = null): ManifestOutcome;

    /**
     * The seller's confirmation of an RTO package: restock its items, or keep them out of
     * stock for a claim.
     *
     * @throws OrderException
     */
    public function confirmRtoReceipt(SellerOrder $sellerOrder, bool $restock, ?Model $actor = null): void;

    /**
     * The shipment timeline of the seller order (the shape of <x-theme::timeline>'s events).
     *
     * @return list<array<string, mixed>>
     */
    public function timeline(SellerOrder $sellerOrder): array;
}
