<?php

namespace Modules\Catalog\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Repositories\CatalogSizeChartRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentCatalogSizeChartRepository extends EloquentBaseRepository implements CatalogSizeChartRepository
{
    private const SORTABLE = ['id', 'name', 'unit', 'updated_at'];

    public function getFilters(Request $request, array $statusOptions): array
    {
        $sessionKey = config('catalog.cache.size_chart');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $unitOptions = ['' => ' -- '.trans('core::core.labels.select').' -- '];
        foreach ((array) config('catalog.size_chart_units') as $unit) {
            $unitOptions[$unit] = trans('catalog::catalog_size_chart.units.'.$unit);
        }

        return [
            [
                'type' => 'text',
                'name' => 'name',
                'row' => '1',
                'value' => $filter('name'),
                'options' => ['placeholder' => trans('catalog::catalog_size_chart.labels.name'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'unit',
                'row' => '1',
                'value' => $filter('unit'),
                'select_options' => $unitOptions,
                'options' => ['label' => trans('catalog::catalog_size_chart.labels.unit'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'row' => '1',
                'value' => $filter('status'),
                'select_options' => $statusOptions,
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'action',
                'class' => 'col-action',
                'row' => '2',
                'buttons' => [
                    'submit' => [
                        'name' => 'search',
                        'type' => 'submit',
                        'onclick' => 'searchFilter(); return false;',
                        'class' => 'btn btn-primary btn-fw',
                        'title' => trans('core::core.buttons.search'),
                    ],
                    'reset' => [
                        'name' => 'reset',
                        'type' => 'button',
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([$sessionKey]))."'",
                        'class' => 'btn btn-secondary btn-fw',
                        'title' => trans('core::core.buttons.reset'),
                    ],
                ],
            ],
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        $sessionKey = config('catalog.cache.size_chart');
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'id';
        $dir = strtoupper((string) $request->input('dir')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request)->orderBy($orderBy, $dir);
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = config('catalog.cache.size_chart');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery();

        if (($name = trim((string) $filter('name'))) !== '') {
            $collection->where('name', 'like', '%'.$name.'%');
        }

        foreach (['unit', 'status'] as $column) {
            if (($value = $filter($column)) !== null && $value !== '') {
                $collection->where($column, $value);
            }
        }

        return $collection;
    }

    public function getOptions(bool $withPlaceholder = false): array
    {
        $options = $this->model->newQuery()
            ->where('status', config('core.enabled'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return ($withPlaceholder ? ['' => ' -- '.trans('core::core.labels.select').' -- '] : []) + $options;
    }
}
