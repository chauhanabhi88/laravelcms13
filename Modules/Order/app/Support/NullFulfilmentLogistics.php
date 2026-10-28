<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\DTO\BookingOutcome;
use Modules\Order\DTO\ManifestOutcome;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SellerOrder;

/**
 * No courier integration (the Logistics module is disabled): nothing can be booked or
 * shipped, every desk action that needs a courier is refused.
 */
class NullFulfilmentLogistics implements FulfilmentLogistics
{
    public function book(SellerOrder $sellerOrder, ?Model $actor = null): BookingOutcome
    {
        return BookingOutcome::refused(trans('order::fulfilment.errors.logistics_unavailable'));
    }

    public function shipments(array $sellerOrderIds): array
    {
        return [];
    }

    public function labels(array $sellerOrders, ?Model $actor = null): array
    {
        throw self::unavailable();
    }

    public function createManifest(array $sellerOrders, Carbon $pickupDate, ?Model $actor = null): ManifestOutcome
    {
        throw self::unavailable();
    }

    public function confirmRtoReceipt(SellerOrder $sellerOrder, bool $restock, ?Model $actor = null): void
    {
        throw self::unavailable();
    }

    public function timeline(SellerOrder $sellerOrder): array
    {
        return [];
    }

    private static function unavailable(): OrderException
    {
        return new OrderException(trans('order::fulfilment.errors.logistics_unavailable'), 'logistics_unavailable');
    }
}
