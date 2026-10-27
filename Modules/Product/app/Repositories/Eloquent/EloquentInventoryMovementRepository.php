<?php

namespace Modules\Product\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Repositories\InventoryMovementRepository;

class EloquentInventoryMovementRepository extends EloquentBaseRepository implements InventoryMovementRepository
{
    private const SORTABLE = ['id', 'created_at', 'type', 'bucket', 'quantity'];

    public function getFilters(Request $request): array
    {
        $sessionKey = $this->sessionKey();
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $select = ['' => trans('core::core.labels.select')];

        return [
            [
                'type' => 'text',
                'name' => 'search',
                'row' => '1',
                'value' => $filter('search'),
                'options' => ['placeholder' => trans('product::product.labels.movement_search'), 'class' => 'form-control'],
            ],
            [
                'type' => 'text',
                'name' => 'product_id',
                'row' => '1',
                'value' => $filter('product_id'),
                'options' => ['placeholder' => trans('product::product.labels.product_id'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'type',
                'row' => '1',
                'value' => $filter('type'),
                'select_options' => $select + array_combine(InventoryMovement::TYPES, array_map(fn (string $type): string => trans('product::product.movement_types.'.$type), InventoryMovement::TYPES)),
                'options' => ['label' => trans('product::product.labels.movement_type'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'date_range',
                'row' => '2',
                'name' => ['created_at_from', 'created_at_to'],
                'value' => ['created_at_from' => $filter('created_at_from'), 'created_at_to' => $filter('created_at_to')],
                'options' => [
                    'created_at_from' => ['label' => trans('product::product.labels.date'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control datepicker'],
                    'created_at_to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control datepicker'],
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
        $sessionKey = $this->sessionKey();
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'id';
        $dir = strtoupper((string) $request->input('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request)
            ->with(['variant:id,product_id,sku,seller_sku', 'product' => fn ($query) => $query->select('id')->with('translations')])
            ->orderBy($orderBy, $dir)
            ->orderBy('id', $dir);
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = $this->sessionKey();
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery();

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->whereHas('variant', fn (Builder $query) => $query->where('sku', 'like', '%'.$search.'%')->orWhere('seller_sku', 'like', '%'.$search.'%'));
        }

        if (($productId = (int) $filter('product_id')) > 0) {
            $collection->where('product_id', $productId);
        }

        if (in_array($type = (string) $filter('type'), InventoryMovement::TYPES, true)) {
            $collection->where('type', $type);
        }

        $timezoneOffset = getTimezoneOffset();
        if (($from = $filter('created_at_from')) !== null && $from !== '') {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", [date('Y-m-d', strtotime((string) $from))]);
        }
        if (($to = $filter('created_at_to')) !== null && $to !== '') {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", [date('Y-m-d', strtotime((string) $to))]);
        }

        return $collection;
    }

    private function sessionKey(): string
    {
        return (string) config('product.cache.inventory_movement');
    }
}
