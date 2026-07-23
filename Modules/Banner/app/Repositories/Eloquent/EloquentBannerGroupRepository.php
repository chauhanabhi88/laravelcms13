<?php

namespace Modules\Banner\Repositories\Eloquent;

use Modules\Banner\Models\BannerGroup;
use Modules\Banner\Models\BannerGroupTranslation;
use Modules\Banner\Repositories\BannerGroupRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentBannerGroupRepository extends EloquentBaseRepository implements BannerGroupRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                'title' => trans('core::core.titles.id'),
                'column' => 'id',
            ],
            [
                'title' => trans('banner::banner_group.titles.name'),
                'column' => 'name',
            ],
            [
                'title' => trans('banner::banner_group.titles.code'),
                'column' => 'code',
            ],
            [
                'title' => trans('core::core.titles.created_at'),
                'column' => 'created_at',
            ],
        ];

        if ($this->getAuthUser()->can('admin.banner.mass_delete')) {
            $massDeleteCheckbox = [
                'column' => 'massDelete',
                'type' => 'massDelete',
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config('banner.cache.banner_group_name'), 'order_by') ? getSessionFilter(config('banner.cache.banner_group_name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('banner.cache.banner_group_name'), 'dir') ? getSessionFilter(config('banner.cache.banner_group_name'), 'dir') : $request->get('dir', 'desc');
        $columns = $this->defaultSort($columns, $orderBy, $dir);

        return $columns;
    }

    public function getFilters($request, $statusOptions)
    {
        $fields = [
            [
                'type' => 'number_range',
                'name' => ['from', 'to'],
                'row' => '1',
                'value' => [
                    'from' => $request->get('from', getSessionFilter(config('banner.cache.banner_group_name'), 'from')),
                    'to' => $request->get('to', getSessionFilter(config('banner.cache.banner_group_name'), 'to')),
                ],
                'options' => [
                    'from' => ['label' => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ],
            [
                'type' => 'text',
                'row' => '1',
                'name' => 'name',
                'value' => $request->get('name', getSessionFilter(config('banner.cache.banner_group_name'), 'name')),
                'options' => ['placeholder' => trans('banner::banner_group.titles.name'), 'class' => 'form-control'],
            ],
            [
                'type' => 'text',
                'name' => 'code',
                'row' => '1',
                'value' => $request->get('code', getSessionFilter(config('banner.cache.banner_group_name'), 'code')),
                'options' => ['placeholder' => trans('banner::banner_group.titles.code'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'row' => '1',
                'name' => 'status',
                'value' => $request->get('status', getSessionFilter(config('banner.cache.banner_group_name'), 'status')),
                'select_options' => $statusOptions,
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'date_range',
                'name' => ['created_at_from', 'created_at_to'],
                'row' => '1',
                'value' => [
                    'created_at_from' => $request->get('created_at_from', getSessionFilter(config('banner.cache.banner_group_name'), 'created_at_from')),
                    'created_at_to' => $request->get('created_at_to', getSessionFilter(config('banner.cache.banner_group_name'), 'created_at_to')),
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
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([config('banner.cache.banner_group_name')]))."'",
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
        $orderBy = getSessionFilter(config('banner.cache.banner_group_name'), 'order_by') ? getSessionFilter(config('banner.cache.banner_group_name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('banner.cache.banner_group_name'), 'dir') ? getSessionFilter(config('banner.cache.banner_group_name'), 'dir') : $request->get('dir', 'desc');
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config('banner.cache.banner_group_name'), $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config('banner.cache.banner_group_name'), 'page'));
    }

    public function filter($request)
    {
        $bannerGroupTranslation = new BannerGroupTranslation;
        $bannerGroupEntity = new BannerGroup;
        $bannerGroupTable = $bannerGroupEntity->getTable();

        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();
        $collection->join($bannerGroupTranslation->getTable().' AS bannerGroupTranslation', $bannerGroupEntity->getTable().'.id', '=', 'bannerGroupTranslation.banner_group_id')
            ->where('bannerGroupTranslation.locale', array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en')
            ->select($bannerGroupTable.'.*', 'bannerGroupTranslation.name');

        $whereCond = $request->get('from', getSessionFilter(config('banner.cache.banner_group_name'), 'from'));
        if ($whereCond !== null) {
            $collection->where($bannerGroupTable.'.id', '>=', $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config('banner.cache.banner_group_name'), 'to'));
        if ($whereCond !== null) {
            $collection->where($bannerGroupTable.'.id', '<=', $whereCond);
        }

        $whereCond = $request->get('name', getSessionFilter(config('banner.cache.banner_group_name'), 'name'));
        if ($whereCond !== null) {
            $name = $whereCond;
            $collection->whereHas('translations', function ($query) use ($name) {
                $query->where('name', 'LIKE', "%{$name}%");
            })->with('translations');
        }

        $whereCond = $request->get('code', getSessionFilter(config('banner.cache.banner_group_name'), 'code'));
        if ($whereCond !== null) {
            $code = $whereCond;
            $collection->where('code', 'LIKE', "%{$code}%");
        }

        $whereCond = $request->get('status', getSessionFilter(config('banner.cache.banner_group_name'), 'status'));
        if ($whereCond !== null) {
            $collection->where('status', $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config('banner.cache.banner_group_name'), 'created_at_from'));
        if ($whereCond !== null) {
            $collection->whereRaw('DATE('.$bannerGroupTable.".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date('Y-m-d', strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config('banner.cache.banner_group_name'), 'created_at_to'));
        if ($whereCond !== null) {
            $collection->whereRaw('DATE('.$bannerGroupTable.".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date('Y-m-d', strtotime($whereCond)));
        }

        return $collection;
    }

    public function getBannersByGroup($code, $locale)
    {
        $collection = $this->allWithBuilder()->where('status', config('core.yes'))->where('code', '=', $code);

        $collection->with(['translations' => function ($query) use ($locale) {
            $query->where('locale', $locale);
        }]);

        $collection->with(['banners.translations' => function ($query) use ($locale) {
            $query->where('locale', $locale);
        }]);

        $slides = $collection->orderBy('sort_order', 'ASC')->get();
        if (! $slides->isEmpty()) {
            $slides = $slides->toArray();

            return $slides[0]['banners'];
        }

        return [];
    }

    public function getBannerGroups($flag = false)
    {
        $bannerGroups = [];
        $bannerGroupTranslation = new BannerGroupTranslation;
        $bannerGroupEntity = new BannerGroup;
        $bannerGroupData = $this->allWithBuilder()->join($bannerGroupTranslation->getTable().' AS bannerGroupTranslation', $bannerGroupEntity->getTable().'.id', '=', 'bannerGroupTranslation.banner_group_id')
            ->where('bannerGroupTranslation.locale', array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en')->pluck('name', $bannerGroupEntity->getTable().'.id')->all();

        if ($flag) {
            $bannerGroupData[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
        ksort($bannerGroupData);

        return $bannerGroupData;
    }
}
