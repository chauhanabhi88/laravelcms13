<?php

namespace Modules\Order\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Order\Repositories\Eloquent\Concerns\FiltersByDateRange;
use Modules\Order\Repositories\SalesOrderRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;

class EloquentSalesOrderRepository extends EloquentBaseRepository implements SalesOrderRepository
{
    use BuildsGridFilters, FiltersByDateRange;

    private const SORTABLE = ['id', 'order_no', 'status', 'payment_method', 'payment_status', 'items_count', 'grand_total', 'is_reseller_order', 'channel', 'placed_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('order.cache.sales_order');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('status', trans('order::sales_order.labels.status'), $filter('status'), $options['statuses']),
            $this->selectFilter('payment_method', trans('order::sales_order.labels.payment_method'), $filter('payment_method'), $options['methods']),
            $this->selectFilter('is_reseller_order', trans('order::sales_order.labels.reseller'), $filter('is_reseller_order'), $options['yes_no']),
            $this->dateRangeFilter('placed_at', trans('order::sales_order.labels.placed_at'), $filter),
            $this->textFilter('pincode', trans('order::sales_order.labels.pincode'), $filter('pincode')),
            $this->textFilter('seller', trans('order::sales_order.labels.seller_search'), $filter('seller')),
            $this->textFilter('search', trans('order::sales_order.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with(['customer', 'shippingAddress']), $request, config('order.cache.sales_order'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('order.cache.sales_order'));
        $collection = $this->model->newQuery();

        foreach (['status', 'payment_method', 'is_reseller_order'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        $this->applyDateRange($collection, 'placed_at', $filter('placed_at_from'), $filter('placed_at_to'));

        if (($pincode = trim((string) $filter('pincode'))) !== '') {
            $collection->whereHas('shippingAddress', fn (Builder $address) => $address->where('pincode', $pincode));
        }

        if (($seller = trim((string) $filter('seller'))) !== '') {
            $collection->whereHas('sellerOrders.seller', fn (Builder $query) => $query
                ->where('code', $seller)
                ->orWhere('store_name', 'like', '%'.$seller.'%'));
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('order_no', 'like', '%'.$search.'%')
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')));
        }

        return $collection;
    }
}
