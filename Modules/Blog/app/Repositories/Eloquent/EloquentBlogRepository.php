<?php

namespace Modules\Blog\Repositories\Eloquent;

use Modules\Blog\Repositories\BlogRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Blog\Models\Blog;

class EloquentBlogRepository extends EloquentBaseRepository implements BlogRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],

            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.blog.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config("blog.cache.name"), "order_by") ? getSessionFilter(config("blog.cache.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("blog.cache.name"), "dir") ? getSessionFilter(config("blog.cache.name"), "dir") : $request->get("dir", "desc");
        $columns = $this->defaultSort($columns,$orderBy,$dir);
        return $columns;
    }

    public function getFilters($request)
    {
        $fields =  [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "row"  => "1",
                "value" => [
                    "from" => $request->get("from"),
                    "to"   => $request->get("to")
                ],
                "options" => [
                    'from' => ["label"=> trans('core::core.labels.id'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],

           [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1",
                'value' => [
                    "created_at_from" => $request->get("created_at_from"),
                    "created_at_to" => $request->get("created_at_to")
                ],
                'options' => [
                    "created_at_from" => ["label"=> trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row"   => "3",
                "buttons" => [
                    "submit" => [
                        "name" => "search",
                        "type" => "submit",
                        "onclick" => "searchFilter(); return false;",
                        "class" => "btn btn-info btn-flat",
                        "title" => trans('core::core.buttons.search')
                    ],
                    "reset" => [
                        "name" => "reset",
                        "type" => "button",
                        "onclick" => "window.location.href= '".route("admin.blog.index", updateUrlParams())."'",
                        "class" => "btn btn-secondary btn-flat",
                        "title" => trans('core::core.buttons.reset')
                    ]
                ]
            ]
        ];



        return $fields;
    }

    public function pagination($request)
    {
        $perPage = $request->get("per_page", settings("core", "default_per_page"));
        $orderBy = getSessionFilter(config("blog.cache.name"), "order_by") ? getSessionFilter(config("blog.cache.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("blog.cache.name"), "dir") ? getSessionFilter(config("blog.cache.name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
		$collection->orderBy($orderBy, $dir);
        return $collection->paginate($perPage);
    }

    public function filter($request)
    {
        $blog =  new Blog();

        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();
        if ($request->get('from') !== null) {
            $collection->where($blog->getTable() . ".id", ">=", $request->get('from'));
        }

        if($request->get('to') !== null) {
            $collection->where($blog->getTable() . ".id", "<=", $request->get('to'));
        }

       if($request->get("created_at_from") !== null) {
            $collection->whereRaw("DATE(" . $blog->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $request->get("created_at_from")), 'Y-m-d'));
        }

        if($request->get("created_at_to") !== null) {
            $collection->whereRaw("DATE(" . $blog->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $request->get("created_at_to")), 'Y-m-d'));
        }
        return $collection;
    }
}
