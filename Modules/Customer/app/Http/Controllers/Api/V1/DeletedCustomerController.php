<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Customer\Repositories\DeletedCustomerRepository;
use Modules\Customer\Models\Customer;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use Modules\Customer\Repositories\CustomerRepository;
use Modules\Menu\Models\Menu;

class DeletedCustomerController extends BackendController
{
    protected $customer = null;
    protected $customerEntity = null;
    public function __construct(DeletedCustomerRepository $customerDel, Customer $customerEntity)
    {
        parent::__construct();
        $this->customer = $customerDel;
        $this->customerEntity = $customerEntity;
    }

    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("customer.cache.deleted_customer_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $statusOptions = $this->customer->getStatusOptions(true);
            $collection = $this->customer->pagination($request);
            $filters = $this->customer->getFilters($request);
            // $columns = $this->customer->sortColumns($request);

            $routeName = $request->route()?->getName();
            // $menuId = Menu::where('link',$routeName)->first()->id;
            $columns = getColumnObject()->getColumns(67);
            return response()->json(compact( 'collection', 'columns', 'filters', 'statusOptions'));
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
                $perPage = getPerPageForModule(config("customer.cache.deleted_customer_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("customer.cache.deleted_customer_name"), $request);
            $statusOptions = $this->customer->getStatusOptions(true);
            $filters = $this->customer->getFilters($request);
            $collection = $this->customer->pagination($request);

            // $columns = $this->customer->sortColumns($request);
            
            $activeMenuId = $request->get('active_menu_id');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $content = view('customer::backend.partials.deletedcustomergrid', compact('request', 'collection', 'columns', 'filters', 'statusOptions'));
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

    public function massRestore(Request $request)
    {
        try {
            $this->customer->restoreMultiple($request);
            $this->customer->flushCache(config("customer.cache.name"));
            return redirect()->route('admin.customer.deletedCustomer', updateUrlParams())->with("success", trans("customer::customer.messages.restore_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.deletedCustomer', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function restore(Request $request)
    {
        try {

            $id = decrypt_It($request->id);
            if (!$id) {
                throw new \Exception(trans("customer::customer.messages.data_invalid"));
            }

            $customerRow = $this->customerEntity->onlyTrashed()->where('id', $id)->first();
            if (!$customerRow) {
                throw new \Exception(trans("customer::customer.messages.data_invalid"));
            }
            $this->customer->restoreAndForceDelete($customerRow, true);
            $this->customer->flushCache(config("customer.cache.name"));
            return redirect()->route('admin.customer.deletedCustomer', updateUrlParams())->with("success", trans("customer::customer.messages.restore_success"));
        } catch (\Throwable $th) {
            return redirect()->route('admin.customer.deletedCustomer', updateUrlParams())->with("error", $th->getMessage());
        }
    }


    public function destroy(Request $request)
    {
        try {          
            $imageRemoveParams = array(
                'module_name' => strtolower(config('customer.name')),
                'dbfield' => 'profile_picture'
            );
            $this->customer->deleteRecord($request, $imageRemoveParams);
            return redirect()->route('admin.customer.deletedCustomer', updateUrlParams())->with("success", trans("customer::customer.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.deletedCustomer', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $imageRemoveParams = array(
                'module_name' => strtolower(config('customer.name')),
                'dbfield' => 'profile_picture'
            );
            $this->customer->setUploadParams($imageRemoveParams);
            $this->customer->forceDeleteMultiple($request, true);
            return redirect()->route('admin.customer.deletedCustomer', updateUrlParams())->with("success", trans("customer::customer.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.deletedCustomer', updateUrlParams())->with("error", $e->getMessage());
        }
    }
}
