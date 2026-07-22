<?php

namespace Modules\Customer\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Core\Handler\Log;
use Modules\Core\Cache\FileStore;
use Modules\Customer\Repositories\CustomerLoginLogRepository;
use Modules\Menu\Models\Menu;

class CustomerLoginLogController extends BackendController
{
    public function __construct(Log $log, CustomerLoginLogRepository $customerLoginLogRepo)
    {
        parent::__construct();

        $this->customerLoginLogRepo = $customerLoginLogRepo;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("customer.cache.customer_login_log"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            
            // $columns = $this->customerLoginLogRepo->sortColumns($request);
            $filters = $this->customerLoginLogRepo->getFilters($request);
            $collection = $this->customerLoginLogRepo->pagination($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            
            return view('customer::backend.customerloginlog.index', compact('request', 'collection', 'columns', 'filters', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customerLog.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function filters(Request $request)
    {
        try {
            
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("customer.cache.customer_login_log"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            
            setFilterSession(config("customer.cache.customer_login_log"), $request);
            // $columns = $this->customerLoginLogRepo->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.customerloginlog.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $collection = $this->customerLoginLogRepo->pagination($request);
            $filters = $this->customerLoginLogRepo->getFilters($request);
    
            $content = view('customer::backend.customerloginlog.partials.grid', compact('request', 'collection', 'columns', 'filters', 'activeMenuId'));
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

    public function export(Request $request) {
        try {
            $data = $this->customerLoginLogRepo->export($request);
            $columnNames = ['customer_id', 'customer_name', 'email', 'last_login_date'];
            return $this->customerLoginLogRepo->exportCsv($columnNames, $data, 'CustomerLoginReport');
        } catch (\Throwable $e) {
            return redirect()->route('admin.customerloginlog.index', updateUrlParams())->with("error", $e->getMessage() . $e->getTraceAsString());
        }
    }
}