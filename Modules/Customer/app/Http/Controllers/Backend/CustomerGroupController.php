<?php

namespace Modules\Customer\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Customer\Http\Requests\CustomerGroup\CreateRequest;
use Modules\Customer\Http\Requests\CustomerGroup\UpdateRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Customer\Repositories\CustomerGroupRepository;
use Modules\Customer\Models\CustomerGroup;
use Modules\Menu\Models\Menu;

class CustomerGroupController extends BackendController
{
    protected $customerGroup = null;
    protected $customerGroupEntity = null;

    public function __construct(CustomerGroupRepository $customerGroup, CustomerGroup $customerGroupEntity)
    {
        parent::__construct();
        $this->customerGroup = $customerGroup;
        $this->customerGroupEntity = $customerGroupEntity;
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
                $perPage = getPerPageForModule(config("customer.cache.customer_group_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("customer.cache.customer_group_name"), $request);
            $statusOptions = $this->customerGroup->getStatusOptions();
            $filters = $this->customerGroup->getFilters($request);
            $collection = $this->customerGroup->pagination($request);
            // $columns = $this->customerGroup->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.customer.group.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $notDeleteIds = $this->customerGroup->where('is_default', config('core.enabled'))->pluck('id')->all();
            $content = view('customer::backend.customer_group.partials.grid', compact('request', 'collection', 'columns', 'filters', 'notDeleteIds', 'statusOptions', 'activeMenuId'));
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

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("customer.cache.customer_group_name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $statusOptions = $this->customerGroup->getStatusOptions();
            $collection = $this->customerGroup->pagination($request);
            $filters = $this->customerGroup->getFilters($request);
            // $columns = $this->customerGroup->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            $notDeleteIds = $this->customerGroup->where('is_default', config('core.enabled'))->pluck('id')->all();
            return view('customer::backend.customer_group.index', compact('request', 'collection', 'columns', 'filters', 'notDeleteIds', 'statusOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        try {
            return view('customer::backend.customer_group.create');
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.group.index', updateUrlParams())->with("error", $e->getMessage());
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
            $params['is_default'] = (isset($params['is_default'])) ? config('core.enabled') : config('core.disabled');
            if ($params['is_default'] == config('core.enabled')) {
                $this->customerGroup->where('is_default', config('core.enabled'))->update(['is_default' => config('core.disabled')]);
            }
            $customerGroup = $this->customerGroup->create($params);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.customer.group.edit', updateUrlParams([$customerGroup->id]))->with("success", trans("customer::customer_group.messages.created_success"));
            }
            return redirect()->route('admin.customer.group.index', updateUrlParams())->with("success", trans("customer::customer_group.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.group.create', updateUrlParams())->with("error", $e->getMessage());
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
                throw new \Exception(trans("customer::customer_group.messages.data_invalid"));
            }
            $customerGroupEntity = $this->customerGroupEntity;
            $customerGroup = $this->customerGroup->find($id);
            if (!$customerGroup) {
                throw new \Exception(trans("customer::customer_group.messages.data_invalid"));
            }
            $statusOptions = $this->customerGroup->getStatusOptions();
            // for getting customerGroup 
            $notDeleteIds = $this->customerGroup->where('is_default', config('core.enabled'))->pluck('id')->all();
            return view('customer::backend.customer_group.edit', compact("customerGroup", 'customerGroupEntity', 'statusOptions', 'notDeleteIds'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.group.index', updateUrlParams())->with("error", $e->getMessage());
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
                throw new \Exception(trans("customer::customer_group.messages.data_invalid"));
            }
            $params = $request->all();
            $customerGroup = $this->customerGroup->find($id);
            if (!$customerGroup) {
                throw new \Exception(trans("customer::customer.messages.data_invalid"));
            }
            $params['is_default'] = (isset($params['is_default'])) ? config('core.enabled') : config('core.disabled');
            if ($params['is_default'] == config('core.enabled')) {
                if ($customerGroup->is_default == config('core.enabled')) {
                    $params['is_default'] = config('core.enabled');
                } else {
                    $this->customerGroup->where('is_default', config('core.enabled'))->update(['is_default' => config('core.disabled')]);
                }
            } else {
                if ($customerGroup->is_default == config('core.enabled')) {
                    $params['is_default'] = config('core.enabled');
                }
            }
            $this->customerGroup->update($customerGroup, $params);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.customer.group.edit', updateUrlParams([$id]))->with("success", trans("customer::customer_group.messages.updated_success"));
            }
            return redirect()->route('admin.customer.group.index', updateUrlParams())->with("success", trans("customer::customer_group.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.group.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("customer::customer_group.messages.data_invalid"));
            }
            $notDeleteIds = $this->customerGroup->where('is_default', config('core.enabled'))->pluck('id')->all();
            if (!in_array($id, $notDeleteIds)) {
                $customerGroup = $this->customerGroup->find($id);

                if (!$customerGroup) {
                    throw new \Exception(trans("customer::customer_group.messages.data_invalid"));
                }

                $this->customerGroup->destroy($customerGroup);
            }
            return redirect()->route('admin.customer.group.index', updateUrlParams())->with("success", trans("customer::customer_group.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.group.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $notDeleteIds = $this->customerGroup->where('is_default', config('core.enabled'))->pluck('id')->all();
            $this->customerGroup->destroyMultiple($request, false, $notDeleteIds);
            return redirect()->route('admin.customer.group.index', updateUrlParams())->with("success", trans("customer::customer_group.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.group.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function updateIsDefault(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $isDefault = $request->get('is_default');
            $isDefault = ($isDefault == 1) ? 1 : 2;
            if ($isDefault == 1) {
                $this->customerGroup->where('is_default', config('core.enabled'))->update(['is_default' => config('core.disabled')]);
                $customerGroupRow = $this->customerGroup->find($id);
                $params = array('is_default' => $isDefault);
                $this->customerGroup->update($customerGroupRow, $params);
            }
        }
        $request = new Request();
        $request->merge(['message' => trans("core::core.messages.status_change_success")]);
        return $this->filters($request);
    }
}
