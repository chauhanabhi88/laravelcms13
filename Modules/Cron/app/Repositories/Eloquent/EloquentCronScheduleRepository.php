<?php

namespace Modules\Cron\Repositories\Eloquent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Cron\Repositories\CronScheduleRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Cron\Models\CronSchedule;

class EloquentCronScheduleRepository extends EloquentBaseRepository implements CronScheduleRepository
{
    public function sortColumns($request,$isMassDelete = true)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
                "title" => trans("cron::cron_schedule.titles.title"),
                "column" => "title"
            ],
            [
                "title" => trans("cron::cron_schedule.titles.command"),
                "column" => "command"
            ],
            [
                "title" => trans("cron::cron_schedule.titles.message"),
                "column" => "message"
            ],
            [
                "title" => trans("cron::cron_schedule.titles.status"),
                "column" => "status"
            ],
            [
                "title" => trans("cron::cron_schedule.titles.execute_date"),
                "column" => "execute_date"
            ],
            [
                "title" => trans("cron::cron_schedule.titles.finished_date"),
                "column" => "finished_date"
            ],
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($isMassDelete) {
            if($this->getAuthUser()->can("admin.cron_schedule.mass_delete")) {
                $massDeleteCheckbox = [
                    "column" => "massDelete",
                    "type" => "massDelete",
                ];
                array_unshift($columns, $massDeleteCheckbox);
            }
        }
        $orderBy = getSessionFilter(config("cron.cache.entity_corn_schedule"), "order_by") ? getSessionFilter(config("cron.cache.entity_corn_schedule"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("cron.cache.entity_corn_schedule"), "dir") ? getSessionFilter(config("cron.cache.entity_corn_schedule"), "dir") : $request->get("dir", "desc");
        $columns = $this->defaultSort($columns,$orderBy,$dir);
        return $columns;
    }

    public function getFilters($request, $sessionKey = null)
    {
        $filterSessionKey = !empty($sessionKey) ? $sessionKey : config("cron.cache.entity_corn_schedule");
        $statusOptions = $this->getStatusOptions(true);
        $fields =  [
            [
                "type" => "number_range",
                "row"  => "1",
                "name" => ["from", "to"],
                "value" => [
                    "from" => $request->get("from", getSessionFilter($filterSessionKey, "from")),
                    "to"   => $request->get("to", getSessionFilter($filterSessionKey, "to"))
                ],
                "options" => [
                    'from' => ["label"=> trans('core::core.labels.id'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "title",
                "value" => $request->get("title", getSessionFilter($filterSessionKey, "title")),
                "options" => ['placeholder' => trans('cron::cron_schedule.titles.title'), 'class' => 'form-control']
            ],

            [
                "type" => "text",
                "row"  => "1",
                "name" => "command",
                "value" => $request->get("command", getSessionFilter($filterSessionKey, "command")),
                "options" => ['placeholder' => trans('cron::cron_schedule.titles.command'), 'class' => 'form-control']
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "message",
                "value" => $request->get("message", getSessionFilter($filterSessionKey, "message")),
                "options" => ['placeholder' => trans('cron::cron_schedule.titles.message'), 'class' => 'form-control']
            ],
            [
                "type" => "select",
                "row"  => "1",
                "name" => "status",
                "value" => $request->get("status", getSessionFilter($filterSessionKey, "status")),
                "select_options" => $statusOptions,
                "options" => ["label"=>trans('core::core.labels.status'), 'class' => 'custom-select']
            ],
            [
                'type' => 'date_range',
                "row"  => "1",
                'name' => ["execute_date_from", "execute_date_to"],
                'value' => [
                    "execute_date_from" => $request->get("execute_date_from", getSessionFilter($filterSessionKey, "execute_date_from")),
                    "execute_date_to" => $request->get("execute_date_to", getSessionFilter($filterSessionKey, "execute_date_to"))
                ],
                'options' => [
                    "execute_date_from" => ["label"=> trans("cron::cron_schedule.titles.execute_date"),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "execute_date_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "date_range",
                "row"  => "1",
                'name' => ["finished_date_from", "finished_date_to"],
                'value' => [
                    "finished_date_from" => $request->get("finished_date_from", getSessionFilter($filterSessionKey, "finished_date_from")),
                    "finished_date_to" => $request->get("finished_date_to", getSessionFilter($filterSessionKey, "finished_date_to"))
                ],
                'options' => [
                    "finished_date_from" => ["label"=> trans("cron::cron_schedule.titles.finished_date"),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "finished_date_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                'type' => 'date_range',
                "row"  => "1",
                'name' => ["created_at_from", "created_at_to"],
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter($filterSessionKey, "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter($filterSessionKey, "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label"=> trans('core::core.labels.created_on'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "row"  => "2",
                "class" => "col-action",
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter",updateUrlParams([config("cron.cache.entity_corn_schedule"), $filterSessionKey]))."'",
                        "class" => "btn btn-secondary btn-fw",
                        "title" => trans('core::core.buttons.reset')
                    ]
                ]
            ]
        ];

        return $fields;
    }

    public function pagination(Request $request, $sessionKey = null)
    {
        $filterSessionKey = !empty($sessionKey) ? $sessionKey : config("cron.cache.entity_corn_schedule");
        $perPage = $request->get("per_page", settings("core", "default_per_page"));
        $orderBy = getSessionFilter(config("cron.cache.entity_corn_schedule"), "order_by") ? getSessionFilter(config("cron.cache.entity_corn_schedule"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("cron.cache.entity_corn_schedule"), "dir") ? getSessionFilter(config("cron.cache.entity_corn_schedule"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request, $sessionKey);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage($filterSessionKey, $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($filterSessionKey, "page"));
    }

    public function filter($request, $sessionKey = null)
    {
        $filterSessionKey = !empty($sessionKey) ? $sessionKey : config("cron.cache.entity_corn_schedule");
        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();

        $cronId = $request->id; 
        if($cronId !== null) {
            $collection->where('cron_id', $cronId);
        }

        $whereCond = $request->get('from', getSessionFilter($filterSessionKey, "from"));
        if ($whereCond !== null) {
            $collection->where("id", ">=", $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter($filterSessionKey, "to"));
        if($whereCond !== null) {
            $collection->where("id", "<=", $whereCond);
        }

        $whereCond = $request->get('title', getSessionFilter($filterSessionKey, "title"));
        if($whereCond !== null) {
            $collection->where("title", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get('command', getSessionFilter($filterSessionKey, "command"));
        if($whereCond !== null) {
            $collection->where("command", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get('message', getSessionFilter($filterSessionKey, "message"));
        if($whereCond !== null) {
            $collection->where("message", "LIKE", "%{$whereCond}%");
        }

        $whereCond = $request->get('status', getSessionFilter($filterSessionKey, "status"));
        if($whereCond !== null) {
            $collection->where("status", $whereCond);
        }

        $whereCond = $request->get('execute_date_from', getSessionFilter($filterSessionKey, "execute_date_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(execute_date + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get('execute_date_to', getSessionFilter($filterSessionKey, "execute_date_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(execute_date + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get('finished_date_from', getSessionFilter($filterSessionKey, "finished_date_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(finished_date + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get('finished_date_to', getSessionFilter($filterSessionKey, "finished_date_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(finished_date + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }
        
        $whereCond = $request->get('created_at_from', getSessionFilter($filterSessionKey, "created_at_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter($filterSessionKey, "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }

        return $collection;
    }

    public function getStatusOptions($flag = false)
    {
        $options = [];
        if($flag) {
            $options[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
    	return $options + [
            config('cron.cron_schedule_status_pending') => trans('cron::cron_schedule.options.status.pending'),
            config('cron.cron_schedule_status_success') => trans('cron::cron_schedule.options.status.success'),
            config('cron.cron_schedule_status_fail') => trans('cron::cron_schedule.options.status.fail')
        ];
    }

}
