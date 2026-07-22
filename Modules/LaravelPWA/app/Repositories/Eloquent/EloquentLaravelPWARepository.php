<?php

namespace Modules\LaravelPWA\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\LaravelPWA\Repositories\LaravelPWARepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\LaravelPWA\Repositories\Repository;

class EloquentLaravelPWARepository extends EloquentBaseRepository implements LaravelPWARepository
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

        return $columns;
    }

    public function getFilters($request)
    {
        $fields =  [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "value" => [
                    "from" => $request->get("from"),
                    "to"   => $request->get("to")
                ],
                "options" => [
                    'from' => ['placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                'value' => [
                    "created_at_from" => $request->get("created_at_from"),
                    "created_at_to" => $request->get("created_at_to")
                ],
                'options' => [
                    "created_at_from" => ['placeholder' => trans('core::core.labels.from'), 'class' => 'form-control datepicker'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control datepicker']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "buttons" => [
                    "submit" => [
                        "name" => "search",
                        "type" => "submit",
                        "onclick" => "searchFilter();",
                        "class" => "btn btn-info btn-flat",
                        "title" => trans('core::core.buttons.search')
                    ],
                    "reset" => [
                        "name" => "reset",
                        "type" => "button",
                        "onclick" => "window.location.href= '".route("admin.laravelpwa.index")."'",
                        "class" => "btn btn-secondary btn-flat",
                        "title" => trans('core::core.buttons.reset')
                    ]
                ]
            ]
        ];


        if($this->getAuthUser()->can("admin.laravelpwa.mass_delete")) {
            $massDeleteCheckbox = [
                "type" => "massDelete"
            ];
            array_unshift($fields, $massDeleteCheckbox);
        }
        return $fields;
    }

    public function pagination($request)
    {
        $perPage = $request->get("per_page", settings("core", "default_per_page"));
        $orderBy = $request->get("order_by", "id");
        $dir = $request->get("dir", "DESC");

        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();
        if ($request->get('from') !== null) {
            $collection->where("id", ">=", $request->get('from'));
        }

        if($request->get('to') !== null) {
            $collection->where("id", "<=", $request->get('to'));
        }


       if($request->get("created_at_from") !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($request->get('created_at_from'))));
        }

        if($request->get("created_at_to") !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($request->get('created_at_to'))));
        }

        $collection->orderBy($orderBy, $dir);
        return $collection->paginate($perPage);
    }
}
