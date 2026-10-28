<?php

namespace Modules\Logistics\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Logistics\Repositories\NdrCaseRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;

class EloquentNdrCaseRepository extends EloquentBaseRepository implements NdrCaseRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'attempts', 'customer_action', 'status', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('logistics.cache.ndr_case');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->textFilter('search', trans('logistics::ndr_case.labels.search'), $filter('search'), '1'),
            $this->selectFilter('status', trans('logistics::ndr_case.labels.status'), $filter('status'), $options['statuses']),
            $this->selectFilter('customer_action', trans('logistics::ndr_case.labels.customer_action'), $filter('customer_action'), $options['actions']),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with('shipment.sellerOrder'), $request, config('logistics.cache.ndr_case'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('logistics.cache.ndr_case'));
        $collection = $this->model->newQuery();

        foreach (['status', 'customer_action'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->whereIn('shipment_id', fn ($sub) => $sub->select('shipment.id')->from('shipment')
                ->leftJoin('seller_order', 'seller_order.id', '=', 'shipment.seller_order_id')
                ->where('shipment.awb', $search)
                ->orWhere('seller_order.sub_order_no', 'like', $search.'%'));
        }

        return $collection;
    }
}
