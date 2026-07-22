<?php

namespace Modules\Blog\Repositories\Eloquent;

use Modules\Blog\Repositories\BlogPostCommentRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Blog\Models\BlogPostComment;
use Modules\Blog\Models\BlogPostTranslation;
use Modules\Customer\Models\Customer;
use Modules\User\Models\User;

class EloquentBlogPostCommentRepository extends EloquentBaseRepository implements BlogPostCommentRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
				"title" => trans("blog::blog_post_comment.titles.subject"),
				"column" => "subject"
			],
            [
				"title" => trans("blog::blog_post_comment.titles.blog_post"),
				"column" => "post_id"
			],
            [
				"title" => trans("blog::blog_post_comment.titles.customer_name"),
				"column" => "customer_id"
			],
            [
				"title" => trans("blog::blog_post_comment.titles.status"),
				"column" => "status"
			],
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];
        if($this->getAuthUser()->can("admin.blog_post_comment.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }

        $orderBy = getSessionFilter(config("blog.cache.blog_post_comment_name"), "order_by") ? getSessionFilter(config("blog.cache.blog_post_comment_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("blog.cache.blog_post_comment_name"), "dir") ? getSessionFilter(config("blog.cache.blog_post_comment_name"), "dir") : $request->get("dir", "desc");
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
                    "from" => $request->get("from", getSessionFilter(config("blog.cache.blog_post_comment_name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("blog.cache.blog_post_comment_name"), "to"))
                ],
                "options" => [
                    'from' => ["label"=> trans('core::core.labels.id'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "subject",
                "value" => $request->get("subject", getSessionFilter(config("blog.cache.blog_post_comment_name"), "subject")),
                "options" => ["placeholder" => trans("blog::blog_post_comment.titles.subject"), "class" => "form-control"]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "post_title",
                "value" => $request->get("post_title", getSessionFilter(config("blog.cache.blog_post_comment_name"), "post_title")),
                "options" => ["placeholder" => trans("blog::blog_post_comment.titles.blog_post"), "class" => "form-control"]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "customer_name",
                "value" => $request->get("customer_name", getSessionFilter(config("blog.cache.blog_post_comment_name"), "customer_name")),
                "options" => ["placeholder" => trans("blog::blog_post_comment.titles.customer_name"), "class" => "form-control"]
            ],
            [
                "type" => "select",
                "row"  => "1",
                "name" => "status",
                "value" => $request->get("status", getSessionFilter(config("blog.cache.blog_post_comment_name"), "status")),
                "select_options" => $statusOptions,
                "options" => ["label" => trans('core::core.labels.status'), 'class' => 'custom-select']
            ],
            [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1",
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("blog.cache.blog_post_comment_name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("blog.cache.blog_post_comment_name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label"=> trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("blog.cache.blog_post_comment_name")]))."'",
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
        $orderBy = getSessionFilter(config("blog.cache.blog_post_comment_name"), "order_by") ? getSessionFilter(config("blog.cache.blog_post_comment_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("blog.cache.blog_post_comment_name"), "dir") ? getSessionFilter(config("blog.cache.blog_post_comment_name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("blog.cache.blog_post_comment_name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("blog.cache.blog_post_comment_name"), "page"));
    }


    public function filter($request)
    {
        $blog_post =  new BlogPostTranslation();
        $blog_post_comment =  new BlogPostComment();
        $customer = new Customer();
        $user = new User();
        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder()
        ->join($customer->getTable() . " AS customer", $blog_post_comment->getTable()  . '.customer_id', '=', 'customer.id')
        ->join($blog_post->getTable() . " AS blogpost", $blog_post_comment->getTable()  . '.post_id', '=', 'blogpost.blog_post_id')
        ->where('blogpost.locale', array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en')
        ->select($blog_post_comment->getTable() . '.*',  'customer.first_name', 'customer.last_name', 'blogpost.title');

        $whereCond = $request->get('from', getSessionFilter(config("blog.cache.blog_post_comment_name"), "from"));
        if ($whereCond !== null) {
            $collection->where($blog_post_comment->getTable() . ".id", ">=", $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config("blog.cache.blog_post_comment_name"), "to"));
        if($whereCond !== null) {
            $collection->where($blog_post_comment->getTable() . ".id", "<=", $whereCond);
        }

        $whereCond = $request->get('status', getSessionFilter(config("blog.cache.blog_post_comment_name"), "status"));
        if ($whereCond !== null) {
            $collection->where($blog_post_comment->getTable()  . '.status', $whereCond);
        }

        $whereCond = $request->get('subject', getSessionFilter(config("blog.cache.blog_post_comment_name"), "subject"));
        if ($whereCond !== null) {
            $collection->where("subject", $whereCond);
        }

        $whereCond = $request->get('post_title', getSessionFilter(config("blog.cache.blog_post_comment_name"), "post_title"));
        if ($whereCond !== null) {
            $collection->where("title", $whereCond);
        }

        $whereCond = $request->get('customer_name', getSessionFilter(config("blog.cache.blog_post_comment_name"), "customer_name"));
        if($whereCond !== null) {
            $customer_name = $whereCond;
            $collection->where(\DB::raw("CONCAT(" .$customer->getTable().".first_name,' ',".$customer->getTable().".last_name)"), 'LIKE', '%' . $customer_name . '%');
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config("blog.cache.blog_post_comment_name"), "created_at_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(" . $blog_post_comment->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $whereCond), 'Y-m-d'));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config("blog.cache.blog_post_comment_name"), "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(" . $blog_post_comment->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $whereCond), 'Y-m-d'));
        }
        return $collection;
    }

    public function getCommentStatusOption($flag = false) {
        $commentStatus = [];
		if($flag) {
			$commentStatus[""] = ' -- ' . trans('core::core.labels.select') . ' -- ';
		}
		$commentStatus[config('blog.comment_status.pending')] = config('blog.comment_status_value.pending');
		$commentStatus[config('blog.comment_status.approved')] = config('blog.comment_status_value.approved');
		$commentStatus[config('blog.comment_status.rejected')] = config('blog.comment_status_value.rejected');

		return $commentStatus;
    }
}