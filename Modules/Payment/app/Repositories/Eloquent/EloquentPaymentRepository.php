<?php

namespace Modules\Payment\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;
use Modules\Payment\Repositories\PaymentRepository;

class EloquentPaymentRepository extends EloquentBaseRepository implements PaymentRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'order_no', 'method', 'status', 'amount', 'amount_refunded', 'captured_at', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('payment.cache.payment');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('method', trans('payment::payment.labels.method'), $filter('method'), $options['methods']),
            $this->selectFilter('status', trans('payment::payment.labels.status'), $filter('status'), $options['statuses']),
            $this->textFilter('search', trans('payment::payment.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with('customer'), $request, config('payment.cache.payment'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('payment.cache.payment'));
        $collection = $this->model->newQuery();

        foreach (['method', 'status'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('order_no', 'like', '%'.$search.'%')
                ->orWhere('gateway_order_id', $search)
                ->orWhere('gateway_payment_id', $search)
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')));
        }

        return $collection;
    }
}
