<?php

namespace Modules\Notification\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Notification\Repositories\NotificationLogRepository;

class EloquentNotificationLogRepository extends EloquentBaseRepository implements NotificationLogRepository
{
    private const SORTABLE = ['id', 'created_at', 'event_key', 'channel', 'status', 'sent_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('notification.cache.notification_log');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $select = ['' => ' -- '.trans('core::core.labels.select').' -- '];

        return [
            [
                'type' => 'select',
                'name' => 'event_key',
                'row' => '1',
                'value' => $filter('event_key'),
                'select_options' => $select + $options['events'],
                'options' => ['label' => trans('notification::notification_log.labels.event_key'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'channel',
                'row' => '1',
                'value' => $filter('channel'),
                'select_options' => $select + $options['channels'],
                'options' => ['label' => trans('notification::notification_log.labels.channel'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'row' => '1',
                'value' => $filter('status'),
                'select_options' => $select + $options['statuses'],
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'text',
                'name' => 'destination',
                'row' => '2',
                'value' => $filter('destination'),
                'options' => ['placeholder' => trans('notification::notification_log.labels.destination'), 'class' => 'form-control'],
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
        $sessionKey = config('notification.cache.notification_log');
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'id';
        $dir = strtoupper((string) $request->input('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request)->with('notifiable')->orderBy($orderBy, $dir)->orderByDesc('id');
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = config('notification.cache.notification_log');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery();

        foreach (['event_key', 'channel', 'status'] as $column) {
            if (($value = $filter($column)) !== null && $value !== '') {
                $collection->where($column, (string) $value);
            }
        }

        if (($destination = trim((string) $filter('destination'))) !== '') {
            $collection->where('destination', 'like', '%'.$destination.'%');
        }

        return $collection;
    }
}
