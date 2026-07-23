<?php

namespace Modules\User\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Role\Repositories\RoleRepository;
use Modules\User\Http\Requests\CreateRequest;
use Modules\User\Http\Requests\UpdateRequest;
use Modules\User\Models\User;
use Modules\User\Repositories\UserRepository;

class UserController extends BackendController
{
    /**
     * @var UserRepository
     */
    private $user;

    /**
     * @var UserEntity
     */
    private $userEntity;

    /**
     * @var RoleRepository
     */
    private $role;

    public function __construct(UserRepository $user, User $userEntity, RoleRepository $role)
    {
        parent::__construct();

        $this->user = $user;
        $this->userEntity = $userEntity;
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
                $perPage = getPerPageForModule(config('user.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }

            $statusOptions = $this->user->getStatusOptions(true);

            $collection = $this->user->pagination($request);
            $filters = $this->user->getFilters($request, $statusOptions);
            // $columns = $this->user->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('user::backend.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Filter sorting of records
     *
     * @return Response
     */
    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('user.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config('user.cache.name'), $request);
            $statusOptions = $this->user->getStatusOptions(true);
            $filters = $this->user->getFilters($request, $statusOptions);
            $collection = $this->user->pagination($request);
            // $columns = $this->user->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.user.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('user::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));

            return response()->json([
                // 'type' => 'success',
                'type' => $request->get('type', 'success'),
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString(),
                ],
                'message' => $request->get('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show the form for creating a new admin.
     *
     * @return Response
     */
    public function create(RoleRepository $role)
    {
        try {
            $roleOptions = $role->getRoleOptions(true);
            $statusOptions = $this->user->getStatusOptions(true);

            return view('user::backend.create', compact('statusOptions', 'roleOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.user.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Created new admin.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        try {
            $params = $request->all();
            if (isset($params['password']) && $params['password']) {
                $params['user']['password'] = Hash::make($params['password']);
            }

            $params['user']['status'] = (isset($params['user']['status'])) ? config('core.enabled') : config('core.disabled');

            $user = $this->user->create($params['user']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.user.edit', updateUrlParams([$user->id]))->with('success', trans('user::user.messages.created_success'));
            }

            return redirect()->route('admin.user.index', updateUrlParams())->with('success', trans('user::user.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.user.create', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for creating a edit admin.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, RoleRepository $roleRepo)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('user::user.messages.data_invalid'));
            }
            $user = $this->user->find($id);
            if (! $user) {
                throw new \Exception(trans('user::user.messages.data_invalid'));
            }

            $masterAdminRole = $roleRepo->where('slug', \Config::get('role.master_admin_slug'))->first();
            $masterAdmins = $this->user->where('status', config('core.yes'))->where('role_id', $masterAdminRole->id)->get();
            $masterAdminCount = count($masterAdmins);

            $role = $roleRepo->find($user->role_id);
            $roleOptions = $roleRepo->getRoleOptions(true);
            $statusOptions = $this->user->getStatusOptions();

            return view('user::backend.edit', compact('user', 'roleOptions', 'statusOptions', 'role', 'masterAdminCount'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.user.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for edit admin profile.
     *
     * @param  int  $id
     * @return Response
     */
    public function editProfile(RoleRepository $roleRepo)
    {
        try {
            $user = Auth::user();
            if (! $user) {
                throw new \Exception(trans('user::user.messages.data_invalid'));
            }
            $role = $roleRepo->find($user->role_id);
            $roleOptions = $roleRepo->getRoleOptions(true);
            $statusOptions = $this->user->getStatusOptions(true);

            return view('user::backend.edit-profile', compact('user', 'roleOptions', 'statusOptions', 'role'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified admin.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(UpdateRequest $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('user::user.messages.data_invalid'));
            }
            $params = $request->all();
            if (isset($params['password']) && $params['password']) {
                $params['user']['password'] = Hash::make($params['password']);
            }
            $currentUserId = Auth::user()->id;
            $user = $this->user->find($id);
            if (! $user) {
                throw new \Exception(trans('user::user.messages.data_invalid'));
            }

            if ($currentUserId == $id) {
                if (isset($params['user']['status'])) {
                    unset($params['user']['status']);
                }
            } else {
                $params['user']['status'] = (isset($params['user']['status'])) ? config('core.enabled') : config('core.disabled');
            }

            $this->user->update($user, $params['user']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.user.edit', updateUrlParams([$id]))->with('success', trans('user::user.messages.updated_success'));
            }

            return redirect()->route('admin.user.index', updateUrlParams())->with('success', trans('user::user.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.user.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified admin.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            $this->user->deleteRecord($request);
            $this->user->flushCache(config('user.cache.deleted_user_name'));

            return redirect()->route('admin.user.index', updateUrlParams())->with('success', trans('user::user.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.user.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->user->destroyMultiple($request);
            $this->user->flushCache(config('user.cache.deleted_user_name'));

            return redirect()->route('admin.user.index', updateUrlParams())->with('success', trans('user::user.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.user.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $status = $request->get('status');
            $userRow = $this->user->find($id);
            $status = ($status == config('core.yes')) ? config('core.yes') : config('core.no');
            $params = ['status' => $status];

            $masterAdminRole = $this->role->where('slug', \Config::get('role.master_admin_slug'))->first();
            $masterAdmins = $this->user->where('status', config('core.yes'))->where('role_id', $masterAdminRole->id)->get();
            $masterAdminCount = count($masterAdmins);

            if (isset($userRow->role->slug) && ! empty($userRow->role->slug) && $userRow->role->slug == \Config::get('role.master_admin_slug') && $masterAdminCount <= 1 && $status == config('core.no')) {
                // dd($masterAdminCount);
                $gridRequest = new Request;
                $gridRequest->merge([
                    'active_menu_id' => $request->get('active_menu_id'),
                    'type' => 'error',
                    'message' => trans('user::user.messages.one_master_admin_active'),
                ]);

                return $this->filters($gridRequest);
            } else {
                $this->user->update($userRow, $params);
            }
        }
        $gridRequest = new Request;
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans('core::core.messages.status_change_success'),
        ]);

        return $this->filters($gridRequest);
    }

    public function updateProfile(UpdateRequest $request)
    {
        try {
            /* the profile always belongs to the logged in admin, the id in the url is not trusted */
            $id = Auth::id();
            if (! $id) {
                throw new \Exception(trans('user::user.messages.data_invalid'));
            }
            $params = $request->all();

            /**
             * Only the fields the profile form exposes may be updated here. The form renders the role
             * and the status read only, so anything else in the payload is discarded rather than
             * letting an admin edit their own role or status.
             */
            $profileParams = array_intersect_key($params['user'] ?? [], array_flip(['name', 'email']));
            if (isset($params['password']) && $params['password']) {
                $profileParams['password'] = Hash::make($params['password']);
            }

            $user = $this->user->find($id);
            if (! $user) {
                throw new \Exception(trans('user::user.messages.data_invalid'));
            }

            $this->user->update($user, $profileParams);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.user.editProfile', updateUrlParams())->with('success', trans('user::user.messages.updated_success'));
            }

            return redirect()->route('admin.user.editProfile', updateUrlParams())->with('success', trans('user::user.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.user.editProfile', updateUrlParams())->with('error', $e->getMessage());
        }
    }
}
