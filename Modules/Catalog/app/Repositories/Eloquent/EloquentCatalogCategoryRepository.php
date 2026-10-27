<?php

namespace Modules\Catalog\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentCatalogCategoryRepository extends EloquentBaseRepository implements CatalogCategoryRepository
{
    private const SORTABLE = ['id', 'slug', 'depth', 'is_leaf', 'sort_order', 'show_in_menu', 'updated_at'];

    public function getFilters(Request $request, array $statusOptions): array
    {
        $sessionKey = config('catalog.cache.category');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $placeholder = ['' => ' -- '.trans('core::core.labels.select').' -- '];

        $depthOptions = $placeholder;
        for ($level = 1; $level <= CatalogSettings::maxCategoryDepth(); $level++) {
            $depthOptions[$level] = trans('catalog::catalog_category.labels.level_n', ['level' => $level]);
        }

        return [
            [
                'type' => 'text',
                'name' => 'name',
                'row' => '1',
                'value' => $filter('name'),
                'options' => ['placeholder' => trans('catalog::catalog_category.labels.name'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'parent_id',
                'row' => '1',
                'value' => $filter('parent_id'),
                'select_options' => $placeholder + $this->parentFilterOptions(),
                'options' => ['label' => trans('catalog::catalog_category.labels.parent'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'depth',
                'row' => '1',
                'value' => $filter('depth'),
                'select_options' => $depthOptions,
                'options' => ['label' => trans('catalog::catalog_category.labels.depth'), 'class' => 'custom-select'],
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
        $sessionKey = config('catalog.cache.category');
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'id';
        $dir = strtoupper((string) $request->input('dir')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request)->with('parent.translations')->orderBy($orderBy, $dir);
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = config('catalog.cache.category');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery()->with('translations');

        if (($name = trim((string) $filter('name'))) !== '') {
            $collection->whereHas('translations', fn (Builder $query) => $query->where('name', 'like', '%'.$name.'%'));
        }

        if (($parentId = $filter('parent_id')) !== null && $parentId !== '') {
            $collection->where('parent_id', (int) $parentId);
        }

        if (($depth = $filter('depth')) !== null && $depth !== '') {
            $collection->where('depth', max(0, (int) $depth - 1));
        }

        if (($status = $filter('status')) !== null && $status !== '') {
            $collection->where('status', (int) $status);
        }

        return $collection;
    }

    public function findEnabledBySlug(string $slug): ?CatalogCategory
    {
        return $this->model->newQuery()
            ->with('translations')
            ->enabled()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Every non-leaf category, indented by level, in tree order.
     *
     * @return array<int, string>
     */
    private function parentFilterOptions(): array
    {
        return $this->model->newQuery()
            ->with('translations')
            ->where('is_leaf', config('core.no'))
            ->orderBy('path')
            ->get()
            ->sortBy(fn (CatalogCategory $category): string => implode('.', array_map(fn (int $id): string => str_pad((string) $id, 10, '0', STR_PAD_LEFT), $category->pathIds())))
            ->mapWithKeys(fn (CatalogCategory $category): array => [$category->id => str_repeat('— ', $category->depth).$category->name])
            ->all();
    }
}
