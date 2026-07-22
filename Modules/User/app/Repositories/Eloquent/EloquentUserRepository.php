<?php

namespace Modules\User\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\User\Repositories\UserRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentUserRepository extends EloquentBaseRepository implements UserRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
                "title" => trans("user::user.titles.name"),
                "column" => "name"
            ],
            [
                "title" => trans("user::user.titles.email"),
                "column" => "email"
            ],
            [
                "title" => trans("user::user.titles.status"),
                "column" => "status"
            ],
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.user.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }  

        $orderBy = getSessionFilter(config("user.cache.name"), "order_by") ? getSessionFilter(config("user.cache.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("user.cache.name"), "dir") ? getSessionFilter(config("user.cache.name"), "dir") : $request->get("dir", "desc");
        $columns = $this->defaultSort($columns,$orderBy,$dir);

        return $columns;
    }

    public function getFilters($request, $statusOptions)
    {
        $fields =  [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "row"  => "1",
                "value" => [
                    "from" => $request->get("from", getSessionFilter(config("user.cache.name"), "from")),
                    "to"   => $request->get("from", getSessionFilter(config("user.cache.name"), "from"))
                ],
                "options" => [
                    'from' => ["label"=> trans('core::core.labels.id'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "name",
                "value" => $request->get("name", getSessionFilter(config("user.cache.name"), "name")),
                "options" => ['placeholder' => trans('user::user.titles.name'), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "email",
                "value" => $request->get("email", getSessionFilter(config("user.cache.name"), "email")),
                "options" => ['placeholder' => trans('user::user.titles.email'), 'class' => 'form-control']
            ],
            [
                "type" => "select",
                "row"  => "1",
                "name" => "status",
                "value" => $request->get("status", getSessionFilter(config("user.cache.name"), "status")),
                "select_options" => $statusOptions,
                "options" => ["label"=>trans('core::core.labels.status'), 'class' => 'custom-select']
            ],
            [
                'type' => 'date_range',
                "row"  => "1",
                'name' => ["created_at_from", "created_at_to"],
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("user.cache.name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("user.cache.name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label"=> trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row"  => "3",
                "buttons" => [
                    "submit" => [
                        "name" => "search",
                        "type" => "submit",
                        "onclick" => "searchFilter(); return false;",
                        "class" => "btn btn-primary btn-fw",
                        "title" => trans('core::core.buttons.search')
                    ],
                    "reset" => [
                        "name" => "reset",
                        "type" => "button",
                        "onclick" => "window.location.href= '" . route("admin.reset_filter", updateUrlParams([config("user.cache.name")])) . "'",
                        "class" => "btn btn-secondary btn-fw",
                        "title" => trans('core::core.buttons.reset')
                    ]
                ]
            ]
        ];


        
        return $fields;
    }

    public function pagination(Request $request)
    {
        $perPage = $request->get("per_page", settings("core", "default_per_page"));
        
        $orderBy = getSessionFilter(config("user.cache.name"), "order_by") ? getSessionFilter(config("user.cache.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("user.cache.name"), "dir") ? getSessionFilter(config("user.cache.name"), "dir") : $request->get("dir", "desc");

        $collection = $this->filter($request);
        
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("user.cache.name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("user.cache.name"), "page")); 
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder()->with('role');

        $whereCond = $request->get("from", getSessionFilter(config("user.cache.name"), "from"));
        if ($whereCond !== null) {
            $collection->where("id", ">=", $whereCond);
        }

        $whereCond = $request->get("to", getSessionFilter(config("user.cache.name"), "to"));
        if($whereCond !== null) {
            $collection->where("id", "<=", $whereCond);
        }

        $whereCond = $request->get("name", getSessionFilter(config("user.cache.name"), "name"));
        if($whereCond !== null) {
            // $name = $request->get('name');
            $collection->where("name", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("email", getSessionFilter(config("user.cache.name"), "email"));
        if($whereCond !== null) {
            // $email = $request->get('email');
            $collection->where("email", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("status", getSessionFilter(config("user.cache.name"), "status"));
        if($whereCond !== null) {
            $collection->where("status", $whereCond);
        }

        $whereCond = $request->get("created_at_from", getSessionFilter(config("user.cache.name"), "created_at_from"));
        if($whereCond!== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("created_at_to", getSessionFilter(config("user.cache.name"), "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }

        return $collection;
    }
}
