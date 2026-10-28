<?php

namespace Modules\Logistics\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Logistics\Repositories\PickupBatchRepository;
use Modules\Order\Repositories\Eloquent\Concerns\FiltersByDateRange;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;

class EloquentPickupBatchRepository extends EloquentBaseRepository implements PickupBatchRepository
{
    use BuildsGridFilters, FiltersByDateRange;

    private const SORTABLE = ['id', 'manifest_no', 'carrier_name', 'pickup_date', 'shipments_count', 'status', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('logistics.cache.pickup_batch');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->textFilter('search', trans('logistics::pickup_batch.labels.search'), $filter('search'), '1'),
            $this->selectFilter('status', trans('logistics::pickup_batch.labels.status'), $filter('status'), $options['statuses']),
            $this->dateRangeFilter('pickup_date', trans('logistics::pickup_batch.labels.pickup_date'), $filter),
            $this->textFilter('seller', trans('logistics::pickup_batch.labels.seller_search'), $filter('seller')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with('seller'), $request, config('logistics.cache.pickup_batch'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('logistics.cache.pickup_batch'));
        $collection = $this->model->newQuery();

        if (($status = (string) $filter('status')) !== '') {
            $collection->where('status', $status);
        }

        $this->applyDateRange($collection, 'pickup_date', $filter('pickup_date_from'), $filter('pickup_date_to'));

        if (($seller = trim((string) $filter('seller'))) !== '') {
            $collection->whereHas('seller', fn (Builder $query) => $query
                ->where('code', $seller)
                ->orWhere('store_name', 'like', '%'.$seller.'%'));
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('manifest_no', 'like', $search.'%')
                ->orWhereIn('id', fn ($sub) => $sub->select('pickup_batch_id')->from('shipment')->where('awb', $search)));
        }

        return $collection;
    }
}
