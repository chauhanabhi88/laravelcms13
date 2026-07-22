<?php

namespace Modules\Role\Repositories\Eloquent;

use Illuminate\Http\Request;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\Auth;
use Modules\Role\Repositories\RoleRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentRoleRepository extends EloquentBaseRepository implements RoleRepository
{
    public function getRoleOptions($flag = false)
    {
        $options = [];
        $user = Auth::user();
        $roleId = null;
        if (!empty($user)) {
            if (!empty($user->role)) {
                if ($user->role->slug != config('user.master_admin_slug')) {
                    $roleId = 1;
                }
            }
        }
        $options = $this->allWithBuilder()->pluck("name", "id")->all();
        if ($flag) {
            $options[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }
        if (!empty($roleId)) {
            if (array_key_exists($roleId, $options)) {
                unset($options[$roleId]);
            }
        }
        ksort($options);

        return $options;
    }

    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
                "title" => trans("role::role.titles.name"),
                "column" => "name"
            ],
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if ($this->getAuthUser()->can("admin.role.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config("role.name"), "order_by") ? getSessionFilter(config("role.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("role.name"), "dir") ? getSessionFilter(config("role.name"), "dir") : $request->get("dir", "desc");
        $columns = $this->defaultSort($columns,$orderBy,$dir);
        return $columns;
    }

    public function getFilters(Request $request)
    {
        $fields = [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "row"  => "1",
                "value" => [
                    "from" => $request->get("from", getSessionFilter(config("role.name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("role.name"), "to"))
                ],
                "options" => [
                    'from' => ["label" => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "name" => "name",
                "row"  => "1",
                "value" => $request->get("name", getSessionFilter(config("role.name"), "name")),
                "options" => ['placeholder' => trans('role::role.titles.name'), 'class' => 'form-control']
            ],
            [
                'type' => 'date_range',
                "row"  => "1",
                'name' => ["created_at_from", "created_at_to"],
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("role.name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("role.name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label" => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row"  => "2",
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
                        "onclick" => "window.location.href= '" . route("admin.reset_filter", updateUrlParams([config("role.name")])) . "'",
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
        $orderBy = getSessionFilter(config("role.name"), "order_by") ? getSessionFilter(config("role.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("role.name"), "dir") ? getSessionFilter(config("role.name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);

        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("role.name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("role.name"), "page")); 
    }


    public function filter($request)
    {
        
        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();

        $whereCond = $request->get("from", getSessionFilter(config("role.name"), "from"));
        if ($whereCond !== null) {
            $collection->where("id", ">=", $whereCond);
        }

        $whereCond = $request->get("to", getSessionFilter(config("role.name"), "to"));
        if ($whereCond !== null) {
            $collection->where("id", "<=", $whereCond);
        }

        $whereCond = $request->get("name", getSessionFilter(config("role.name"), "name"));
        if ($whereCond !== null) {
            // $name = $request->get('name');
            $collection->where("name", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get("created_at_from", getSessionFilter(config("role.name"), "created_at_from"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get("created_at_to", getSessionFilter(config("role.name"), "created_at_to"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }

        return $collection;
    }

    public function getModulePermissions()
    {
        $moduleList = Module::all();

        $permissions = [];
        foreach ($moduleList as $module) {
            $path = $module->getPath();
            if (file_exists($path . '/config/permissions.php')) {
                $permissions[] = require_once($path . '/config/permissions.php');
            }
        }
        return $permissions;
    }
}
