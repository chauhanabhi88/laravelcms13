<?php

namespace Modules\Language\Repositories\Eloquent;

use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Language\Repositories\LanguageRepository;

class EloquentLanguageRepository extends EloquentBaseRepository implements LanguageRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                'title' => trans('core::core.titles.id'),
                'column' => 'id',
            ],
            [
                'title' => trans('language::language.titles.title'),
                'column' => 'title',
            ],
            [
                'title' => trans('language::language.titles.locale'),
                'column' => 'locale',
            ],
            [
                'title' => trans('language::language.titles.is_default'),
                'column' => 'is_default',
            ],
            // [
            //     "title" => trans("language::language.titles.status"),
            //     "column" => "status",
            // ],
            [
                'title' => trans('core::core.titles.created_at'),
                'column' => 'created_at',
            ],
        ];

        $orderBy = getSessionFilter(config('language.name'), 'order_by') ? getSessionFilter(config('language.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('language.name'), 'dir') ? getSessionFilter(config('language.name'), 'dir') : $request->get('dir', 'desc');
        $columns = $this->defaultSort($columns, $orderBy, $dir);

        return $columns;
    }

    public function getFilters($request, $statusOptions, $yesNoOptions)
    {
        $fields = [
            [
                'type' => 'number_range',
                'row' => '1',
                'name' => ['from', 'to'],
                'value' => [
                    'from' => $request->get('from', getSessionFilter(config('language.name'), 'from')),
                    'to' => $request->get('to', getSessionFilter(config('language.name'), 'to')),
                ],
                'options' => [
                    'from' => ['label' => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ],
            [
                'type' => 'text',
                'row' => '1',
                'name' => 'title',
                'value' => $request->get('title', getSessionFilter(config('language.name'), 'title')),
                'options' => ['placeholder' => trans('language::language.titles.title'), 'class' => 'form-control'],
            ],
            [
                'type' => 'text',
                'row' => '1',
                'name' => 'locale',
                'value' => $request->get('locale', getSessionFilter(config('language.name'), 'locale')),
                'options' => ['placeholder' => trans('language::language.titles.locale'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'row' => '2',
                'name' => 'is_default',
                'value' => $request->get('is_default', getSessionFilter(config('language.name'), 'is_default')),
                'select_options' => $yesNoOptions,
                'options' => ['label' => 'Is default', 'class' => 'custom-select'],
            ],
            [
                'type' => 'date_range',
                'row' => '2',
                'name' => ['created_at_from', 'created_at_to'],
                'value' => [
                    'created_at_from' => $request->get('created_at_from', getSessionFilter(config('language.name'), 'created_at_from')),
                    'created_at_to' => $request->get('created_at_to', getSessionFilter(config('language.name'), 'created_at_to')),
                ],
                'options' => [
                    'created_at_from' => ['label' => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control datepicker'],
                    'created_at_to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control datepicker'],
                ],
            ],
            [
                'type' => 'select',
                'row' => '2',
                'name' => 'status',
                'value' => $request->get('status', getSessionFilter(config('language.name'), 'status')),
                'select_options' => $statusOptions,
                'options' => ['label' => 'Status', 'class' => 'custom-select'],
            ],
            [
                'type' => 'action',
                'row' => '3',
                'class' => 'col-action',
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
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([config('language.name')]))."'",
                        'class' => 'btn btn-secondary btn-fw',
                        'title' => trans('core::core.buttons.reset'),
                    ],
                ],
            ],
        ];

        return $fields;
    }

    public function pagination($request)
    {
        $perPage = $request->get('per_page', settings('core', 'default_per_page'));
        $orderBy = getSessionFilter(config('language.name'), 'order_by') ? getSessionFilter(config('language.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('language.name'), 'dir') ? getSessionFilter(config('language.name'), 'dir') : $request->get('dir', 'desc');
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config('language.name'), $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config('language.name'), 'page'));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();

        $whereCond = $request->get('from', getSessionFilter(config('language.name'), 'from'));
        if ($whereCond !== null) {
            $collection->where('id', '>=', $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config('language.name'), 'to'));
        if ($whereCond !== null) {
            $collection->where('id', '<=', $whereCond);
        }

        $whereCond = $request->get('title', getSessionFilter(config('language.name'), 'title'));
        if ($whereCond !== null) {
            $collection->where('title', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('locale', getSessionFilter(config('language.name'), 'locale'));
        if ($whereCond !== null) {
            $collection->where('locale', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('is_default', getSessionFilter(config('language.name'), 'is_default'));
        if ($whereCond !== null) {
            $collection->where('is_default', $whereCond);
        }

        $whereCond = $request->get('status', getSessionFilter(config('language.name'), 'status'));
        if ($whereCond !== null) {
            $collection->where('status', $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config('language.name'), 'created_at_from'));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date('Y-m-d', strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config('language.name'), 'created_at_to'));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date('Y-m-d', strtotime($whereCond)));
        }

        return $collection;
    }

    public function getLanguageOptions()
    {
        $attributes = [
            'status' => config('core.enabled'),
        ];
        $collection = $this->getByAttributes($attributes);
        $languageLocale = [];
        if ($collection) {
            foreach ($collection as $language) {
                $languageLocale[$language->locale] = $language->title;
            }
        }

        return $languageLocale;
    }

    public function getTranslationOptions($flag = false)
    {
        $result = [];
        $result = trans('language::language.translation_option');
        if ($flag) {
            $result[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
        ksort($result);

        return $result;
    }
}
