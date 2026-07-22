<?php

namespace Modules\Menu\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Menu\Models\Menu;
use Modules\Menu\Repositories\MenuRepository;
use Modules\Menu\Http\Requests\CreateRequest;
use Modules\Menu\Http\Requests\UpdateRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Role\Repositories\RoleRepository;
use Nwidart\Modules\Facades\Module;

class MenuController extends BackendController
{
    /**
     * @var MenuRepository
     */
    private $menu;

    /**
     * @var UserEntity
     */
    private $menuEntity;

    public function __construct(MenuRepository $menuRepo, Menu $menu, RoleRepository $role)
    {
        parent::__construct();
        $this->role = $role;
        $this->menu = $menuRepo;
        $this->menuEntity = $menu;
    }

    public function requireAssets()
    {
        $this->getAssetManager()->addAsset('modules/menu/js/nestable.js');
        $this->getAssetManager()->addAsset('modules/menu/css/nestable.css');
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule('Menu', $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $this->requireAssets();
            // $columns = $this->menu->sortColumns();
            $collection = $this->menu->pagination($request);
            $statusOptions = $this->menu->getStatusOptions(true);
            $menu = $this->menu->buildMenu($collection, $statusOptions,0);
            // $filters = $this->menu->getFilters($request);

            return view('menu::backend.index', compact('menu'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function postIndex(Request $request)
    {
        try {
            $params = $request->all();
            
            $source       = $params['source'];
            $destination  = !empty($params['destination']) ? $params['destination'] : 0;
            $item = $this->menu->find($source);
            $this->menu->update($item, ['parent_id' => $destination]);

            $ordering       = json_decode($params['sort_order']);
            $rootOrdering   = json_decode($params['rootOrder']);

            if (isset($ordering) && !empty($ordering)) {
                foreach ($ordering as $order => $item_id) {
                    if ($itemToOrder = $this->menu->find($item_id)) {
                        $this->menu->update($itemToOrder, ['sort_order' => $order]);
                    }
                }
            } else {
                if (isset($rootOrdering) && !empty($rootOrdering)) {
                    foreach ($rootOrdering as $order => $item_id) {
                        if ($itemToOrder = $this->menu->find($item_id)) {
                            $this->menu->update($itemToOrder, ['sort_order' => $order]);
                        }
                    }
                }
            }
            $request = new Request();
            $request->merge(['message' => trans("menu::menu.messages.updated_success")]);
            return $this->filters($request);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule('Menu', $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $this->requireAssets();
            // $columns = $this->menu->sortColumns();
            // $filters = $this->menu->getFilters($request);
            $statusOptions = $this->menu->getStatusOptions(true);
            $collection = $this->menu->pagination($request);
            $menu = $this->menu->buildMenu($collection, $statusOptions,0);

            $content = view('menu::backend.partials.menu', compact('menu'));
            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'nestable',
                    'html' => $content->__toString()
                ],
                'message' => $request->get('message'),
                // 'redirectUrl' =>  route('admin.menu.index', updateUrlParams()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        try {
            $permissions = $this->role->getModulePermissions();
            return view('menu::backend.create', compact('permissions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.menu.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        try {
            $params = $request->all();
            $params["menu"]["link_target"] = (!empty($params["menu"]["link_target"])) ? "1" : "2";
            $params["menu"]["is_system"] = (!empty($params["menu"]["is_system"])) ? "1" : "2";
            $params["menu"]["status"] = (!empty($params["menu"]["status"])) ? "1" : "2";
            if(!empty($params["menu"]["custom_link"])) {
                $params["menu"]["link"] = $params["menu"]["text_link"];
            } else {
                $params["menu"]["link"] = $params["menu"]["dropdown_link"];
            }
            $menu = $this->menu->create($params['menu']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.menu.edit', updateUrlParams([$menu->id]))->with("success", trans("menu::menu.messages.created_success"));
            }
            return redirect()->route('admin.menu.index', updateUrlParams())->with("success", trans("menu::menu.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.menu.create', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("menu::menu.messages.data_invalid"));
            }
            $menu = $this->menu->find($id);
            if (!$menu) {
                throw new \Exception(trans("menu::menu.messages.data_invalid"));
            }
            $permissions = $this->role->getModulePermissions();

            $temp=[];
            foreach ($permissions as $key => $modules){
                foreach($modules as $modulePermission){
                    foreach($modulePermission as $value => $label){
                        if((in_array('index', explode('.', $value)) || in_array('create', explode('.', $value)))){
                            $temp[$key][]=trans($value)."-".trans($label);
                        }
                    }
                }
            }

            return view('menu::backend.edit', compact('menu', 'permissions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.menu.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(UpdateRequest $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("menu::menu.messages.data_invalid"));
            }
            $params = $request->all();
            $menu = $this->menu->find($id);
            if (!$menu) {
                throw new \Exception(trans("menu::menu.messages.data_invalid"));
            }
            $params["menu"]["link_target"] = (!empty($params["menu"]["link_target"])) ? "1" : "2";
            $params["menu"]["is_system"] = (!empty($params["menu"]["is_system"])) ? "1" : "2";
            $params["menu"]["status"] = (!empty($params["menu"]["status"])) ? "1" : "2";
            if(!empty($params["menu"]["custom_link"])) {
                $params["menu"]["link"] = $params["menu"]["text_link"];
            } else {
                $params["menu"]["link"] = $params["menu"]["dropdown_link"];
            }
            $this->menu->update($menu, $params['menu']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.menu.edit', updateUrlParams([$id]))->with("success", trans("menu::menu.messages.updated_success"));
            }
            return redirect()->route('admin.menu.index', updateUrlParams())->with("success", trans("menu::menu.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.menu.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->id;

            if (!$id) {
                throw new \Exception(trans("menu::menu.messages.data_invalid"));
            }
            $menu = $this->menu->find($id);
            if (!$menu) {
                throw new \Exception(trans("menu::menu.messages.data_invalid"));
            }
            if(isset($request->deleteAllChild) && !empty($request->deleteAllChild)) {
                $this->menu->where('parent_id', $menu->id)->delete();
            } else {
                $this->menu->where('parent_id', $menu->id)->update(['parent_id' => 0]);
            }
            $this->menu->destroy($menu);
            return redirect()->route('admin.menu.index', updateUrlParams())->with("success", trans("menu::menu.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.menu.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->menu->destroyMultiple($request);
            return redirect()->route('admin.menu.index', updateUrlParams())->with("success", trans("menu::menu.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.menu.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function updateStatus(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $status = $request->get('status');
            $menuRow = $this->menu->find($id);
            $status = ($status == 1) ? config('core.enabled') : config('core.disabled');
            $params = array('status' => $status);
            $this->menu->update($menuRow, $params);
        }
        $request = new Request();
        $request->merge(['message' => trans("menu::menu.messages.status_change_success")]);
        return $this->filters($request);
    }


}
