<?php

namespace Modules\Payment\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;
use Modules\Payment\Repositories\PaymentRefundRepository;

class EloquentPaymentRefundRepository extends EloquentBaseRepository implements PaymentRefundRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'amount', 'mode', 'status', 'processed_at', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('payment.cache.payment_refund');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('status', trans('payment::payment_refund.labels.status'), $filter('status'), $options['statuses']),
            $this->selectFilter('mode', trans('payment::payment_refund.labels.mode'), $filter('mode'), $options['modes']),
            $this->textFilter('search', trans('payment::payment_refund.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with('payment'), $request, config('payment.cache.payment_refund'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('payment.cache.payment_refund'));
        $collection = $this->model->newQuery();

        foreach (['status', 'mode'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('gateway_refund_id', $search)
                ->orWhere('utr', $search)
                ->orWhereHas('payment', fn (Builder $payment) => $payment
                    ->where('order_no', 'like', '%'.$search.'%')
                    ->orWhere('gateway_payment_id', $search)));
        }

        return $collection;
    }
}
