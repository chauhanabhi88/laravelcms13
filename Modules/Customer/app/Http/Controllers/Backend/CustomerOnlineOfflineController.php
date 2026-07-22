<?php

namespace Modules\Customer\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Core\Handler\Log;
use Modules\Core\Cache\FileStore;
use Illuminate\Support\Facades\Cache as FacadesCache;
use Modules\Customer\Repositories\CustomerOnlineOfflineLogRepository;
use Modules\Menu\Models\Menu;
class CustomerOnlineOfflineController extends BackendController
{
    /**
     * @var ActivityLogRepository
     */
    private $activitylog;
    private $loginlog;

    /**
     * @var UserEntity
     */
    private $activitylogEntity;
    private $log;

    public function __construct(Log $log, CustomerOnlineOfflineLogRepository $customerOnlineOfflineLogRepo)
    {
        parent::__construct();

        $this->customerOnlineOfflineLogRepo = $customerOnlineOfflineLogRepo;
        $this->log = $log;
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if(!(int)config('customer.show_customer_online_offline_grid')) {
                return redirect()->route('admin.dashboard.index', updateUrlParams());
            }
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("customer.cache.customer_online_offline_log"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            // $columns = $this->customerOnlineOfflineLogRepo->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            $filters = $this->customerOnlineOfflineLogRepo->getFilters($request);
            $fileStore = new FileStore(FacadesCache::getFilesystem(), storage_path('framework/cache/customer'));
            $collection = $this->customerOnlineOfflineLogRepo->pagination($request);
           
            return view('customer::backend.customerlog.index', compact('request', 'collection', 'fileStore', 'columns', 'filters', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customerLog.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }


    // public function refreshGrid(Request $request)
    // {
    //     try {
    //         $columns = $this->customerOnlineOfflineLogRepo->sortColumns($request);
    //         $collection = $this->customerOnlineOfflineLogRepo->pagination($request);
    //         $filters = $this->customerOnlineOfflineLogRepo->getFilters($request);
    //         $fileStore = new FileStore(FacadesCache::getFilesystem(), storage_path('framework/cache/customer'));
    //         $content = view('customer::backend.customerlog.partials.grid', compact('request', 'collection', 'fileStore', 'columns', 'filters'));
    //         return response()->json([
    //             'type' => 'success',
    //             'content' => [
    //                 'element' => 'collection',
    //                 'html' => $content->__toString()
    //             ]
    //         ]);
    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'type' => 'error',
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    // }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("customer.cache.customer_online_offline_log"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("customer.cache.name"), $request, null, config("activitylog.customer_online_offline_log"));
            // $columns = $this->customerOnlineOfflineLogRepo->sortColumns($request);
            $collection = $this->customerOnlineOfflineLogRepo->pagination($request);
            $filters = $this->customerOnlineOfflineLogRepo->getFilters($request);
            $fileStore = new FileStore(FacadesCache::getFilesystem(), storage_path('framework/cache/customer'));
            $activeMenuId = getActiveMenuId($request, 'admin.customerLog.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $content = view('customer::backend.customerlog.partials.grid', compact('request', 'collection', 'fileStore', 'columns', 'filters', 'activeMenuId'));
            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

}
