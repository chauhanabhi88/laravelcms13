<?php

namespace Modules\Mail\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Mail\Repositories\MailRepository;

class EloquentMailRepository extends EloquentBaseRepository implements MailRepository
{
    protected $_mail = null;

    public function getFilters($request, $statusOptions)
    {
        $fields = [
            [
                'type' => 'number_range',
                'name' => ['from', 'to'],
                'row' => '1',
                'value' => [
                    'from' => $request->get('from', getSessionFilter(config('mail.name'), 'from')),
                    'to' => $request->get('to', getSessionFilter(config('mail.name'), 'to')),
                ],
                'options' => [
                    'from' => ['label' => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ],
            [
                'type' => 'text',
                'name' => 'name',
                'row' => '1',
                'value' => $request->get('name', getSessionFilter(config('mail.name'), 'name')),
                'options' => ['placeholder' => trans('mail::mail.titles.name'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'row' => '1',
                'name' => 'status',
                'value' => $request->get('status', getSessionFilter(config('mail.name'), 'status')),
                'select_options' => $statusOptions,
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'date_range',
                'row' => '1',
                'name' => ['created_at_from', 'created_at_to'],
                'value' => [
                    'created_at_from' => $request->get('created_at_from', getSessionFilter(config('mail.name'), 'created_at_from')),
                    'created_at_to' => $request->get('created_at_to', getSessionFilter(config('mail.name'), 'created_at_to')),
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
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([config('mail.name')]))."'",
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
        $orderBy = getSessionFilter(config('mail.name'), 'order_by') ? getSessionFilter(config('mail.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('mail.name'), 'dir') ? getSessionFilter(config('mail.name'), 'dir') : $request->get('dir', 'desc');

        $collection = $this->filter($request);

        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config('mail.name'), $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config('mail.name'), 'page'));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();
        $collection = $this->allWithBuilder();

        $whereCond = $request->get('from', getSessionFilter(config('mail.name'), 'from'));
        if ($whereCond !== null) {
            $collection->where('id', '>=', $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config('mail.name'), 'to'));
        if ($whereCond !== null) {
            $collection->where('id', '<=', $whereCond);
        }

        $whereCond = $request->get('name', getSessionFilter(config('mail.name'), 'name'));
        if ($whereCond !== null) {
            // $name = $request->get('name');
            $collection->where('name', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('slug', getSessionFilter(config('mail.name'), 'slug'));
        if ($whereCond !== null) {
            // $slug = $request->get('slug');
            $collection->where('slug', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('status', getSessionFilter(config('mail.name'), 'status'));
        if ($whereCond !== null) {
            $collection->where('status', $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config('mail.name'), 'created_at_from'));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date('Y-m-d', strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config('mail.name'), 'created_at_to'));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date('Y-m-d', strtotime($whereCond)));
        }

        return $collection;
    }
}
