<?php

namespace Modules\Customer\Repositories\Eloquent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Repositories\CustomerGroupRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Illuminate\Support\Facades\Auth;

class EloquentCustomerGroupRepository extends EloquentBaseRepository implements CustomerGroupRepository
{

    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
                "title" => trans("customer::customer_group.titles.name"),
                "column" => "name"
            ],
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.customer.group.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }

        $orderBy = getSessionFilter(config("customer.cache.customer_group_name"), "order_by") ? getSessionFilter(config("customer.cache.customer_group_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("customer.cache.customer_group_name"), "dir") ? getSessionFilter(config("customer.cache.customer_group_name"), "dir") : $request->get("dir", "desc");
        $columns = $this->defaultSort($columns,$orderBy,$dir);

        return $columns;
    }

    public function getFilters($request)
    {
        $fields =  [
            [
                "type" => "number_range",
                "row"  => "1",
                "name" => ["from", "to"],
                "value" => [
                    "from" => $request->get("from", getSessionFilter(config("customer.cache.customer_group_name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("customer.cache.customer_group_name"), "to"))
                ],
                "options" => [
                    'from' => ["label"=> trans('core::core.labels.id'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "name" => "name",
                "row"  => "1",
                "value" => $request->get("name", getSessionFilter(config("customer.cache.customer_group_name"), "name")),
                "options" => ['placeholder' => trans('customer::customer_group.titles.name'), 'class' => 'form-control']
            ],
            [
                'type' => 'date_range',
                "row"  => "1",
                'name' => ["created_at_from", "created_at_to"],
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("customer.cache.customer_group_name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("customer.cache.customer_group_name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label"=> trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row"   => '4',
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("customer.cache.customer_group_name")]))."'",
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
        $orderBy = getSessionFilter(config("customer.cache.customer_group_name"), "order_by") ? getSessionFilter(config("customer.cache.customer_group_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("customer.cache.customer_group_name"), "dir") ? getSessionFilter(config("customer.cache.customer_group_name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("customer.cache.customer_group_name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("customer.cache.customer_group_name"), "page"));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();
        $collection = $this->allWithBuilder();

        $whereCond = $request->get("from", getSessionFilter(config("customer.cache.customer_group_name"), "from"));
        if ($whereCond !== null) {
            $collection->where("id", ">=", $whereCond);
        }

        $whereCond = $request->get("to", getSessionFilter(config("customer.cache.customer_group_name"), "to"));
        if ($whereCond !== null) {
            $collection->where("id", "<=", $whereCond);
        }

        $whereCond = $request->get("name", getSessionFilter(config("customer.cache.customer_group_name"), "name"));
        if ($whereCond !== null) {
            $collection->where("name", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("created_at_from", getSessionFilter(config("customer.cache.customer_group_name"), "created_at_from"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("created_at_to", getSessionFilter(config("customer.cache.customer_group_name"), "created_at_to"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }
        return $collection;
    }
}
