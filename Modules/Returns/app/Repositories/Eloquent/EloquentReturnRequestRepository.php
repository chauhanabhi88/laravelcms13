<?php

namespace Modules\Returns\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;
use Modules\Returns\Repositories\ReturnRequestRepository;

class EloquentReturnRequestRepository extends EloquentBaseRepository implements ReturnRequestRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'return_no', 'status', 'type', 'refund_amount', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('returns.cache.return_request');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('status', trans('returns::return_request.labels.status'), $filter('status'), $options['statuses']),
            $this->selectFilter('type', trans('returns::return_request.labels.type'), $filter('type'), $options['types']),
            $this->selectFilter('reason_code', trans('returns::return_request.labels.reason'), $filter('reason_code'), $options['reasons']),
            $this->selectFilter('needs_attention', trans('returns::return_request.labels.needs_attention'), $filter('needs_attention'), $options['attention']),
            $this->textFilter('search', trans('returns::return_request.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with(['sellerOrder', 'seller', 'customer']), $request, config('returns.cache.return_request'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('returns.cache.return_request'));
        $collection = $this->model->newQuery();

        foreach (['status', 'type', 'reason_code', 'needs_attention'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('return_no', 'like', '%'.$search.'%')
                ->orWhereHas('sellerOrder', fn (Builder $order) => $order->where('sub_order_no', 'like', '%'.$search.'%'))
                ->orWhereHas('customer', fn (Builder $customer) => $customer->where('phone', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')));
        }

        return $collection;
    }
}
