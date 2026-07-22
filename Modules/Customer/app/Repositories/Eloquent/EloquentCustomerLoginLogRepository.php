<?php

namespace Modules\Customer\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use Modules\Customer\Repositories\CustomerLoginLogRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Customer\Models\CustomerLoginLog;
use Modules\Customer\Models\Customer;

class EloquentCustomerLoginLogRepository extends EloquentBaseRepository implements CustomerLoginLogRepository
{

    protected $_param = [];

    public function setParam(array $param)
    {
        if ($param) {
            $this->_param = $param;
            $this->generateLog();
        }
    }
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("customer::customer.titles.customer_id"),
                "column" => "customer_id"
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
                "title" => trans("customer::customer.titles.last_login_date"),
                "column" => "last_login_date"
            ]
        ];

        $orderBy = getSessionFilter(config("customer.cache.customer_login_log"), "order_by") ? getSessionFilter(config("customer.cache.customer_login_log"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("customer.cache.customer_login_log"), "dir") ? getSessionFilter(config("customer.cache.customer_login_log"), "dir") : $request->get("dir", "desc");
        $columns = $this->defaultSort($columns, $orderBy, $dir);

        return $columns;
    }

    public function getFilters($request)
    {
        $fields = [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "row" => "1",
                "value" => [
                    "from" => $request->get("from", getSessionFilter(config("customer.cache.customer_login_log"), "from")),
                    "to" => $request->get("to", getSessionFilter(config("customer.cache.customer_login_log"), "to"))
                ],
                "options" => [
                    'from' => ["label" => trans('customer::customer.titles.customer_id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "name" => "name",
                "row" => "1",
                "value" => $request->get("name", getSessionFilter(config("customer.cache.customer_login_log"), "name")),
                "options" => ['placeholder' => trans('customer::customer.titles.first_name'), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "name" => "email",
                "row" => "1",
                "value" => $request->get("email", getSessionFilter(config("customer.cache.customer_login_log"), "email")),
                "options" => ['placeholder' => trans('customer::customer.titles.email'), 'class' => 'form-control']
            ],
            [
                'type' => 'date_range',
                "row" => "1",
                'name' => ["last_login_date_from", "last_login_date_to"],
                'value' => [
                    "last_login_date_from" => $request->get("last_login_date_from", getSessionFilter(config("customer.cache.customer_login_log"), "last_login_date_from")),
                    "last_login_date_to" => $request->get("last_login_date_to", getSessionFilter(config("customer.cache.customer_login_log"), "last_login_date_to"))
                ],
                'options' => [
                    "last_login_date_from" => ["label" => trans('customer::customer.titles.last_login_date'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "last_login_date_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row" => '4',
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
                        "onclick" => "window.location.href= '" . route("admin.reset_filter", updateUrlParams([config("customer.cache.customer_login_log")])) . "'",
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

        $dir = getSessionFilter(config("customer.cache.customer_login_log"), "dir") ? getSessionFilter(config("customer.cache.customer_login_log"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy('customer_id', $dir);
        updateSessionFilterPage(config("customer.cache.customer_login_log"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("customer.cache.customer_login_log"), "page"));
    }

    public function filter($request)
    {
        $collection = $this->allWithBuilder();
        $timezoneOffset = getTimezoneOffset();
        $customerLoginLog = new CustomerLoginLog();
        $customerLoginLogTable = $customerLoginLog->getTable();

        $customer = new Customer();
        $customerTable = $customer->getTable();

        $collection = $collection->where($customerLoginLogTable . '.action', config('customer.customerLoginLogArr.login.action'))
            ->join($customerTable . ' AS customer', $customerLoginLogTable . '.customer_id', '=', 'customer.id')
            ->groupBy($customerLoginLogTable . '.customer_id', 'customer.first_name', 'customer.last_name', 'customer.email')
            ->select(DB::raw('count(' . $customerLoginLogTable . '.id) as login_count'), DB::raw('max(' . $customerLoginLogTable . '.created_at) as last_login_date'), 'customer.first_name', 'customer.last_name', 'customer.email', $customerLoginLogTable . '.customer_id');

        $whereCond = $request->get("from", getSessionFilter(config("customer.cache.customer_login_log"), "from"));
        if ($whereCond !== null) {
            $collection->where("customer_id", ">=", $whereCond);
        }

        $whereCond = $request->get("to", getSessionFilter(config("customer.cache.customer_login_log"), "to"));
        if ($whereCond !== null) {
            $collection->where("customer_id", "<=", $whereCond);
        }

        $whereCond = $request->get("name", getSessionFilter(config("customer.cache.customer_login_log"), "name"));
        if ($whereCond !== null) {
            $collection->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'LIKE', '%' . $whereCond . '%');
        }

        $whereCond = $request->get("email", getSessionFilter(config("customer.cache.customer_login_log"), "email"));
        if ($whereCond !== null) {
            $collection->where("email", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("last_login_date_from", getSessionFilter(config("customer.cache.customer_login_log"), "last_login_date_from"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(" . $customerLoginLogTable . ".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("last_login_date_to", getSessionFilter(config("customer.cache.customer_login_log"), "last_login_date_to"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(" . $customerLoginLogTable . ".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }
        //dd($collection->toSql());
        return $collection;
    }

    public function export($request)
    {
        $orderBy = getSessionFilter(config("customer.cache.customer_login_log"), "order_by") ? getSessionFilter(config("customer.cache.customer_login_log"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("customer.cache.customer_login_log"), "dir") ? getSessionFilter(config("customer.cache.customer_login_log"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy('customer_id', $dir);
        $array = $collection->get();
        $data = [];
        if (count($array) > 0) {
            foreach ($array as $loginDetail) {
                $data[] = [
                    'customer_id' => $loginDetail->customer_id,
                    'customer_name' => $loginDetail->first_name . " " . $loginDetail->last_name,
                    'email' => $loginDetail->email,
                    'last_login_date' => getFormatedDate($loginDetail->last_login_date, getGridDateFormat())
                ];
            }
        }
        return $data;
    }

    public function inActivePreviousSession($data)
    {
        $this->where("customer_id", $data['customer_id'])->update(['is_loggedin' => config("core.no")]);
    }

    public function generateLog()
    {
        if ($this->_param) {
            $loginLog = $this->where("customer_id", $this->_param['customer_id'])
                ->where("is_loggedin", config("core.no"))
                ->orderBy("id", "DESC")->first();
            if ($loginLog) {
                $this->_param['last_login_at'] = $loginLog->created_date;
            } else {
                $this->_param['last_login_at'] = date('Y-m-d H:i:s');
            }
            $this->create($this->_param);
        }
    }
}
