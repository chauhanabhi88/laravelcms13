<?php

namespace Modules\Customer\Repositories\Eloquent;

use Illuminate\Support\Facades\Cache as FacadesCache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Customer\Repositories\CustomerOnlineOfflineLogRepository;

class EloquentCustomerOnlineOfflineLogRepository extends EloquentBaseRepository implements CustomerOnlineOfflineLogRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                'title' => trans('core::core.titles.id'),
                'column' => 'id',
            ],

            [
                'title' => trans('customer::customer_online_offline.titles.customer_name'),
                'column' => 'first_name',
            ],
            [
                'title' => trans('customer::customer_online_offline.titles.email'),
                'column' => 'email',
            ],
            [
                'title' => trans('customer::customer_online_offline.titles.status'),
                'column' => 'status',
                'no_sort' => true,
            ],
        ];

        $orderBy = getSessionFilter(config('customer.cache.customer_online_offline_log'), 'order_by') ? getSessionFilter(config('customer.cache.customer_online_offline_log'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('customer.cache.customer_online_offline_log'), 'dir') ? getSessionFilter(config('customer.cache.customer_online_offline_log'), 'dir') : $request->get('dir', 'desc');
        $columns = $this->defaultSort($columns, $orderBy, $dir);

        return $columns;
    }

    public function getFilters($request)
    {
        $fields = [
            [
                'type' => 'number_range',
                'name' => ['from', 'to'],
                'row' => '1',
                'value' => [
                    'from' => $request->get('from', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'from')),
                    'to' => $request->get('to', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'to')),
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
                'value' => $request->get('name', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'name')),
                'options' => ['placeholder' => trans('customer::customer_online_offline.titles.customer_name'), 'class' => 'form-control'],
            ],
            [
                'type' => 'text',
                'name' => 'email',
                'row' => '1',
                'value' => $request->get('email', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'email')),
                'options' => ['placeholder' => trans('customer::customer_online_offline.titles.email'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'row' => '1',
                'value' => $request->get('status', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'status')),
                'select_options' => $this->getStatusOptions(true),
                'options' => ['label' => trans('customer::customer_online_offline.titles.status'), 'class' => 'custom-select', 'id' => 'customerStatus'],
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
                        'class' => 'btn btn-primary btn-sm au-btn-icon',
                        'icon_class' => 'fa fa-search',
                        'title' => trans('core::core.buttons.search'),
                    ],
                    'reset' => [
                        'name' => 'reset',
                        'type' => 'button',
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([config('customer.cache.customer_online_offline_log'), config('customer.cache.customer_online_offline_log')]))."'",
                        'class' => 'btn btn-secondary btn-sm au-btn-icon',
                        'icon_class' => 'fa fa-refresh',
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
        if ($perPage == null) {
            $perPage = \Config::get('core.per_page_info');
        }
        $orderBy = getSessionFilter(config('customer.cache.customer_online_offline_log'), 'order_by') ? getSessionFilter(config('customer.cache.customer_online_offline_log'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('customer.cache.customer_online_offline_log'), 'dir') ? getSessionFilter(config('customer.cache.customer_online_offline_log'), 'dir') : $request->get('dir', 'desc');
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config('customer.cache.customer_online_offline_log'), $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'page'));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();
        $collection = $this->allWithBuilder();

        // For filtering customer based on active inactive
        $fileStore = FacadesCache::store('customer');
        $activeCustomers = [];
        $whereCond = $request->get('status', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'status'));
        if ($whereCond !== null) {
            if ($collection->count() > 0) {
                foreach ($collection->get() as $customer) {
                    $customerStatus = $fileStore->get('customer-is-online-'.$customer->id);
                    if (! empty($customerStatus)) {
                        $activeCustomers[] = $customer->id;
                    }
                }
            }
            if ($whereCond == config('customer.customer_log.online')) {
                $collection = $collection->whereIn('id', $activeCustomers);
            }

            if ($whereCond == config('customer.customer_log.offline')) {
                $collection = $collection->whereNotIn('id', $activeCustomers);
            }
        }
        // End filtering active/inactive

        $whereCond = $request->get('from', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'from'));
        if ($whereCond !== null) {
            $collection->where('id', '>=', $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'to'));
        if ($whereCond !== null) {
            $collection->where('id', '<=', $whereCond);
        }

        $whereCond = $request->get('name', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'name'));
        if ($whereCond !== null) {
            $collection->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'LIKE', '%'.$whereCond.'%');
        }

        $whereCond = $request->get('email', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'email'));
        if ($whereCond !== null) {
            $collection->where('email', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'created_at_from'));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date('Y-m-d', strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config('customer.cache.customer_online_offline_log'), 'created_at_to'));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date('Y-m-d', strtotime($whereCond)));
        }

        return $collection;
    }

    public function getStatusOptions($flag = false)
    {
        $options = [];
        if ($flag) {
            $options[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }

        return $options + [
            config('customer.customer_log.online') => trans('customer::customer_online_offline.customer_log.online'),
            config('customer.customer_log.offline') => trans('customer::customer_online_offline.customer_log.offline'),
        ];
    }
}
