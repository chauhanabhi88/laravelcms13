<?php

namespace Modules\Block\Repositories\Eloquent;

use Modules\Block\Models\Block;
use Modules\Block\Models\BlockTranslation;
use Modules\Block\Repositories\BlockRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentBlockRepository extends EloquentBaseRepository implements BlockRepository
{
    public function getFilters($request, $languageOptions, $statusOptions)
    {
        $fields = [
            [
                'type' => 'number_range',
                'name' => ['from', 'to'],
                'row' => '1',
                'value' => [
                    'from' => $request->get('from', getSessionFilter(config('block.name'), 'from')),
                    'to' => $request->get('to', getSessionFilter(config('block.name'), 'to')),
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
                'value' => $request->get('title', getSessionFilter(config('block.name'), 'title')),
                'options' => ['placeholder' => trans('block::block.titles.title'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'row' => '1',
                'name' => 'is_enabled',
                'value' => $request->get('is_enabled', getSessionFilter(config('block.name'), 'is_enabled')),
                'select_options' => $statusOptions,
                'options' => ['label' => trans('block::block.titles.is_enabled'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'date_range',
                'row' => '1',
                'name' => ['created_at_from', 'created_at_to'],
                'value' => [
                    'created_at_from' => $request->get('created_at_from', getSessionFilter(config('block.name'), 'created_at_from')),
                    'created_at_to' => $request->get('created_at_to', getSessionFilter(config('block.name'), 'created_at_to')),
                ],
                'options' => [
                    'created_at_from' => ['label' => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'created_at_to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ],
            [
                'type' => 'action',
                'row' => '3',
                'class' => 'col-action',
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
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([config('block.name')]))."'",
                        'class' => 'btn btn-secondary btn-fw',
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
        $orderBy = getSessionFilter(config('block.name'), 'order_by') ? getSessionFilter(config('block.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('block.name'), 'dir') ? getSessionFilter(config('block.name'), 'dir') : $request->get('dir', 'desc');
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config('block.name'), $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config('block.name'), 'page'));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();

        $block = new Block;
        $blockTrans = new BlockTranslation;

        $collection = $this->allWithBuilder();
        $collection->join($blockTrans->getTable().' AS blockTranslation', $block->getTable().'.id', '=', 'blockTranslation.block_id')->where('blockTranslation.locale', app()->getLocale())
            ->select($block->getTable().'.*', 'blockTranslation.title AS title');

        $whereCond = $request->get('from', getSessionFilter(config('block.name'), 'from'));
        if ($whereCond !== null) {
            $collection->where($block->getTable().'.id', '>=', $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config('block.name'), 'to'));
        if ($whereCond !== null) {
            $collection->where($block->getTable().'.id', '<=', $whereCond);
        }

        $whereCond = $request->get('title', getSessionFilter(config('block.name'), 'title'));
        if ($whereCond !== null) {
            $title = $whereCond;
            $collection->whereHas('translations', function ($query) use ($title) {
                $query->where('title', 'LIKE', "%{$title}%");
            })->with('translations');
        }

        $whereCond = $request->get('slug', getSessionFilter(config('block.name'), 'slug'));
        if ($whereCond !== null) {
            $slug = $whereCond;
            $collection->where('slug', 'LIKE', "%{$slug}%");
        }

        $whereCond = $request->get('is_enabled', getSessionFilter(config('block.name'), 'is_enabled'));
        if ($whereCond !== null) {
            $collection->where('is_enabled', $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config('block.name'), 'created_at_from'));
        if ($whereCond !== null) {
            $collection->whereRaw('DATE('.$block->getTable().".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date('Y-m-d', strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config('block.name'), 'created_at_to'));
        if ($whereCond !== null) {
            $collection->whereRaw('DATE('.$block->getTable().".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date('Y-m-d', strtotime($whereCond)));
        }

        return $collection;
    }

    /**
     * Get Block Content
     *
     * @param  $locale
     *                 return content
     */
    public function getBlockContent($slug, $locale)
    {
        $collection = $this->allWithBuilder();
        $collection->where('slug', $slug);
        $collection->with(['translations' => function ($query) use ($locale) {
            $query->where('locale', $locale);
        }]);
        $collection->where('is_enabled', config('core.yes'));

        return $collection->first();
    }
}
