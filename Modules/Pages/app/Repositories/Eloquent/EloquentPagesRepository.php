<?php

namespace Modules\Pages\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Pages\Models\Pages;
use Modules\Pages\Models\PagesTranslation;
use Modules\Pages\Repositories\PagesRepository;

class EloquentPagesRepository extends EloquentBaseRepository implements PagesRepository
{
    protected $_page = null;

    /**
     * {@inheritdoc}
     *
     * Overrides the base implementation: for Pages, slug lives on the
     * pages table itself, not on the translated pages_translation table
     * (which has no slug column), so the inherited whereHas('translations', ...)
     * lookup would query a non-existent column.
     */
    public function findBySlug($slug)
    {
        return $this->model->where('slug', $slug)->with('translations')->first();
    }

    public function sortColumns($request)
    {
        $columns = [
            [
                'title' => trans('core::core.titles.id'),
                'column' => 'id',
            ],
            [
                'title' => trans('pages::pages.titles.title'),
                'column' => 'title',
            ],
            [
                'title' => trans('core::core.titles.created_at'),
                'column' => 'created_at',
            ],
        ];

        if ($this->getAuthUser()->can('admin.page.mass_delete')) {
            $massDeleteCheckbox = [
                'column' => 'massDelete',
                'type' => 'massDelete',
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config('pages.name'), 'order_by') ? getSessionFilter(config('pages.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('pages.name'), 'dir') ? getSessionFilter(config('pages.name'), 'dir') : $request->get('dir', 'desc');
        $columns = $this->defaultSort($columns, $orderBy, $dir);

        return $columns;
    }

    public function getFilters($request, $languageOptions, $statusOptions)
    {
        $fields = [
            [
                'type' => 'number_range',
                'name' => ['from', 'to'],
                'row' => '1',
                'value' => [
                    'from' => $request->get('from', getSessionFilter(config('pages.name'), 'from')),
                    'to' => $request->get('to', getSessionFilter(config('pages.name'), 'to')),
                ],
                'options' => [
                    'from' => ['label' => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ],
            [
                'type' => 'text',
                'name' => 'title',
                'row' => '1',
                'value' => $request->get('title', getSessionFilter(config('pages.name'), 'title')),
                'options' => ['placeholder' => trans('pages::pages.titles.title'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'row' => '1',
                'name' => 'status',
                'value' => $request->get('status', getSessionFilter(config('pages.name'), 'status')),
                'select_options' => $statusOptions,
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'date_range',
                'row' => '1',
                'name' => ['created_at_from', 'created_at_to'],
                'value' => [
                    'created_at_from' => $request->get('created_at_from', getSessionFilter(config('pages.name'), 'created_at_from')),
                    'created_at_to' => $request->get('created_at_to', getSessionFilter(config('pages.name'), 'created_at_to')),
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
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([config('pages.name')]))."'",
                        'class' => 'btn btn-secondary btn-fw',
                        'title' => trans('core::core.buttons.reset'),
                    ],
                ],
            ],
        ];

        return $fields;
    }

    public function pagination(Request $request)
    {
        $perPage = $request->get('per_page', settings('core', 'default_per_page'));
        $orderBy = getSessionFilter(config('pages.name'), 'order_by') ? getSessionFilter(config('pages.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('pages.name'), 'dir') ? getSessionFilter(config('pages.name'), 'dir') : $request->get('dir', 'desc');

        $collection = $this->filter($request);

        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config('pages.name'), $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config('pages.name'), 'page'));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();
        $pages = new Pages;
        $pagesTranslation = new PagesTranslation;

        $collection = $this->allWithBuilder();
        $collection->join($pagesTranslation->getTable().' AS pagesTranslation', $pages->getTable().'.id', '=', 'pagesTranslation.page_id')->where('pagesTranslation.locale', app()->getLocale())
            ->select($pages->getTable().'.*', 'pagesTranslation.title AS title');

        $whereCond = $request->get('from', getSessionFilter(config('pages.name'), 'from'));
        if ($whereCond !== null) {
            $collection->where($pages->getTable().'.id', '>=', $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config('pages.name'), 'to'));
        if ($whereCond !== null) {
            $collection->where($pages->getTable().'.id', '<=', $whereCond);
        }

        $whereCond = $request->get('title', getSessionFilter(config('pages.name'), 'title'));
        if ($request->get('title') !== null) {
            $title = $request->get('title');
            $collection->whereHas('translations', function ($query) use ($title) {
                $query->where('locale', app()->getLocale())->where('title', 'LIKE', "%{$title}%");
            })->with('translations');
        }

        $whereCond = $request->get('status', getSessionFilter(config('pages.name'), 'status'));
        if ($whereCond !== null) {
            $collection->where('status', $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config('pages.name'), 'created_at_from'));
        if ($whereCond !== null) {
            $collection->whereRaw('DATE('.$pages->getTable().".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date('Y-m-d', strtotime($whereCond)));
        }
        $whereCond = $request->get('created_at_to', getSessionFilter(config('pages.name'), 'created_at_to'));
        if ($whereCond !== null) {
            $collection->whereRaw('DATE('.$pages->getTable().".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date('Y-m-d', strtotime($whereCond)));
        }

        return $collection;
    }
}
