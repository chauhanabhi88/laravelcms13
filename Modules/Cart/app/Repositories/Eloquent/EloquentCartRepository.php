<?php

namespace Modules\Cart\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Cart\Repositories\CartRepository;
use Modules\Cart\Support\CartOptions;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentCartRepository extends EloquentBaseRepository implements CartRepository
{
    private const SORTABLE = ['id', 'type', 'last_activity_at', 'reminded_at', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('cart.cache.cart');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $select = ['' => ' -- '.trans('core::core.labels.select').' -- '];

        return [
            [
                'type' => 'select',
                'name' => 'owner',
                'row' => '1',
                'value' => $filter('owner'),
                'select_options' => $select + $options['owners'],
                'options' => ['label' => trans('cart::cart.labels.owner'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'type',
                'row' => '1',
                'value' => $filter('type'),
                'select_options' => $select + $options['types'],
                'options' => ['label' => trans('cart::cart.labels.type'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'state',
                'row' => '1',
                'value' => $filter('state'),
                'select_options' => $select + $options['states'],
                'options' => ['label' => trans('cart::cart.labels.state'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'text',
                'name' => 'customer',
                'row' => '2',
                'value' => $filter('customer'),
                'options' => ['placeholder' => trans('cart::cart.labels.customer_search'), 'class' => 'form-control'],
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
        $sessionKey = config('cart.cache.cart');
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'last_activity_at';
        $dir = strtoupper((string) $request->input('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request)
            ->with('customer')
            ->withCount(['items as active_lines' => fn ($query) => $query->active()])
            ->withSum(['items as active_quantity' => fn ($query) => $query->active()], 'quantity')
            ->withCount(['items as saved_lines' => fn ($query) => $query->savedForLater()])
            ->orderBy($orderBy, $dir)
            ->orderByDesc('id');
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = config('cart.cache.cart');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery();

        match ((string) $filter('owner')) {
            CartOptions::OWNER_CUSTOMER => $collection->whereNotNull('customer_id'),
            CartOptions::OWNER_GUEST => $collection->whereNull('customer_id'),
            default => null,
        };

        if (($type = (string) $filter('type')) !== '') {
            $collection->where('type', $type);
        }

        $abandonedBefore = now()->subHours((int) config('cart.abandoned.after_hours', 3));

        match ((string) $filter('state')) {
            CartOptions::STATE_ACTIVE => $collection->whereHas('items', fn ($query) => $query->active())->where('last_activity_at', '>=', $abandonedBefore),
            CartOptions::STATE_ABANDONED => $collection->whereHas('items', fn ($query) => $query->active())->where('last_activity_at', '<', $abandonedBefore),
            CartOptions::STATE_EMPTY => $collection->whereDoesntHave('items', fn ($query) => $query->active()),
            default => null,
        };

        if (($customer = trim((string) $filter('customer'))) !== '') {
            $collection->whereHas('customer', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('first_name', 'like', '%'.$customer.'%')
                ->orWhere('last_name', 'like', '%'.$customer.'%')
                ->orWhere('email', 'like', '%'.$customer.'%')
                ->orWhere('contact_number', 'like', '%'.$customer.'%')));
        }

        return $collection;
    }
}
