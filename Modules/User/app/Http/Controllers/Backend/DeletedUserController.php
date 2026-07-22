<?php

namespace Modules\User\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\User\Models\User;
use Modules\User\Repositories\DeletedUserRepository;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Menu\Models\Menu;

class DeletedUserController extends BackendController
{
    /**
     * @var DeletedUserRepository
     */
    private $deleted_user;

    /**
     * @var UserEntity
     */
    private $deleted_userEntity;

    public function __construct(DeletedUserRepository $deleted_userRepo, User $userEntity)
    {
        parent::__construct();

        $this->deleted_user = $deleted_userRepo;
        $this->userEntity = $userEntity;
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("user.cache.deleted_user_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            // $columns = $this->deleted_user->sortColumns($request);
            $collection = $this->deleted_user->pagination($request);
            $filters = $this->deleted_user->getFilters($request);
            $statusOptions = $this->deleted_user->getStatusOptions(true);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('user::backend.deleted_user.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with("error", $e->getMessage());
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
                $perPage = getPerPageForModule(config("user.cache.deleted_user_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("user.cache.deleted_user_name"), $request);
            // $columns = $this->deleted_user->sortColumns($request);
            $filters = $this->deleted_user->getFilters($request);
            $collection = $this->deleted_user->pagination($request);
            $statusOptions = $this->deleted_user->getStatusOptions(true);
            $activeMenuId = getActiveMenuId($request, 'admin.deleted_user.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('user::backend.deleted_user.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));
            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ],
                'message' => $request->get('message')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    public function massRestore(Request $request)
    {
        try {
            $this->deleted_user->restoreMultiple($request);
            $this->deleted_user->flushCache(config("user.cache.name"));
            return redirect()->route('admin.deleted_user.index', updateUrlParams())->with("success", trans("user::deleted_user.messages.restore_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.deleted_user.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function restore(Request $request)
    {
        try {
            $id = decrypt_It($request->id);
            if (!$id) {
                throw new \Exception(trans("user::deleted_user.messages.data_invalid"));
            }
            $userRow = $this->userEntity->onlyTrashed()->where('id', $id)->first();
            if (!$userRow) {
                throw new \Exception(trans("user::deleted_user.messages.data_invalid"));
            }
            $this->deleted_user->restoreAndForceDelete($userRow, true);
            $this->deleted_user->flushCache(config("user.cache.name"));
            return redirect()->route('admin.deleted_user.index', updateUrlParams())->with("success", trans("user::deleted_user.messages.restore_success"));
        } catch (\Throwable $th) {
            return redirect()->route('admin.deleted_user.index', updateUrlParams())->with("error", $th->getMessage());
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
            $this->deleted_user->deleteRecord($request);            
            return redirect()->route('admin.deleted_user.index', updateUrlParams())->with("success", trans("user::deleted_user.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.deleted_user.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->deleted_user->forceDeleteMultiple($request);
            return redirect()->route('admin.deleted_user.index', updateUrlParams())->with("success", trans("user::deleted_user.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.deleted_user.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }
}
