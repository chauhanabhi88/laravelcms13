<?php

declare(strict_types=1);

namespace Modules\Payment\Repositories\Eloquent\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Filter-row definitions for core::partials.filters and the shared session-backed
 * pagination of the Payment admin grids.
 */
trait BuildsGridFilters
{
    /**
     * @return Closure(string): mixed
     */
    protected function filterReader(Request $request, string $sessionKey): Closure
    {
        return fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    protected function selectFilter(string $name, string $label, mixed $value, array $options, string $row = '1'): array
    {
        return [
            'type' => 'select',
            'name' => $name,
            'row' => $row,
            'value' => $value,
            'select_options' => ['' => ' -- '.trans('core::core.labels.select').' -- '] + $options,
            'options' => ['label' => $label, 'class' => 'custom-select'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function textFilter(string $name, string $placeholder, mixed $value, string $row = '2'): array
    {
        return [
            'type' => 'text',
            'name' => $name,
            'row' => $row,
            'value' => $value,
            'options' => ['placeholder' => $placeholder, 'class' => 'form-control'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterActions(string $sessionKey, string $row = '2'): array
    {
        return [
            'type' => 'action',
            'class' => 'col-action',
            'row' => $row,
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
        ];
    }

    /**
     * @param  list<string>  $sortable
     */
    protected function paginateGrid(Builder $collection, Request $request, string $sessionKey, array $sortable, string $defaultSort = 'id'): LengthAwarePaginator
    {
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), $sortable, true) ? $request->input('order_by') : $defaultSort;
        $dir = strtoupper((string) $request->input('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $collection->orderBy($orderBy, $dir)->orderByDesc('id');
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }
}
