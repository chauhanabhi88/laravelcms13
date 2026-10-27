<?php

namespace Modules\Storefront\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Repositories\HomeSectionRepository;

class EloquentHomeSectionRepository extends EloquentBaseRepository implements HomeSectionRepository
{
    private const SORTABLE = ['id', 'type', 'sort_order', 'status', 'updated_at'];

    public function getFilters(Request $request, array $statusOptions, array $typeOptions): array
    {
        $sessionKey = config('storefront.cache.home_section');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));

        return [
            [
                'type' => 'text',
                'name' => 'title',
                'row' => '1',
                'value' => $filter('title'),
                'options' => ['placeholder' => trans('storefront::home_section.labels.title'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'type',
                'row' => '1',
                'value' => $filter('type'),
                'select_options' => ['' => ' -- '.trans('core::core.labels.select').' -- '] + $typeOptions,
                'options' => ['label' => trans('storefront::home_section.labels.type'), 'class' => 'custom-select'],
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
        $sessionKey = config('storefront.cache.home_section');
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : 'sort_order';
        $dir = strtoupper((string) $request->input('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $collection = $this->filter($request)->orderBy($orderBy, $dir)->orderBy('id');
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request): Builder
    {
        $sessionKey = config('storefront.cache.home_section');
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery()->with('translations');

        if (($title = trim((string) $filter('title'))) !== '') {
            $collection->whereHas('translations', fn (Builder $translation) => $translation->where('title', 'like', '%'.$title.'%'));
        }

        if (($type = $filter('type')) !== null && $type !== '') {
            $collection->where('type', (string) $type);
        }

        if (($status = $filter('status')) !== null && $status !== '') {
            $collection->where('status', (int) $status);
        }

        return $collection;
    }

    public function move(HomeSection $section, string $direction): void
    {
        DB::transaction(function () use ($section, $direction): void {
            $ordered = $this->model->newQuery()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $index = array_search($section->id, $ordered, true);
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($ordered[$target])) {
                return;
            }

            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

            foreach ($ordered as $position => $id) {
                $this->model->newQuery()->whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });
    }

    public function nextSortOrder(): int
    {
        return (int) $this->model->newQuery()->max('sort_order') + 10;
    }
}
