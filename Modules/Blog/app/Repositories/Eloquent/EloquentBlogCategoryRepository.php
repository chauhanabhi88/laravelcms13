<?php

namespace Modules\Blog\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Blog\Repositories\BlogCategoryRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Blog\Models\BlogCategory;
use Modules\Blog\Models\BlogCategoryTranslation;


class EloquentBlogCategoryRepository extends EloquentBaseRepository implements BlogCategoryRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
			[
				"title" => trans("blog::blog_category.titles.title"),
				"column" => "title",
                "no_sort" => true
			],

            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.blog_category.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        
        $orderBy = getSessionFilter(config("blog.cache.blog_category_name"), "order_by") ? getSessionFilter(config("blog.cache.blog_category_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("blog.cache.blog_category_name"), "dir") ? getSessionFilter(config("blog.cache.blog_category_name"), "dir") : $request->get("dir", "desc");
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
                    "from" => $request->get("from", getSessionFilter(config("blog.cache.blog_category_name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("blog.cache.blog_category_name"), "to"))
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
			"value" => $request->get("title", getSessionFilter(config("blog.cache.blog_category_name"), "title")),
			"options" => ["placeholder" => trans("blog::blog_category.titles.title"), "class" => "form-control"]
			],

            [
                "type" => "select",
                "row"  => "1",
                "name" => "status",
                "value" => $request->get("status", getSessionFilter(config("blog.cache.blog_category_name"), "status")),
                "select_options" => $statusOptions,
                "options" => ["label" => trans('core::core.labels.status'), 'class' => 'custom-select']
            ],

           [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1",
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("blog.cache.blog_category_name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("blog.cache.blog_category_name"), "created_at_to"))
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
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("blog.cache.blog_category_name")]))."'",
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
        $orderBy = getSessionFilter(config("blog.cache.blog_category_name"), "order_by") ? getSessionFilter(config("blog.cache.blog_category_name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("blog.cache.blog_category_name"), "dir") ? getSessionFilter(config("blog.cache.blog_category_name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("blog.cache.blog_category_name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("blog.cache.blog_category_name"), "page"));
    }

    public function filter($request)
    {
        $blog_category =  new BlogCategory();

        $timezoneOffset = getTimezoneOffset();

        $collection = $this->allWithBuilder();

        $whereCond = $request->get('from', getSessionFilter(config("blog.cache.blog_category_name"), "from"));
        if ($whereCond !== null) {
            $collection->where($blog_category->getTable() . ".id", ">=", $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config("blog.cache.blog_category_name"), "to"));
        if($whereCond !== null) {
            $collection->where($blog_category->getTable() . ".id", "<=", $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config("blog.cache.blog_category_name"), "created_at_from"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(" . $blog_category->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $whereCond), 'Y-m-d'));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config("blog.cache.blog_category_name"), "created_at_to"));
        if($whereCond !== null) {
            $collection->whereRaw("DATE(" . $blog_category->getTable() . ".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date_format(date_create_from_format(config('core.encrypt.php_datepicker_format'), $whereCond), 'Y-m-d'));
        }

        $whereCond = $request->get('status', getSessionFilter(config("blog.cache.blog_category_name"), "status"));
        if ($whereCond !== null) {
            $collection->where("status", $whereCond);
        }

        $whereCond = $request->get('title', getSessionFilter(config("blog.cache.blog_category_name"), "title"));
        if($whereCond !== null) {
        $title = $whereCond;
            $collection->whereHas("translations", function ($query) use ($title) {
                $query->where("title", "LIKE", "%{$title}%");
        })->with('translations');
        }

        return $collection;
    }

    public function getAllBlogCategory($flag = false) {
        $blogCategory = [];
        $blogCategoryTranslation = new BlogCategoryTranslation();
        $blogCategoryEntity = new BlogCategory();
        $blogCategory = $this->allWithBuilder()->join($blogCategoryTranslation->getTable() . ' AS blogCategoryTranslation', $blogCategoryEntity->getTable()  . '.id', '=', 'blogCategoryTranslation.blog_category_id')
            ->where('blogCategoryTranslation.locale', array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en')->pluck('title', $blogCategoryEntity->getTable().'.id')->all();
        if($flag) {
            $blogCategory[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }
        ksort($blogCategory);
        return $blogCategory;
    }

}
