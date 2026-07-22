<?php

namespace Modules\Banner\Repositories\Eloquent;

use Modules\Banner\Repositories\BannerRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;  
use Modules\Banner\Models\Banner as BannerEntity;
use Modules\Banner\Models\BannerGroupTranslation;
use Modules\Banner\Models\BannerTranslation;

class EloquentBannerRepository extends EloquentBaseRepository implements BannerRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id"
            ],
            [
                "title" => trans("banner::banner.titles.image"),
                "column" => "image",
                "no_sort" => true
            ],
            [
                "title" => trans("banner::banner.titles.title"),
                "column" => "title"
            ],
            [
                "title" => trans("banner::banner.titles.banner_groups"),
                "column" => "banner_group_title"
            ],

            // [
            //     "title" => trans("banner::banner.titles.status"),
            //     "column" => "status"
            // ],
            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ],
        ];

        if ($this->getAuthUser()->can("admin.banner.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config("banner.cache.name"), "order_by") ? getSessionFilter(config("banner.cache.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("banner.cache.name"), "dir") ? getSessionFilter(config("banner.cache.name"), "dir") : $request->get("dir", "desc");
        $columns = $this->defaultSort($columns,$orderBy,$dir);
        return $columns;
    }

    public function getFilters($request, $statusOptions, $bannerGroups)
    {
        $fields =  [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "row"  => "1",
                "value" => [
                    "from" => $request->get("from", getSessionFilter(config("banner.cache.name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("banner.cache.name"), "from"))
                ],
                "options" => [
                    'from' => ["label" => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],

            [
                "type" => "text",
                "name" => "title",
                "row"  => "1",
                "value" => $request->get("title", getSessionFilter(config("banner.cache.name"), "title")),
                "options" => ['placeholder' => trans('banner::banner.titles.title'), 'class' => 'form-control']
            ],
            [
                "type" => "select",
                "name" => "group_id",
                "row"  => "1",
                "value" => $request->get("group_id", getSessionFilter(config("banner.cache.name"), "group_id")),
                "select_options" => $bannerGroups,
                "options" => ["label" => "Banner Group", 'class' => 'custom-select']
            ],
            [
                "type" => "select",
                "row"  => "1",
                "name" => "status",
                "value" => $request->get("status", getSessionFilter(config("banner.cache.name"), "status")),
                "select_options" => $statusOptions,
                "options" => ["label" => trans('core::core.labels.status'), 'class' => 'custom-select']
            ],
            // [
            //     "type" => "text",
            //     "name" => "url",
            //     "row"  => "1",
            //     "value" => $request->get("url"),
            //     "options" => ['placeholder' => trans('banner::banner.titles.url'), 'class' => 'form-control']
            // ],
            [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1",
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("banner.cache.name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("banner.cache.name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label" => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row"  => "3",
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
                        "onclick" => "window.location.href= '" . route("admin.reset_filter", updateUrlParams([config("banner.cache.name")])) . "'",
                        "class" => "btn btn-secondary btn-fw",
                        "title" => trans('core::core.buttons.reset')
                    ]
                ]
            ]
        ];


        // if($this->getAuthUser()->can("admin.banner.mass_delete")) {
        //     $massDeleteCheckbox = [
        //         "type" => "massDelete",
        //     ];
        //     array_unshift($fields, $massDeleteCheckbox);
        // }
        return $fields;
    }

    public function pagination($request)
    {
        $perPage = $request->get("per_page", settings("core", "default_per_page"));
        $orderBy = getSessionFilter(config("banner.cache.name"), "order_by") ? getSessionFilter(config("banner.cache.name"), "order_by") : $request->get("order_by", "id");
        $dir = getSessionFilter(config("banner.cache.name"), "dir") ? getSessionFilter(config("banner.cache.name"), "dir") : $request->get("dir", "desc");
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config("banner.cache.name"), $collection, $perPage);
        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config("banner.cache.name"), "page"));
    }

    public function filter($request)
    {
        $bannerTranslation = new BannerTranslation();
        $bannerEntity = new BannerEntity();
        $bannerGroupTranslation = new BannerGroupTranslation();
        $banner = $bannerEntity->getTable();
        $timezoneOffset = getTimezoneOffset();
        
        $collection = $this->allWithBuilder();

        $collection->join($bannerTranslation->getTable() . ' AS bannerTranslation', $bannerEntity->getTable()  . '.id', '=', 'bannerTranslation.banner_id')
            ->join($bannerGroupTranslation->getTable() . ' AS bannerGroupTranslation', 'banner.group_id', '=',  'bannerGroupTranslation.banner_group_id')
            ->where('bannerGroupTranslation.locale', array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en')
            ->where('bannerTranslation.locale', array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en')
            ->select($banner . '.*',  'bannerTranslation.title',  'bannerGroupTranslation.name as banner_group_title');
        
        $whereCond = $request->get('from', getSessionFilter(config("banner.cache.name"), "from"));
        if ($whereCond !== null) {
            $collection->where($banner . ".id", ">=", $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config("banner.cache.name"), "to"));
        if ($whereCond !== null) {
            $collection->where($banner . ".id", "<=", $whereCond);
        }

        $whereCond = $request->get('title', getSessionFilter(config("banner.cache.name"), "title"));
        if ($whereCond !== null) {
            $title = $whereCond;
            $collection->whereHas('translations', function ($query) use ($title) {
                $query->where('title', "LIKE", "%{$title}%");
            })->with('translations');
        }

        $whereCond = $request->get('group_id', getSessionFilter(config("banner.cache.name"), "group_id"));
        if ($whereCond !== null) {

            $group_id = $whereCond;
            $collection->where("group_id", $group_id);
        }

        $whereCond = $request->get('url', getSessionFilter(config("banner.cache.name"), "url"));
        if ($whereCond !== null) {
            $url = $whereCond;
            $collection->where("url", "LIKE", "%{$url}%");
        }

        $whereCond = $request->get('status', getSessionFilter(config("banner.cache.name"), "status"));
        if ($whereCond !== null) {
            $collection->where("status", $whereCond);
        }

        $whereCond = $request->get('sort_order', getSessionFilter(config("banner.cache.name"), "sort_order"));
        if ($whereCond !== null) {
            $collection->where("sort_order", $whereCond);
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config("banner.cache.name"), "created_at_from"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(" . $banner . ".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date("Y-m-d", strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config("banner.cache.name"), "created_at_to"));
        if ($whereCond !== null) {
            $collection->whereRaw("DATE(" . $banner . ".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date("Y-m-d", strtotime($whereCond)));
        }
        
        return $collection;
    }

    /* Get Banner Data By Code */
    public function getBannerByCode($code)
    {
        $locale = array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en';
        $collection = $this->model->where('code', $code)->where('status', \Config::get('banner.status.enable'));
        $collection->with(array('translations' => function ($query) use ($locale) {
            $query->where("locale", $locale);
        }));
        $bannerData = $collection->get()->first();
        if (isset($bannerData) && !empty($bannerData)) {
            return $bannerData->toArray();
        }
        return [];
    }

    /* Get Banner Data By Code */
    // public function getBannerByCode($code)
    // {
    //     $locale = array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en';
    //     $collection = $this->allWithBuilder()->where('code', $code)->where('status', \Config::get('banner.status.enable'));
    //     $collection->with(array('translations' => function ($query) use ($locale) {
    //         $query->where("locale", $locale);
    //     }));
    //     $bannerData = $collection->get();
    //     if($bannerData)
    //     {
    //         return $bannerData->toArray();
    //     }
    //     return [];
    // }


    /**
     * Get Banners By Group
     *
     * @param $locale
     * return $slides
     */
    // public function getBannersByGroup($code, $locale)
    // {
    //     $bannerGroup = app(BannerGroupRepository::class);

    //     $collection = $this->allWithBuilder()->where('country','=',NULL);
    //     $collection->with(array('translations' => function ($query) use ($locale) {
    //         $query->where("locale", $locale);
    //     }));

    //     $collection->with(array('bannerGroups' => function ($query) use ($code) {
    //         $query->where("code", $code);
    //     }));

    //     $slides = $collection->orderBy("sort_order", "ASC")->get();
    //     if($slides)
    //     {
    //         return $slides->toArray();
    //     }
    //     return [];
    // }
}
