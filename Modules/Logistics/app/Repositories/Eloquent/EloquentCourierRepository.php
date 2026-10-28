<?php

namespace Modules\Logistics\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Logistics\Repositories\CourierRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;

class EloquentCourierRepository extends EloquentBaseRepository implements CourierRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'code', 'name', 'driver', 'supports_cod', 'supports_reverse', 'priority', 'status'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('logistics.cache.courier');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->textFilter('search', trans('logistics::courier.labels.search'), $filter('search'), '1'),
            $this->selectFilter('driver', trans('logistics::courier.labels.driver'), $filter('driver'), $options['drivers']),
            $this->selectFilter('status', trans('core::core.labels.status'), $filter('status'), $options['statuses']),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request), $request, config('logistics.cache.courier'), self::SORTABLE, 'priority');
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('logistics.cache.courier'));
        $collection = $this->model->newQuery();

        foreach (['driver', 'status'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'));
        }

        return $collection;
    }

    public function getOptions(bool $withPlaceholder = false, bool $all = false): array
    {
        $query = $this->model->newQuery()->orderBy('priority')->orderBy('name');

        if (! $all) {
            $query->where('status', config('core.enabled'));
        }

        $options = $query->pluck('name', 'id')->all();

        return ($withPlaceholder ? ['' => ' -- '.trans('core::core.labels.select').' -- '] : []) + $options;
    }
}
