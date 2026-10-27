<?php

namespace Modules\Catalog\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Repositories\CatalogBrandRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentCatalogBrandRepository extends EloquentBaseRepository implements CatalogBrandRepository
{
    private const SORTABLE = ['id', 'slug', 'is_verified', 'status', 'created_at'];

    public function getFilters(Request $request, array $statusOptions): array
    {
        $sessionKey = config('catalog.cache.brand');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $yesNo = $this->getYesNoOptions(true);

        return [
            [
                'type' => 'text',
                'name' => 'name',
                'row' => '1',
                'value' => $filter('name'),
                'options' => ['placeholder' => trans('catalog::catalog_brand.labels.name'), 'class' => 'form-control'],
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
                'type' => 'select',
                'name' => 'is_verified',
                'row' => '1',
                'value' => $filter('is_verified'),
                'select_options' => $yesNo,
                'options' => ['label' => trans('catalog::catalog_brand.labels.is_verified'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'pending',
                'row' => '1',
                'value' => $filter('pending'),
                'select_options' => $yesNo,
                'options' => ['label' => trans('catalog::catalog_brand.labels.pending_requests'), 'class' => 'custom-select'],
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
        $sessionKey = config('catalog.cache.brand');
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'id';
        $dir = strtoupper((string) $request->input('dir')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request)->orderBy($orderBy, $dir);
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = config('catalog.cache.brand');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery()->with('translations');

        if (($name = trim((string) $filter('name'))) !== '') {
            $collection->where(function (Builder $query) use ($name): void {
                $query->where('slug', 'like', '%'.$name.'%')
                    ->orWhereHas('translations', fn (Builder $translation) => $translation->where('name', 'like', '%'.$name.'%'));
            });
        }

        foreach (['status', 'is_verified'] as $column) {
            if (($value = $filter($column)) !== null && $value !== '') {
                $collection->where($column, (int) $value);
            }
        }

        $pending = $filter('pending');
        if ((string) $pending === (string) config('core.yes')) {
            $collection->pendingRequests();
        } elseif ((string) $pending === (string) config('core.no')) {
            $collection->where(fn (Builder $query) => $query->whereNull('requested_by_seller_id')->orWhere('status', config('core.enabled')));
        }

        return $collection;
    }

    public function searchEnabled(?string $term, int $limit): Collection
    {
        $term = trim((string) $term);

        return $this->model->newQuery()
            ->with('translations')
            ->enabled()
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('slug', 'like', '%'.$term.'%')
                        ->orWhereHas('translations', fn (Builder $translation) => $translation->where('name', 'like', '%'.$term.'%'));
                });
            })
            ->orderBy('slug')
            ->limit($limit)
            ->get()
            ->sortBy(fn (CatalogBrand $brand): string => mb_strtolower((string) $brand->name))
            ->values();
    }
}
