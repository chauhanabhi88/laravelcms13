<?php

namespace Modules\User\Repositories\Eloquent;

use Modules\User\Models\User;
use Modules\User\Repositories\DeletedUserRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentDeletedUserRepository extends EloquentBaseRepository implements DeletedUserRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
                "title" => trans("user::deleted_user.titles.name"),
                "column" => "name"
            ],
            [
                "title" => trans("user::deleted_user.titles.email"),
                "column" => "email"
            ],

            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ],
            [
                "title" => trans("user::deleted_user.titles.deleted_at"),
                "column" => "deleted_at"
            ]
        ];

        if ($this->getAuthUser()->can("admin.deleted_user.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config("user.cache.deleted_user_name"), "order_by") ? getSessionFilter(config("user.cache.deleted_user_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("user.cache.deleted_user_name"), "dir") ? getSessionFilter(config("user.cache.deleted_user_name"), "dir") : $request->get("dir", "desc");
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
                    "from" => $request->get("from", getSessionFilter(config("user.cache.deleted_user_name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("user.cache.deleted_user_name"), "to"))
                ],
                "options" => [
                    'from' => ["label" => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "name",
                "value" => $request->get("name", getSessionFilter(config("user.cache.deleted_user_name"), "name")),
                "options" => ["placeholder" => trans("user::deleted_user.titles.name"), "class" => "form-control"]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "email",
                "value" => $request->get("email", getSessionFilter(config("user.cache.deleted_user_name"), "email")),
                "options" => ["placeholder" => trans("user::deleted_user.titles.email"), "class" => "form-control"]
            ],

            [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1",
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("user.cache.deleted_user_name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("user.cache.deleted_user_name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label" => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                'type' => 'date_range',
                'name' => ["deleted_at_from", "deleted_at_to"],
                "row"  => "1",
                'value' => [
                    "deleted_at_from" => $request->get("deleted_at_from", getSessionFilter(config("user.cache.deleted_user_name"), "deleted_at_from")),
                    "deleted_at_to" => $request->get("deleted_at_to", getSessionFilter(config("user.cache.deleted_user_name"), "deleted_at_to"))
                ],
                'options' => [
                    "deleted_at_from" => ["label" => "Deleted at", 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "deleted_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
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
                        "class" => "btn btn-primary btn-fw",
                        "title" => trans('core::core.buttons.search')
                    ],
                    "reset" => [
                        "name" => "reset",
                        "type" => "button",
                        "onclick" => "window.location.href= '" . route("admin.reset_filter", updateUrlParams([config("user.cache.deleted_user_name")])) . "'",
                        "class" => "btn btn-secondary btn-fw",
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
        $orderBy = getSessionFilter(config("user.cache.deleted_user_name"), "order_by") ? getSessionFilter(config("user.cache.deleted_user_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("user.cache.deleted_user_name"), "dir") ? getSessionFilter(config("user.cache.deleted_user_name"), "dir") : $request->get("dir", "desc");

        $collection = $this->filter($request);
       

        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("user.cache.deleted_user_name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("user.cache.deleted_user_name"), "page")); 
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();

        $collection = User::onlyTrashed();

        $whereCond = $request->get("from", getSessionFilter(config("user.cache.deleted_user_name"), "from"));
        if ($whereCond !== null) {
            $collection->where("id", ">=", $whereCond);
        }

        $whereCond = $request->get("to", getSessionFilter(config("user.cache.deleted_user_name"), "to"));
        if ($whereCond !== null) {
            $collection->where("id", "<=", $whereCond);
        }

        $whereCond = $request->get("name", getSessionFilter(config("user.cache.deleted_user_name"), "name"));
        if ($whereCond !== null) {
            $collection->where("name", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("email", getSessionFilter(config("user.cache.deleted_user_name"), "email"));
        if ($whereCond !== null) {
            // $email = $request->get("email");
            $collection->where("email", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("created_at_from", getSessionFilter(config("user.cache.deleted_user_name"), "created_at_from"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("created_at_to", getSessionFilter(config("user.cache.deleted_user_name"), "created_at_to"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("deleted_at_from", getSessionFilter(config("user.cache.deleted_user_name"), "deleted_at_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(deleted_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("deleted_at_to", getSessionFilter(config("user.cache.deleted_user_name"), "deleted_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(deleted_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }

        return $collection;
    }
}
