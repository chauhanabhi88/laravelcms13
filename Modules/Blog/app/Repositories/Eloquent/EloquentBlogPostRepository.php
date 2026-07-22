<?php

namespace Modules\Blog\Repositories\Eloquent;

use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Blog\Models\BlogPost;
use Modules\Blog\Models\BlogPostTranslation;

class EloquentBlogPostRepository extends EloquentBaseRepository implements BlogPostRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
			[
				"title" => trans("blog::blog_post.titles.image"),
				"column" => "image",
				"no_sort" => true
			],			
            [
				"title" => trans("blog::blog_post.titles.title"),
				"column" => "title",
                "no_sort" => true
			],

            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.blog_post.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config("blog.cache.blog_post_name"), "order_by") ? getSessionFilter(config("blog.cache.blog_post_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("blog.cache.blog_post_name"), "dir") ? getSessionFilter(config("blog.cache.blog_post_name"), "dir") : $request->get("dir", "desc");
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
                    "from" => $request->get("from", getSessionFilter(config("blog.cache.blog_post_name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("blog.cache.blog_post_name"), "to"))
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
			"value" => $request->get("title", getSessionFilter(config("blog.cache.blog_post_name"), "title")),
			"options" => ["placeholder" => trans("blog::blog_post.titles.title"), "class" => "form-control"]
			],

            [
                "type" => "select",
                "row"  => "1",
                "name" => "status",
                "value" => $request->get("status", getSessionFilter(config("blog.cache.blog_post_name"), "status")),
                "select_options" => $statusOptions,
                "options" => ["label" => trans('core::core.labels.status'), 'class' => 'custom-select']
            ],

           [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1",
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("blog.cache.blog_post_name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("blog.cache.blog_post_name"), "created_at_to"))
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("blog.cache.blog_post_name")]))."'",
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
        $orderBy = getSessionFilter(config("blog.cache.blog_post_name"), "order_by") ? getSessionFilter(config("blog.cache.blog_post_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("blog.cache.blog_post_name"), "dir") ? getSessionFilter(config("blog.cache.blog_post_name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("blog.cache.blog_post_name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("blog.cache.blog_post_name"), "page"));
    }

    public function filter($request)
    {
        $blog_post =  new BlogPost();

        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();

        $whereCond = $request->get('from', getSessionFilter(config("blog.cache.blog_post_name"), "from"));
        if ($whereCond !== null) {
            $collection->where($blog_post->getTable() . ".id", ">=", $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config("blog.cache.blog_post_name"), "to"));
        if($whereCond !== null) {
            $collection->where($blog_post->getTable() . ".id", "<=", $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config("blog.cache.blog_post_name"), "created_at_from"));
       if($whereCond !== null) {
            $collection->whereRaw("DATE(" . $blog_post->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $whereCond), 'Y-m-d'));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config("blog.cache.blog_post_name"), "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(" . $blog_post->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $whereCond), 'Y-m-d'));
        }
        
        $whereCond = $request->get('status', getSessionFilter(config("blog.cache.blog_post_name"), "status"));
        if ($whereCond !== null) {
            $collection->where("status", $whereCond);
        }

        $whereCond = $request->get('title', getSessionFilter(config("blog.cache.blog_post_name"), "title"));
        if($whereCond !== null) {
            $title = $whereCond;
                $collection->whereHas("translations", function ($query) use ($title) {
                    $query->where("title", "LIKE", "%{$title}%");
            })->with('translations');
        }

        return $collection;
    }

    public function getAllPostTitle($id = null, $flag = false) {
        $data = [];
        $blogPostTranslation = new BlogPostTranslation();
        $blogPostEntity = new BlogPost();
        $collection = $this->allWithBuilder();
        if ($id) {
            $collection->where($blogPostEntity->getTable()  . '.id', $id);
        }
        $data = $collection->join($blogPostTranslation->getTable() . ' AS blogPostTranslation', $blogPostEntity->getTable()  . '.id', '=', 'blogPostTranslation.blog_post_id')
            ->where('blogPostTranslation.locale', array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en')->pluck('title', $blogPostEntity->getTable().'.id')->toArray();
        if($flag) {
            $data[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }
        ksort($data);
        return $data;
    }
}
