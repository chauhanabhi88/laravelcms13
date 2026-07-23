<?php

namespace Modules\Role\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Role\Http\Requests\UpsertRequest;
use Modules\Role\Repositories\RoleRepository;

class RoleController extends BackendController
{
    /**
     * @var RoleRepository
     */
    private $role;

    public function __construct(RoleRepository $role)
    {
        parent::__construct();

        $this->role = $role;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {

            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule('role', $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            $collection = $this->role->pagination($request);
            $filters = $this->role->getFilters($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('role::backend.index', compact('request', 'collection', 'columns', 'filters', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
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
                $perPage = getPerPageForModule('role', $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config('role.name'), $request);
            $filters = $this->role->getFilters($request);
            $collection = $this->role->pagination($request);
            $activeMenuId = getActiveMenuId($request, 'admin.role.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('role::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        try {

            $this->getAssetManager()->addAsset('modules/theme/backend/js/jquery.slug.js');
            $this->getAssetManager()->addAsset('modules/theme/backend/bootstrap/bootstrap-switch.min.js');

            $permissions = $this->role->getModulePermissions();

            return view('role::backend.create', compact('permissions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(UpsertRequest $request)
    {
        try {
            $params = $request->all();
            $params['role']['permissions'] = json_encode($request->input('permissions', []));
            $role = $this->role->create($params['role']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.role.edit', updateUrlParams([$role->id]))->with('success', trans('role::role.messages.created_success'));
            }

            return redirect()->route('admin.role.index', updateUrlParams())->with('success', trans('role::role.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.role.create', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('role::role.messages.data_invalid'));
            }
            $this->getAssetManager()->addAsset('modules/theme/backend/js/jquery.slug.js');
            $this->getAssetManager()->addAsset('modules/theme/backend/bootstrap/bootstrap-switch.min.js');

            $role = $this->role->find($id);
            if (! $role) {
                throw new \Exception(trans('role::role.messages.data_invalid'));
            }
            $permissions = $this->role->getModulePermissions();

            return view('role::backend.edit', compact('role', 'permissions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.role.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(UpsertRequest $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('role::role.messages.data_invalid'));
            }
            $params = $request->all();
            $params['role']['permissions'] = json_encode($request->input('permissions', []));

            $role = $this->role->find($id);
            if (! $role) {
                throw new \Exception(trans('role::role.messages.data_invalid'));
            }

            $this->role->update($role, $params['role']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.role.edit', updateUrlParams([$id]))->with('success', trans('role::role.messages.updated_success'));
            }

            return redirect()->route('admin.role.index', updateUrlParams())->with('success', trans('role::role.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.role.edit', updateUrlParams([$id]))->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            $role = $this->role->find($request->id);
            if ($role && $role->slug == config('role.master_admin_slug')) {
                throw new \Exception(trans('role::role.messages.master_admin_protected'));
            }
            $this->role->deleteRecord($request);

            return redirect()->route('admin.role.index', updateUrlParams())->with('success', trans('role::role.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.role.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $masterAdmin = $this->role->findBySlug(config('role.master_admin_slug'));
            $notDeleteIds = $masterAdmin ? [$masterAdmin->id] : [];
            $this->role->destroyMultiple($request, false, $notDeleteIds);

            return redirect()->route('admin.role.index', updateUrlParams())->with('success', trans('role::role.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.role.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }
}
