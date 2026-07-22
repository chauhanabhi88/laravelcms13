<?php

namespace Modules\Mail\Repositories\Eloquent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Mail\Repositories\MailLogRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentMailLogRepository extends EloquentBaseRepository implements MailLogRepository
{

    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
                "title" => trans("mail::mail_log.titles.from"),
                "column" => "from_email"
            ],
           
            [
                "title" => trans("mail::mail_log.titles.to"),
                "column" => "to_email"
            ],
            [
                "title" => trans("mail::mail_log.titles.subject"),
                "column" => "subject"
            ],
            [
                "title" => trans("mail::mail_log.titles.mailed_at"),
                "column" => "created_at"
            ]
        ];
        $orderBy = getSessionFilter(config("mail.cache.mail_log"), "order_by") ? getSessionFilter(config("mail.cache.mail_log"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("mail.cache.mail_log"), "dir") ? getSessionFilter(config("mail.cache.mail_log"), "dir") : $request->get("dir", "desc");
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
                    "from" => $request->get("from"),
                    "to"   => $request->get("to")
                ],
                "options" => [
                    'from' => ["label" => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "name" => "from_email",
                "row" => '1',
                "value" => $request->get("from_email"),
                "options" => ['placeholder' => trans('mail::mail_log.titles.from'), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "name" => "to_email",
                "row" => '1',
                "value" => $request->get("to_email"),
                "options" => ['placeholder' => trans('mail::mail_log.titles.to'), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "name" => "subject",
                "row" => '1',
                "value" => $request->get("subject"),
                "options" => ['placeholder' => trans('mail::mail_log.titles.subject'), 'class' => 'form-control']
            ],
            [
                'type' => 'date_range',
                "row"  => "1",
                'name' => ["created_at_from", "created_at_to"],
                'value' => [
                    "created_at_from" => $request->get("created_at_from"),
                    "created_at_to" => $request->get("created_at_to")
                ],
                'options' => [
                    "created_at_from" => ["label" => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row" => "3",
                "buttons" => [
                    "submit" => [
                        "name" => "search",
                        "type" => "submit",
                        "onclick" => "searchFilter(); return false;",
                        "class" => "btn btn-primary btn-fw",
                        "icon_class" => "fa fa-search",
                        "title" => trans('core::core.buttons.search')
                    ],
                    "reset" => [
                        "name" => "reset",
                        "type" => "button",
                        "onclick" => "window.location.href= '" . route("admin.mail_log.index", updateUrlParams()) . "'",
                        "class" => "btn btn-secondary btn-fw",
                        "icon_class" => "fa fa-refresh",
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
        $orderBy = getSessionFilter(config("mail.cache.mail_log"), "order_by") ? getSessionFilter(config("mail.cache.mail_log"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("mail.cache.mail_log"), "dir") ? getSessionFilter(config("mail.cache.mail_log"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        return $collection->paginate($perPage);
    } 

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();
        $collection = $this->allWithBuilder();
        if ($request->get('from') !== null) {
            $collection->where("id", ">=", $request->get('from'));
        }

        if ($request->get('to') !== null) {
            $collection->where("id", "<=", $request->get('to'));
        }

        if ($request->get("from_email") !== null) {
            $fromEmail = $request->get('from_email');
            $collection->where("from_email", "LIKE", "%{$fromEmail}%");
        }

        if ($request->get("to_email") !== null) {
            $toEmail = $request->get('to_email');
            $collection->where("to_email", "LIKE", "%{$toEmail}%");
        }

        if ($request->get("subject") !== null) {
            $subject = $request->get('subject');
            $collection->where("subject", "LIKE", "%{$subject}%");
        }

        if ($request->get("created_at_from") !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($request->get('created_at_from'))));
        }

        if ($request->get("created_at_to") !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($request->get('created_at_to'))));
        }
        return $collection;
    }       

}
