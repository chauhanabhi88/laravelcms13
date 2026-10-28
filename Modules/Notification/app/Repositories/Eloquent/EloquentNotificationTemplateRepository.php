<?php

namespace Modules\Notification\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Notification\Repositories\NotificationTemplateRepository;

class EloquentNotificationTemplateRepository extends EloquentBaseRepository implements NotificationTemplateRepository
{
    private const SORTABLE = ['id', 'event_key', 'channel', 'audience', 'status', 'updated_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('notification.cache.notification_template');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $select = ['' => ' -- '.trans('core::core.labels.select').' -- '];

        return [
            [
                'type' => 'select',
                'name' => 'event_key',
                'row' => '1',
                'value' => $filter('event_key'),
                'select_options' => $select + $options['events'],
                'options' => ['label' => trans('notification::notification_template.labels.event_key'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'channel',
                'row' => '1',
                'value' => $filter('channel'),
                'select_options' => $select + $options['channels'],
                'options' => ['label' => trans('notification::notification_template.labels.channel'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'audience',
                'row' => '1',
                'value' => $filter('audience'),
                'select_options' => $select + $options['audiences'],
                'options' => ['label' => trans('notification::notification_template.labels.audience'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'row' => '1',
                'value' => $filter('status'),
                'select_options' => $options['statuses'],
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'text',
                'name' => 'body',
                'row' => '2',
                'value' => $filter('body'),
                'options' => ['placeholder' => trans('notification::notification_template.labels.search_text'), 'class' => 'form-control'],
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
        $sessionKey = config('notification.cache.notification_template');
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'event_key';
        $dir = strtoupper((string) $request->input('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $collection = $this->filter($request)->orderBy($orderBy, $dir)->orderBy('channel')->orderBy('id');
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = config('notification.cache.notification_template');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery();

        foreach (['event_key', 'channel', 'audience'] as $column) {
            if (($value = $filter($column)) !== null && $value !== '') {
                $collection->where($column, (string) $value);
            }
        }

        if (($status = $filter('status')) !== null && $status !== '') {
            $collection->where('status', (int) $status);
        }

        if (($text = trim((string) $filter('body'))) !== '') {
            $collection->where(fn (Builder $query) => $query->where('body', 'like', '%'.$text.'%')->orWhere('title', 'like', '%'.$text.'%'));
        }

        return $collection;
    }
}
