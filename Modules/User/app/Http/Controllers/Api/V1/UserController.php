<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
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

    public function __construct(UserRepository $user, User $userEntity, RoleRepository $role)
    {
        parent::__construct();

        $this->user = $user;
        $this->userEntity = $userEntity;
        $this->role = $role;
    }

    public function login(Request $request)
    {

        try {
            $rules = [
                'email' => 'required|email',
                'password' => 'required',
            ];
            $validator = Validator::make($request->all(), $rules, [
                'required' => trans('customer::customer_api.messages.required'),
                'email.email' => trans('customer::customer_api.messages.invalid_email'),
            ]);
            // if validation fails
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()], 400);
            }

            $admin = User::where('email', $request->email)->first();

            $response = Http::asForm()->post(URL::to('oauth/token'), [
                'grant_type' => 'password',
                'client_id' => config('services.passport.password_client_id'),
                'client_secret' => config('services.passport.password_client_secret'),
                'username' => $request->email,
                'password' => $request->password,
                'scope' => 'admin',
            ]);

            if ($response->failed() || ! $response->json('access_token')) {
                return response()->json(['message' => trans('customer::customer_api.messages.wrong_credentials')], 400);
            }

            return $response->json();
        } catch (\Throwable $th) {
            Log::error($th);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }

    }

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
            $routeName = $request->route()?->getName();
            // $menuId = Menu::where('link',$routeName)->first()->id;
            $columns = getColumnObject()->getColumns(62);
            $roleOptions = $this->role->getRoleOptions(true);

            return response()->json(compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'roleOptions'));
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
    }

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
            // $activeMenuId = $request->get('active_menu_id');
            // $columns = getColumnObject()->getColumns($activeMenuId);

            return response()->json(compact('collection', 'filters'));

        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => trans('core::core.messages.unexpected_error'),
            ]);
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

            $this->user->create($params['user']);

            return response()->json(['success' => true, 'message' => trans('user::user.messages.created_success')]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
    }

    public function updateStatus(Request $request)
    {
        try {
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
                    return response()->json(['success' => false, 'message' => trans('user::user.messages.one_master_admin_active')], 400);
                } else {
                    $this->user->update($userRow, $params);
                }
            }

            return response()->json(['success' => true, 'message' => trans('user::user.messages.status_change_success')]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
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

            return response()->json(['success' => false, 'message' => trans('user::user.messages.deleted_success')]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $limit = (int) settings('core', 'max_delete_limit');
            $selectedIds = $request->get('selected_ids');
            $isSelectAll = $request->get('select_all');
            if (! $isSelectAll && empty($selectedIds)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid request. Expected an array of IDs.',
                ], 400);
            }
            $collection = $this->user->filter($request)->limit($limit);
            $ids = [];
            if ($isSelectAll) {
                $ids = $collection->pluck('id')->toArray();
            } else {
                $collection = $collection->whereIn('id', $selectedIds)->get();
                $ids = $collection->pluck('id')->toArray();
            }
            if (! empty($ids)) {
                $this->user->whereIn('id', $ids)->delete();
            }
            $this->user->flushCache(config('user.cache.name'));
            $this->user->flushCache(config('user.cache.deleted_user_name'));

            return response()->json(['success' => true, 'message' => trans('user::user.messages.deleted_success')]);

            // $this->user->destroyMultiple($request);
            // $this->user->flushCache(config("user.cache.deleted_user_name"));
            return redirect()->route('admin.user.index', updateUrlParams())->with('success', trans('user::user.messages.deleted_success'));
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
    }

    public function edit(Request $request, RoleRepository $roleRepo)
    {
        try {
            $id = $request->id;
            if (! $id) {
                return response()->json(['success' => false, 'message' => trans('user::user.messages.data_invalid')], 400);
            }
            $user = $this->user->find($id);
            if (! $user) {
                return response()->json(['success' => false, 'message' => trans('user::user.messages.data_invalid')], 400);
            }

            $masterAdminRole = $roleRepo->where('slug', \Config::get('role.master_admin_slug'))->first();
            $masterAdmins = $this->user->where('status', config('core.yes'))->where('role_id', $masterAdminRole->id)->get();
            $masterAdminCount = count($masterAdmins);

            $role = $roleRepo->find($user->role_id);
            $roleOptions = $roleRepo->getRoleOptions(true);
            $statusOptions = $this->user->getStatusOptions();

            return response()->json(compact('user', 'roleOptions', 'statusOptions', 'role', 'masterAdminCount'));
            // return view('user::backend.edit', compact("user", "roleOptions", "statusOptions", "role","masterAdminCount"));
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
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
                return response()->json(['success' => false, 'message' => trans('user::user.messages.data_invalid')], 400);
            }
            $params = $request->all();
            if (isset($params['password']) && $params['password']) {
                $params['user']['password'] = Hash::make($params['password']);
            }
            $currentUserId = Auth::user()->id;
            $user = $this->user->find($id);
            if (! $user) {
                return response()->json(['success' => false, 'message' => trans('user::user.messages.data_invalid')], 400);
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

            return response()->json(['success' => true, 'message' => trans('user::user.messages.updated_success')]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
    }
}
