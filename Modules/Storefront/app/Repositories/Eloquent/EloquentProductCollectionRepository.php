<?php

namespace Modules\Storefront\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Storefront\Models\ProductCollection;
use Modules\Storefront\Repositories\ProductCollectionRepository;

class EloquentProductCollectionRepository extends EloquentBaseRepository implements ProductCollectionRepository
{
    private const SORTABLE = ['id', 'slug', 'type', 'sort', 'status', 'updated_at'];

    public function getFilters(Request $request, array $statusOptions): array
    {
        $sessionKey = config('storefront.cache.product_collection');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));

        return [
            [
                'type' => 'text',
                'name' => 'name',
                'row' => '1',
                'value' => $filter('name'),
                'options' => ['placeholder' => trans('storefront::product_collection.labels.name'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'type',
                'row' => '1',
                'value' => $filter('type'),
                'select_options' => [
                    '' => ' -- '.trans('core::core.labels.select').' -- ',
                    ProductCollection::TYPE_RULE => trans('storefront::product_collection.types.rule'),
                    ProductCollection::TYPE_MANUAL => trans('storefront::product_collection.types.manual'),
                ],
                'options' => ['label' => trans('storefront::product_collection.labels.type'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'row' => '1',
                'value' => $filter('status'),
                'select_options' => $statusOptions,
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
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
        $sessionKey = config('storefront.cache.product_collection');
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'id';
        $dir = strtoupper((string) $request->input('dir')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request)->orderBy($orderBy, $dir);
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = config('storefront.cache.product_collection');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery()->with('translations');

        if (($name = trim((string) $filter('name'))) !== '') {
            $collection->where(function (Builder $query) use ($name): void {
                $query->where('slug', 'like', '%'.$name.'%')
                    ->orWhereHas('translations', fn (Builder $translation) => $translation->where('name', 'like', '%'.$name.'%'));
            });
        }

        foreach (['type', 'status'] as $column) {
            if (($value = $filter($column)) !== null && $value !== '') {
                $collection->where($column, $column === 'status' ? (int) $value : (string) $value);
            }
        }

        return $collection;
    }

    public function getOptions(bool $withPlaceholder = false): array
    {
        $options = $this->model->newQuery()->with('translations')->orderBy('id')->get()
            ->mapWithKeys(fn (ProductCollection $collection): array => [$collection->id => (string) ($collection->name ?? $collection->slug)])
            ->all();

        return $withPlaceholder ? ['' => ' -- '.trans('core::core.labels.select').' -- '] + $options : $options;
    }
}
