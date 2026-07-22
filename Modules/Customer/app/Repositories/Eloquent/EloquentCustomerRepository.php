<?php

namespace Modules\Customer\Repositories\Eloquent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Illuminate\Support\Facades\Auth;
use Modules\Customer\Models\Customer;

class EloquentCustomerRepository extends EloquentBaseRepository implements CustomerRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
                "title" => trans("customer::customer.titles.profile"),
                "column" => "profile_picture",
                "no_sort" => true
            ],
            [
                "title" => trans("customer::customer.titles.first_name"),
                "column" => "first_name"
            ],
            [
                "title" => trans("customer::customer.titles.email"),
                "column" => "email"
            ],
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if((settings('core', 'email_verification') == config('core.yes'))) {
            $emailVerify = [
                [
                    "title" => trans("customer::customer.titles.email_verified"),
                    "column" => "email_verified_at"
                ]
            ];
            array_splice($columns, 4, 0, $emailVerify);
        }

        if($this->getAuthUser()->can("admin.customer.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config("customer.cache.name"), "order_by") ? getSessionFilter(config("customer.cache.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("customer.cache.name"), "dir") ? getSessionFilter(config("customer.cache.name"), "dir") : $request->get("dir", "desc");
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
                    "from" => $request->get("from", getSessionFilter(config("customer.cache.name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("customer.cache.name"), "to"))
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
                "value" => $request->get("name", getSessionFilter(config("customer.cache.name"), "name")),
                "options" => ['placeholder' => trans('customer::customer.titles.first_name'), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "name" => "email",
                "row"  => "1",
                "value" => $request->get("email", getSessionFilter(config("customer.cache.name"), "email")),
                "options" => ['placeholder' => trans('customer::customer.titles.email'), 'class' => 'form-control']
            ],
            [
                "type" => "select",
                "row"  => "1",
                "name" => "status",
                "value" => $request->get("status", getSessionFilter(config("customer.cache.name"), "status")),
                "select_options" => $statusOptions,
                "options" => ["label"=>trans('core::core.labels.status'), 'class' => 'custom-select']
            ],
            [
                'type' => 'date_range',
                "row"  => "1",
                'name' => ["created_at_from", "created_at_to"],
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("customer.cache.name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("customer.cache.name"), "created_at_to"))
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("customer.cache.name")]))."'",
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
        $orderBy = getSessionFilter(config("customer.cache.name"), "order_by") ? getSessionFilter(config("customer.cache.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("customer.cache.name"), "dir") ? getSessionFilter(config("customer.cache.name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("customer.cache.name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("customer.cache.name"), "page"));
    }

    public function filter($request) {
        $timezoneOffset = getTimezoneOffset();
        $collection = $this->allWithBuilder();

        $whereCond = $request->get("from", getSessionFilter(config("customer.cache.name"), "from"));
        if ($whereCond !== null) {
            $collection->where("id", ">=", $whereCond);
        }

        $whereCond = $request->get("to", getSessionFilter(config("customer.cache.name"), "to"));
        if($whereCond !== null) {
            $collection->where("id", "<=", $whereCond);
        }

        $whereCond = $request->get("name", getSessionFilter(config("customer.cache.name"), "name"));
        if($whereCond !== null) {
            $collection->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'LIKE', '%' . $whereCond . '%');
        }

        $whereCond = $request->get("email", getSessionFilter(config("customer.cache.name"), "email"));
        if($whereCond !== null) {
            $collection->where("email", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("status", getSessionFilter(config("customer.cache.name"), "status"));
        if($whereCond !== null) {
            $collection->where("status", $whereCond);
        }

        $whereCond = $request->get("created_at_from", getSessionFilter(config("customer.cache.name"), "created_at_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("created_at_to", getSessionFilter(config("customer.cache.name"), "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }
        return $collection;
    }

    public function getLoginUserInfo() {
        if(auth()->guard('customers')->check()) {
            $id = Auth::guard('customers')->user()->id;
            $collection = $this->allWithBuilder();
            $data = $collection->find($id);
        }else{
            $data = array();
        }
        return $data;
    }

    public function getAllCustomerName($id = null, $flag = false) {
        $data = [];
        $customer = new Customer();
        $collection = $this->allWithBuilder();
        if ($id) {
            $collection->where('id', $id);
        }
        $data =  $collection->select("id", DB::raw("CONCAT(first_name, ' ', last_name) as name"))->pluck("name", 'id')->toArray();
        if($flag) {
            $data[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }
        ksort($data);
        return $data;
    }
}
