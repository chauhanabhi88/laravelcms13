<?php

namespace Modules\Contact\Repositories\Eloquent;

use Modules\Contact\Repositories\ContactRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentContactRepository extends EloquentBaseRepository implements ContactRepository
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
                "title" => trans("contact::contact.labels.customer_name"),
                "column" => "name",
                "default_sort" => false,
            ],
            [
                "title" => trans("contact::contact.labels.email"),
                "column" => "email",
                "default_sort" => false,
            ],
            [
                "title" => trans("contact::contact.labels.contact_number"),
                "column" => "contact_number",
                "default_sort" => false,
            ],
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.contact.mass_delete")) {
            $massDeleteCheckbox = [
                "column"    =>  "massDelete",
                "type" => "massDelete"
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }

        return $columns;
    }

    public function getFilters($request)
    { 
        $fields =  [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "row"   => 1,
                "value" => [
                    "from" => $request->get("from", getSessionFilter(config("contact.name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("contact.name"), "to"))
                ],
                "options" => [
                    'from' => ["label" => trans('core::core.labels.id'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "name" => "name",
                "row"   => 1,
                "value" => $request->get("name", getSessionFilter(config("contact.name"), "name")),
                "options" => ['placeholder' => trans("contact::contact.labels.customer_name"), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "name" => "email",
                "row"   =>  1,
                "value" => $request->get("email", getSessionFilter(config("contact.name"), "email")),
                "options" => ['placeholder' => trans("contact::contact.labels.email"), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "name" => "contact_number",
                "row"   => 1,
                "value" => $request->get("contact_number", getSessionFilter(config("contact.name"), "contact_number")),
                "options" => ['placeholder' => trans("contact::contact.labels.contact_number"), 'class' => 'form-control']
            ],
            [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"   =>  2,
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("contact.name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("contact.name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label" => trans('core::core.labels.created_on'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control datepicker'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control datepicker']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row"   => 3,
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("contact.name")]))."'",
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
        $orderBy = $request->get("order_by", "id");
        $dir = $request->get("dir", "DESC");
        $collection = $this->filter($request);
        if(is_null($perPage)) {
            $perPage = \config('core.defaultPerPage');
        }
        updateSessionFilterPage(config("contact.name"), $collection, $perPage);
        $collection->orderBy($orderBy, $dir);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("contact.name"), "page"));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();

        $whereCond = $request->get('from', getSessionFilter(config("contact.name"), "from"));
        if ($whereCond !== null) {
            $collection->where("id", ">=", $whereCond);
        }

        $whereCond = $request->get('name', getSessionFilter(config("contact.name"), "name"));
        if($whereCond !== null) {
            $name = $whereCond;
            $collection->where("name", "LIKE", "%{$name}%");
        }

        $whereCond = $request->get('email', getSessionFilter(config("contact.name"), "email"));
        if($whereCond !== null) {
            $email = $whereCond;
            $collection->where("email", "LIKE", "%{$email}%");
        }

        $whereCond = $request->get('contact_number', getSessionFilter(config("contact.name"), "contact_number"));
        if($whereCond !== null) {
            $contact_number = $whereCond;
            $collection->where("contact_number", "LIKE", "%{$contact_number}%");
        }

        $whereCond = $request->get('to', getSessionFilter(config("contact.name"), "to"));
        if($whereCond !== null) {
            $collection->where("id", "<=", $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config("contact.name"), "created_at_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config("contact.name"), "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }

        return $collection;
    }

    public function export($request)
    {
        $orderBy = $request->get("order_by", "id");
        $dir = $request->get("dir", "DESC");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        $array = $collection->get();
        $data = [];
        if (count($array) > 0) {
            foreach ($array as $contact) {
                $data[] = [
                    'created_at'        =>  getFormatedDate($contact->created_at, getGridDateFormat()),
                    'contact_number'    =>  $contact->contact_number,
                    'name'              =>  $contact->name,
                    'email'             =>  $contact->email
                ];
            }
        }
        return $data;
    }
}
