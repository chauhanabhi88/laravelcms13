<?php

namespace Modules\Order\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Order\Repositories\Eloquent\Concerns\FiltersByDateRange;
use Modules\Order\Repositories\SellerOrderRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;

class EloquentSellerOrderRepository extends EloquentBaseRepository implements SellerOrderRepository
{
    use BuildsGridFilters, FiltersByDateRange;

    private const SORTABLE = ['id', 'sub_order_no', 'status', 'items_subtotal', 'tax_total', 'commission_total', 'sla_dispatch_by', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('order.cache.seller_order');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('status', trans('order::seller_order.labels.status'), $filter('status'), $options['statuses']),
            $this->selectFilter('sla', trans('order::seller_order.labels.sla'), $filter('sla'), $options['sla']),
            $this->dateRangeFilter('created_at', trans('order::seller_order.labels.created_at'), $filter),
            $this->textFilter('seller', trans('order::seller_order.labels.seller_search'), $filter('seller')),
            $this->textFilter('search', trans('order::seller_order.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with('seller'), $request, config('order.cache.seller_order'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('order.cache.seller_order'));
        $collection = $this->model->newQuery();

        if (($status = (string) $filter('status')) !== '') {
            $collection->where('status', $status);
        }

        if ((string) $filter('sla') === 'breached') {
            $collection->slaBreached();
        }

        $this->applyDateRange($collection, 'created_at', $filter('created_at_from'), $filter('created_at_to'));

        if (($seller = trim((string) $filter('seller'))) !== '') {
            $collection->whereHas('seller', fn (Builder $query) => $query
                ->where('code', $seller)
                ->orWhere('store_name', 'like', '%'.$seller.'%'));
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('sub_order_no', 'like', '%'.$search.'%')
                ->orWhere('invoice_no', $search));
        }

        return $collection;
    }
}
