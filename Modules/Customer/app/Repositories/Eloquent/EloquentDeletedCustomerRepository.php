<?php

namespace Modules\Customer\Repositories\Eloquent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Repositories\DeletedCustomerRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Illuminate\Support\Facades\Auth;
use Modules\Customer\Models\Customer;
class EloquentDeletedCustomerRepository extends EloquentBaseRepository implements DeletedCustomerRepository
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
                "title" => trans("customer::customer.titles.customer_name"),
                "column" => "first_name"
            ],
            [
                "title" => trans("customer::customer.titles.email"),
                "column" => "email"
            ],
            
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ], 
            [
                "title" => trans("customer::customer.titles.deleated_at"),
                "column" => "deleted_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.customer.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }

        $orderBy = getSessionFilter(config("customer.cache.deleted_customer_name"), "order_by") ? getSessionFilter(config("customer.cache.deleted_customer_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("customer.cache.deleted_customer_name"), "dir") ? getSessionFilter(config("customer.cache.deleted_customer_name"), "dir") : $request->get("dir", "desc");
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
                    "from" => $request->get("from", getSessionFilter(config("customer.cache.deleted_customer_name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("customer.cache.deleted_customer_name"), "to"))
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
                "value" => $request->get("name", getSessionFilter(config("customer.cache.deleted_customer_name"), "name")),
                "options" => ['placeholder' => trans('customer::customer.titles.first_name'), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "name" => "email",
                "row"  => "1",
                "value" => $request->get("email", getSessionFilter(config("customer.cache.deleted_customer_name"), "email")),
                "options" => ['placeholder' => trans('customer::customer.titles.email'), 'class' => 'form-control']
            ],
            [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1", 
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("customer.cache.deleted_customer_name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("customer.cache.deleted_customer_name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label"=> trans('core::core.labels.created_on'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ], 
            [
                'type' => 'date_range',
                'name' => ["deleted_at_from", "deleted_at_to"],
                "row"  => "1",
                'value' => [
                    "deleted_at_from" => $request->get("deleted_at_from", getSessionFilter(config("customer.cache.deleted_customer_name"), "deleted_at_from")),
                    "deleted_at_to" => $request->get("deleted_at_to", getSessionFilter(config("customer.cache.deleted_customer_name"), "deleted_at_to"))
                ],
                'options' => [
                    "deleted_at_from" => ["label"=> "Deleted at",'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "deleted_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row"   => '3',
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("customer.cache.deleted_customer_name")]))."'",
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
        $orderBy = getSessionFilter(config("customer.cache.deleted_customer_name"), "order_by") ? getSessionFilter(config("customer.cache.deleted_customer_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("customer.cache.deleted_customer_name"), "dir") ? getSessionFilter(config("customer.cache.deleted_customer_name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("customer.cache.deleted_customer_name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("customer.cache.deleted_customer_name"), "page"));
        
    }


    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();
        $collection = Customer::onlyTrashed();
        
        $whereCond = $request->get("from", getSessionFilter(config("customer.cache.deleted_customer_name"), "from"));
        if ($whereCond !== null) {
            $collection->where("id", ">=", $whereCond);
        }

        $whereCond = $request->get("to", getSessionFilter(config("customer.cache.deleted_customer_name"), "to"));
        if($whereCond !== null) {
            $collection->where("id", "<=", $whereCond);
        }

        $whereCond = $request->get("name", getSessionFilter(config("customer.cache.deleted_customer_name"), "name"));
        if($whereCond !== null) {
            $collection->where("first_name", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("email", getSessionFilter(config("customer.cache.deleted_customer_name"), "email"));
        if($whereCond !== null) {
            $collection->where("email", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("status", getSessionFilter(config("customer.cache.deleted_customer_name"), "status"));
        if($whereCond !== null) {
            $collection->where("status", $whereCond);
        }

        $whereCond = $request->get("created_at_from", getSessionFilter(config("customer.cache.deleted_customer_name"), "created_at_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("created_at_to", getSessionFilter(config("customer.cache.deleted_customer_name"), "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }
        
        return $collection;
    }

    public function getLoginUserInfo() {
        if(auth()->guard('customer')->check()) {
            $id = Auth::guard('customer')->user()->id;
            $collection = $this->allWithBuilder();
            $data = $collection->find($id);
        }else{
            $data = array();
        }
        return $data;
    }
    public function onlyTrashedData($id) {
        if(isset($id) && !empty($id)) {
            $customerModel = new Customer();
            $customerRow = $customerModel->onlyTrashed()->where('id',$id)->get()->first();
        }
        return $customerRow;
    }

    
}