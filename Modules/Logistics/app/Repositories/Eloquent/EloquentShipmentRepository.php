<?php

namespace Modules\Logistics\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Repositories\ShipmentRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;

class EloquentShipmentRepository extends EloquentBaseRepository implements ShipmentRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'awb', 'carrier_name', 'status', 'edd', 'attempts', 'cod_amount', 'payment_mode', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('logistics.cache.shipment');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->textFilter('search', trans('logistics::shipment.labels.search'), $filter('search'), '1'),
            $this->selectFilter('status', trans('logistics::shipment.labels.status'), $filter('status'), $options['statuses']),
            $this->selectFilter('courier_id', trans('logistics::shipment.labels.courier'), $filter('courier_id'), $options['couriers']),
            $this->selectFilter('payment_mode', trans('logistics::shipment.labels.payment_mode'), $filter('payment_mode'), $options['payment_modes']),
            $this->textFilter('carrier', trans('logistics::shipment.labels.carrier'), $filter('carrier')),
            $this->selectFilter('flag', trans('logistics::shipment.labels.flag'), $filter('flag'), $options['flags'], '2'),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid(
            $this->filter($request)->with(['sellerOrder.seller', 'courier']),
            $request,
            config('logistics.cache.shipment'),
            self::SORTABLE,
        );
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('logistics.cache.shipment'));
        $collection = $this->model->newQuery()->select('shipment.*');

        foreach (['status', 'courier_id', 'payment_mode'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where('shipment.'.$column, $value);
            }
        }

        if (($carrier = trim((string) $filter('carrier'))) !== '') {
            $collection->where('shipment.carrier_name', 'like', '%'.$carrier.'%');
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('shipment.awb', $search)
                ->orWhereIn('shipment.seller_order_id', fn ($sub) => $sub->select('id')->from('seller_order')->where('sub_order_no', 'like', $search.'%')));
        }

        match ((string) $filter('flag')) {
            'needs_attention' => $collection->where('shipment.needs_attention', config('core.yes')),
            'stuck' => $collection
                ->whereIn('shipment.status', [Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT])
                ->where('shipment.picked_up_at', '<', Carbon::now()->subDays((int) config('logistics.stuck_after_days', 3))),
            'ndr' => $collection->where('shipment.status', Shipment::STATUS_NDR),
            'rto' => $collection->whereIn('shipment.status', Shipment::RTO_STATUSES),
            'reverse' => $collection->where('shipment.direction', Shipment::DIRECTION_REVERSE),
            default => null,
        };

        return $collection;
    }

    public function findForShow(int $id): ?Shipment
    {
        return $this->model->newQuery()
            ->with(['sellerOrder.seller', 'sellerOrder.salesOrder', 'sellerOrder.warehouse', 'courier', 'rule', 'events', 'ndrCases', 'items.orderItem'])
            ->find($id);
    }
}
