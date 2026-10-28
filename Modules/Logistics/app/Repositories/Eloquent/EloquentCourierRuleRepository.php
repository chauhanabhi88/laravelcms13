<?php

namespace Modules\Logistics\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Logistics\Repositories\CourierRuleRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;

class EloquentCourierRuleRepository extends EloquentBaseRepository implements CourierRuleRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'priority', 'name', 'strategy', 'status'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('logistics.cache.courier_rule');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->textFilter('name', trans('logistics::courier_rule.labels.name'), $filter('name'), '1'),
            $this->selectFilter('courier_id', trans('logistics::courier_rule.labels.courier'), $filter('courier_id'), $options['couriers']),
            $this->selectFilter('strategy', trans('logistics::courier_rule.labels.strategy'), $filter('strategy'), $options['strategies']),
            $this->selectFilter('status', trans('core::core.labels.status'), $filter('status'), $options['statuses']),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        $sessionKey = config('logistics.cache.courier_rule');
        $request->merge(['dir' => $request->input('dir', 'ASC')]);

        return $this->paginateGrid($this->filter($request)->with('courier'), $request, $sessionKey, self::SORTABLE, 'priority');
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('logistics.cache.courier_rule'));
        $collection = $this->model->newQuery();

        foreach (['courier_id', 'strategy', 'status'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        if (($name = trim((string) $filter('name'))) !== '') {
            $collection->where('name', 'like', '%'.$name.'%');
        }

        return $collection;
    }
}
