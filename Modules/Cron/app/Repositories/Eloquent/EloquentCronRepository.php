<?php

namespace Modules\Cron\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Cron\Repositories\CronRepository;

class EloquentCronRepository extends EloquentBaseRepository implements CronRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                'title' => trans('core::core.titles.id'),
                'column' => 'id',
            ],
            [
                'title' => trans('cron::cron.titles.title'),
                'column' => 'title',
            ],
            [
                'title' => trans('cron::cron.titles.command'),
                'column' => 'command',
            ],
            [
                'title' => trans('cron::cron.titles.cron_expression'),
                'column' => 'cron_expression',
            ],
            [
                'title' => trans('core::core.titles.created_at'),
                'column' => 'created_at',
            ],
        ];

        if ($this->getAuthUser()->can('admin.cron.mass_delete')) {
            $massDeleteCheckbox = [
                'column' => 'massDelete',
                'type' => 'massDelete',
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config('cron.cache.name'), 'order_by') ? getSessionFilter(config('cron.cache.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('cron.cache.name'), 'dir') ? getSessionFilter(config('cron.cache.name'), 'dir') : $request->get('dir', 'desc');
        $columns = $this->defaultSort($columns, $orderBy, $dir);

        return $columns;
    }

    public function getFilters($request)
    {
        $statusOptions = $this->getStatusOptions(true);
        $fields = [
            [
                'type' => 'number_range',
                'name' => ['from', 'to'],
                'row' => '1',
                'value' => [
                    'from' => $request->get('from', getSessionFilter(config('cron.cache.name'), 'from')),
                    'to' => $request->get('to', getSessionFilter(config('cron.cache.name'), 'to')),
                ],
                'options' => [
                    'from' => ['label' => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ],
            [
                'type' => 'text',
                'name' => 'title',
                'row' => '1',
                'value' => $request->get('title', getSessionFilter(config('cron.cache.name'), 'title')),
                'options' => ['placeholder' => trans('cron::cron.titles.title'), 'class' => 'form-control'],
            ],

            [
                'type' => 'text',
                'name' => 'command',
                'row' => '1',
                'value' => $request->get('command', getSessionFilter(config('cron.cache.name'), 'command')),
                'options' => ['placeholder' => trans('cron::cron.titles.command'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'row' => '1',
                'name' => 'status',
                'value' => $request->get('status', getSessionFilter(config('cron.cache.name'), 'status')),
                'select_options' => $statusOptions,
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'text',
                'name' => 'cron_expression',
                'row' => '1',
                'value' => $request->get('cron_expression', getSessionFilter(config('cron.cache.name'), 'cron_expression')),
                'options' => ['placeholder' => trans('cron::cron.titles.cron_expression'), 'class' => 'form-control'],
            ],
            [
                'type' => 'date_range',
                'row' => '1',
                'name' => ['created_at_from', 'created_at_to'],
                'value' => [
                    'created_at_from' => $request->get('created_at_from', getSessionFilter(config('cron.cache.name'), 'created_at_from')),
                    'created_at_to' => $request->get('created_at_to', getSessionFilter(config('cron.cache.name'), 'created_at_to')),
                ],
                'options' => [
                    'created_at_from' => ['label' => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'created_at_to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ],
            [
                'type' => 'action',
                'class' => 'col-action',
                'row' => '3',
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
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([config('cron.cache.name')]))."'",
                        'class' => 'btn btn-secondary btn-fw',
                        'title' => trans('core::core.buttons.reset'),
                    ],
                ],
            ],
        ];

        return $fields;
    }

    public function pagination(Request $request)
    {
        $perPage = $request->get('per_page', settings('core', 'default_per_page'));
        $orderBy = getSessionFilter(config('cron.cache.name'), 'order_by') ? getSessionFilter(config('cron.cache.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('cron.cache.name'), 'dir') ? getSessionFilter(config('cron.cache.name'), 'dir') : $request->get('dir', 'desc');
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config('cron.cache.name'), $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config('cron.cache.name'), 'page'));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();
        $collection = $this->allWithBuilder();

        $whereCond = $request->get('from', getSessionFilter(config('cron.cache.name'), 'from'));
        if ($whereCond !== null) {
            $collection->where('id', '>=', $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config('cron.cache.name'), 'to'));
        if ($whereCond !== null) {
            $collection->where('id', '<=', $whereCond);
        }

        $whereCond = $request->get('title', getSessionFilter(config('cron.cache.name'), 'title'));
        if ($whereCond !== null) {
            $collection->where('title', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('cron_expression', getSessionFilter(config('cron.cache.name'), 'cron_expression'));
        if ($whereCond !== null) {
            $collection->where('cron_expression', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('command', getSessionFilter(config('cron.cache.name'), 'command'));
        if ($whereCond !== null) {
            $collection->where('command', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('status', getSessionFilter(config('cron.cache.name'), 'status'));
        if ($whereCond !== null) {
            $collection->where('status', $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config('cron.cache.name'), 'created_at_from'));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date('Y-m-d', strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config('cron.cache.name'), 'created_at_to'));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date('Y-m-d', strtotime($whereCond)));
        }

        return $collection;
    }
}
