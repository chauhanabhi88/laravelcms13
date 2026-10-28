<?php

namespace Modules\Returns\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;
use Modules\Returns\Repositories\ReturnClaimRepository;

class EloquentReturnClaimRepository extends EloquentBaseRepository implements ReturnClaimRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'status', 'reason', 'resolution_amount', 'created_at', 'decided_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('returns.cache.return_claim');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('status', trans('returns::return_claim.labels.status'), $filter('status'), $options['statuses']),
            $this->selectFilter('reason', trans('returns::return_claim.labels.reason'), $filter('reason'), $options['reasons']),
            $this->textFilter('search', trans('returns::return_claim.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with(['returnRequest', 'sellerOrder', 'seller']), $request, config('returns.cache.return_claim'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('returns.cache.return_claim'));
        $collection = $this->model->newQuery();

        foreach (['status', 'reason'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->whereHas('returnRequest', fn (Builder $return) => $return->where('return_no', 'like', '%'.$search.'%'))
                ->orWhereHas('sellerOrder', fn (Builder $order) => $order->where('sub_order_no', 'like', '%'.$search.'%')));
        }

        return $collection;
    }
}
