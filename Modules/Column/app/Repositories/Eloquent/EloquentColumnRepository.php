<?php

namespace Modules\Column\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Column\Repositories\ColumnRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Column\Models\Column;
use Modules\Menu\Models\Menu;

class EloquentColumnRepository extends EloquentBaseRepository implements ColumnRepository
{
    public function sortColumns()
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id",
                "default_sort" => true,
            ],

            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.column.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }

        return $columns;
    }

    public function getFilters($request,$yesNoOptions,$menuOptions)
    {
        $fields =  [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "row"  => "1",
                "value" => [
                    "from" => $request->get("from", getSessionFilter(config("column.cache.name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("column.cache.name"), "to"))
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
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("column.cache.name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("column.cache.name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label"=> trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "name",
                "value" => $request->get("name", getSessionFilter(config("column.cache.name"), "name")),
                "options" => ['placeholder' => trans('column::column.labels.name'), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "row"  => "2",
                "name" => "code",
                "value" => $request->get("code", getSessionFilter(config("column.cache.code"), "code")),
                "options" => ['placeholder' => trans('column::column.labels.code'), 'class' => 'form-control']
            ],
            [
                "type" => "select",
                "row"  => "2",
                "name" => "is_sortable",
                "value" => $request->get("is_sortable", getSessionFilter(config("column.cache.is_sortable"), "is_sortable")),
                "select_options" => $yesNoOptions,
                "options" => ['label' => trans('column::column.labels.is_sortable'), 'class' => 'custom-select']
            ],
            [
                "type" => "select",
                "row"  => "2",
                "name" => "is_default",
                "value" => $request->get("is_default", getSessionFilter(config("column.cache.is_default"), "is_default")),
                "select_options" => $yesNoOptions,
                "options" => ['label' => trans('column::column.labels.is_default'), 'class' => 'custom-select']
            ],
            [
                "type" => "select",
                "row"  => "2",
                "name" => "menu",
                "value" => $request->get("menu", getSessionFilter(config("column.cache.menu"), "menu")),
                "select_options" => $menuOptions,
                "options" => ['label' => trans('column::column.labels.menu'), 'class' => 'custom-select']
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("column.cache.name")]))."'",
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
        $orderBy = $request->get("order_by", "id");
        $dir = $request->get("dir", "DESC");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("column.cache.name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("column.cache.name"), "page"));
    }

    public function filter($request)
    {
        $column =  new Column();
        $menu = new Menu();
        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder()
        ->join($menu->getTable() . " AS menu", $column->getTable()  . '.menu_id', '=', 'menu.id')
        ->select($column->getTable() . '.*',  'menu.label as menu');

        $whereCond = $request->get("from", getSessionFilter(config("column.cache.name"), "from"));
        if ($whereCond !== null) {
            $collection->where($column->getTable() . ".id", ">=", $whereCond);
        }

        $whereCond = $request->get("to", getSessionFilter(config("column.cache.name"), "to"));
        if($whereCond !== null) {
            $collection->where($column->getTable() . ".id", "<=", $whereCond);
        }

        $whereCond = $request->get("created_at_from", getSessionFilter(config("column.cache.name"), "created_at_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(" . $column->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $whereCond), 'Y-m-d'));
        }

        $whereCond = $request->get("created_at_to", getSessionFilter(config("column.cache.name"), "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(" . $column->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $whereCond), 'Y-m-d'));
        }

        $whereCond = $request->get("name", getSessionFilter(config("column.cache.name"), "name"));
        if($whereCond !== null) {
            $collection->where("name", 'LIKE', '%' . $whereCond . '%');
        }

        $whereCond = $request->get("code", getSessionFilter(config("column.cache.code"), "code"));
        if($whereCond !== null) {
            $collection->where("code", 'LIKE', '%' . $whereCond . '%');
        }

        $whereCond = $request->get("is_sortable", getSessionFilter(config("column.cache.is_sortable"), "is_sortable"));
        if($whereCond !== null) {
            $collection->where("is_sortable", $whereCond);
        }
        $whereCond = $request->get("is_default", getSessionFilter(config("column.cache.is_default"), "is_default"));
        if($whereCond !== null) {
            $collection->where("is_default", $whereCond);
        }

        $whereCond = $request->get("menu", getSessionFilter(config("column.cache.menu"), "menu"));
        if($whereCond !== null) {
            $collection->where("menu.id", $whereCond);
        }

		return $collection;
    }

    function getMenuOptions() {
        $options = Menu::pluck('label','id')->toArray();
        $options = ['' => '-- Select --'] + $options;
        return $options;
    }
}
