<?php

namespace Modules\Menu\Repositories\Eloquent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Menu\Repositories\MenuRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Menu\Repositories\Repository;
use Modules\Menu\Models\Menu;

class EloquentMenuRepository extends EloquentBaseRepository implements MenuRepository
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
                "title" => trans("menu::menu.titles.title"),
                "column" => "title"
            ],
            [
                "title" => trans("menu::menu.titles.link"),
                "column" => "link"
            ],

            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if ($this->getAuthUser()->can("admin.menu.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
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
                "row"  => "1",
                "name" => "title",
                "value" => $request->get("title"),
                "options" => ["placeholder" => trans("menu::menu.titles.title"), "class" => "form-control"]
            ],
            [
                "type" => "text",
                "row"  => "1",
                "name" => "link",
                "value" => $request->get("link"),
                "options" => ["placeholder" => trans("menu::menu.titles.link"), "class" => "form-control"]
            ],

            [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1",
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
                "row"   => "3",
                "buttons" => [
                    "submit" => [
                        "name" => "search",
                        "type" => "submit",
                        "onclick" => "searchFilter(); return false;",
                        "class" => "btn btn-info btn-flat",
                        "title" => trans('core::core.buttons.search')
                    ],
                    "reset" => [
                        "name" => "reset",
                        "type" => "button",
                        "onclick" => "window.location.href= '" . route("admin.menu.index", updateUrlParams()) . "'",
                        "class" => "btn btn-secondary btn-flat",
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
        $orderBy = $request->get("order_by", "sort_order");
        $dir = $request->get("dir", "ASC");
        $collection = $this->allWithBuilder();
        // $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        return $collection->get();
        // return $collection->paginate($perPage);
    }

    public function buildMenu($menu,  $statusOptions, $parentId = 0)
    {
        $result = null;
        $deletebtn = null;
        foreach ($menu as $item)
            if ($item->parent_id == $parentId) {
                if ($item->is_system == config("core.disabled")) {
                    $deleteRoute = route('admin.menu.delete', updateUrlParams([$item->id]));
                    $deletebtn = " | <button type=\"button\" class=\"btn\" data-toggle=\"modal\" data-target=\"#modal-delete-confirmation\" data-action-target=\"{$deleteRoute}\"><span data-placement=\"right\" data-toggle=\"tooltip\"  title=\"" . trans('core::core.labels.delete') . "\"><i class=\"fas fa-trash\"></span></i></button>";
                }
                $editRoute = route('admin.menu.edit', updateUrlParams([$item->id]));
                $statusTitle = $statusOptions[$item->status];
                $checked = $item->status == 1 ? "checked" : "";
                $status = "| <span data-placement=\"right\" data-toggle=\"tooltip\"  title=\"$statusTitle\">
                                <label class=\"menu-switch\">
                                    <input type=\"checkbox\" class=\"status\" data-id=\"$item->id\" $checked>
                                    <span class=\"slider round\"></span>
                                </label>
                            </span>";
                $result .= "<li class='dd-item nested-list-item' data-order='{$item->sort_order}' data-id='{$item->id}'>
	      <div class='dd-handle nested-list-handle'>
	        <span class='fa fa-arrows'></span>
	      </div>
	      <div class='nested-list-content'>{$item->label}
	        <div class='float-right action-btns'>
            <button type=\"button\" onclick=\"setLocation('{$editRoute}');\" class=\"btn\"><span data-placement=\"right\" data-toggle=\"tooltip\"  title=\"" . trans('core::core.labels.edit') . "\"><i class=\"fas fa-edit\"></i></span></button>
            $deletebtn $status
	        </div>
	      </div>" . $this->buildMenu($menu,  $statusOptions, $item->id) . "</li>";
            }
        return $result ?  "\n<ol class=\"dd-list\">\n$result</ol>\n" : null;
    }

    protected function clean($string)
    {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.

        return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
    }


    public function checkChildsMenu($childMenu = [], $permissions = [])
    {
        $showMenu = call_user_func_array('array_merge', array_map(function ($child) {
            return array_column($child, "link");
        }, array_column($childMenu, 'child')));
        
        if (empty(array_intersect($permissions, $showMenu))) {
            return true;
        }
        return false;
    }

    protected function _getChildMenu($menu, $cnt)
    {
        $permissions = [];
        if (Auth::check()) {
            $user = Auth::user();
            $permissions = !empty($user->role) && !empty($user->role->permissions) ? json_decode($user->role->permissions) : [];
        }
        $result = null;
        if (!empty($permissions) && !empty($menu)) {
            foreach ($menu as $_menu) {

                $toggleStatus = "";
                $childMenu = $_menu->child;
                if (empty(trim($_menu->link)) || in_array(trim($_menu->link), $permissions) || (str_contains(trim($_menu->link), 'http') || str_contains(trim($_menu->link), 'https'))) {


                    $checkChildMenuPermissions = $this->checkChildsMenu($childMenu->toArray(), $permissions);

                    //removed !empty(array_filter(array_column($childMenu->toArray(), 'link'))) condition
                    if (empty(trim($_menu->link)) && $cnt == 0 && $childMenu->count() != 0  && empty(array_intersect($permissions, array_column($childMenu->toArray(), 'link'))) && $checkChildMenuPermissions) {
                        continue;
                    }

                    //removed !empty(array_filter(array_column($childMenu->toArray(), 'link'))) condition
                    if (empty(trim($_menu->link)) && $cnt != 0 && $childMenu->count() != 0 && !empty(array_filter(array_column($childMenu->toArray(), 'link'))) && empty(array_intersect($permissions, array_column($childMenu->toArray(), 'link'))) && $checkChildMenuPermissions) {
                        continue;
                    }
                    $toggleStatus = $cnt == 0 ? 'toggle-status=\"closed\"' : '';
                    $result .= "<li class=\"nav-item level-" . $cnt . " \" $toggleStatus menu-id = \"".$_menu->id."\">";

                    $linkTarget = $_menu->link_target == 1 ? "_blank" : "_self";

                    if (($childMenu->count()) && $cnt >= 1) {
                        $result .= "<p class=\"nav-link collapsed\" data-toggle=\"collapse\" href=\"#" . $this->clean(str_replace(" ", "-", $_menu->label)) . "\" aria-expanded=\"false\" aria-controls=\"" . str_replace(" ", "-", $_menu->label) . "\">";
                    } else if (!empty($_menu->link) && (str_contains(trim($_menu->link), 'http') || str_contains(trim($_menu->link), 'https'))) {
                        $result .= "<a class=\"nav-link\" href=\"" . trim($_menu->link) . "\" target=\"" . $linkTarget . "\">";
                    } else if (!empty($_menu->link)) {
                        $result .= "<a class=\"nav-link\" href=\"" . route(trim($_menu->link), updateUrlParams()) . "\" target=\"" . $linkTarget . "\">";
                    } else {
                        $result .= "<a class=\"nav-link\" href=\"javascript:void(0);\" onclick=\"return false\">";
                    }
                    if (!empty($_menu->link) && in_array('create', explode('.', trim($_menu->link)))) {
                        $result .= "<i class=\"menu-icon fa fa-plus fa-sm\"></i>";
                    } else if (isset($_menu->icon) && !empty($_menu->icon)) {
                        $result .= "<i class=\"menu-icon " . trim($_menu->icon) . "\"></i>";
                    } else if ($cnt >= 1) {
                        $result .= "<i class=\"menu-icon mdi mdi-folder-outline\"></i>";
                    }

                    // $result .= isset($_menu->icon) && !empty($_menu->icon) ? "<i class=\"".trim($_menu->icon)."\"></i>" : "";

                    // $result .= $cnt >= 1 ? "<span class=\"menu-title\">" : "<span class=\"menu-title\">";

                    $result .= "<span class=\"$_menu->css_class menu-title\">";

                    $result .= $_menu->label . "</span>";

                    $result .= ($childMenu->count()) && $cnt >= 1 ? "<i class=\"badge badge-pill ml-auto fa fa-arrow-right\"></i>" : "";

                    $result .= ($childMenu->count()) && $cnt >= 1 ? "</p>" : "</a>";

                    if (($childMenu->count()) && $cnt + 1 == 1) {
                        $result .= "<div class=\"submenu\"><div class=\"submenu-inner ps-enabled\"><div class=\"menu-head\"><div class=\"title\">" . $_menu->label . "</div></div><ul class=\"nav\">";
                    } elseif ($childMenu->count()) {
                        $result .= "<div class=\"collapse\" id=\"" . $this->clean(str_replace(" ", "-", $_menu->label)) . "\" style=\"\"><div class=\"\"><ul class=\"nav\">";
                    }
                    $result .= $this->_getChildMenu($childMenu, $cnt + 1);
                    $result .= ($childMenu->count()) ? "</ul></div></div>" : "";
                    $result .= "</li>";
                }
            }
        }
        return $result;
    }

    public function getResources($object = null)
    {
        $menu = new Menu();
        if (Schema::hasTable($menu->getTable())) {

            $collection = $this->allWithBuilder()->where('status', config('core.enabled'));
            if (empty($object)) {
                $collection = $collection->where('parent_id', 0);
            } else {
                $collection = $collection->where('parent_id', $object->id);
            }
            $collection->orderBy('sort_order', 'ASC');
            return $collection->with('child')->get();
        }
    }

    public function getMenu($roleId = null)
    {
        $menu = $this->getResources();

        return $this->_getChildMenu($menu, 0);
    }
}
