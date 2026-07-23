<?php

namespace Modules\Customer\Http\Controllers\Backend;

use Config;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Customer\Http\Requests\CreateRequest;
use Modules\Customer\Http\Requests\CustomerAddressRequest;
use Modules\Customer\Http\Requests\UpdateRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Repositories\CustomerRepository;

class CustomerController extends BackendController
{
    protected $customer = null;

    protected $customerEntity = null;

    public function __construct(CustomerRepository $customer, Customer $customerEntity)
    {
        parent::__construct();
        $this->customer = $customer;
        $this->customerEntity = $customerEntity;
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
                $perPage = getPerPageForModule(config('customer.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config('customer.cache.name'), $request);
            $statusOptions = $this->customer->getStatusOptions(true);
            $filters = $this->customer->getFilters($request, $statusOptions);
            $collection = $this->customer->pagination($request);
            // $columns = $this->customer->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.customer.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $content = view('customer::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
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
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('customer.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            $statusOptions = $this->customer->getStatusOptions(true);

            $collection = $this->customer->pagination($request);
            $filters = $this->customer->getFilters($request, $statusOptions);
            // $columns = $this->customer->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('customer::backend.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * image max upload size.
     *
     * @return Response
     */
    private function getMaxImageSize()
    {
        $maxUploadSize = (! empty(settings('customer', 'max_upload_size'))) ? settings('customer', 'max_upload_size') : Config::get('customer.default_image_size');
        $maxUploadServer = (int) (ini_get('upload_max_filesize')) > (int) (ini_get('post_max_size')) ? (int) (ini_get('post_max_size')) : (int) (ini_get('upload_max_filesize'));

        return $maxUploadSize ? (($maxUploadSize > $maxUploadServer) ? $maxUploadServer : $maxUploadSize) : $maxUploadServer;
    }

    private function getImageTypes()
    {
        $imageTypes = (! empty(settings('customer', 'image_type'))) ? settings('customer', 'image_type') : Config::get('customer.default_image_type');
        $imageTypes = explode(',', $imageTypes);
        $imageTypes = '.'.implode(',.', $imageTypes);

        return $imageTypes;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        try {
            $image_extension = $this->getImageTypes();
            $image_max_size = $this->getMaxImageSize();
            $statusOptions = $this->customer->getStatusOptions(true);

            return view('customer::backend.create', compact('statusOptions', 'image_extension', 'image_max_size'));
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
    public function store(CreateRequest $request)
    {
        try {
            $params = $request->all();
            $checkEmailInTransh = Customer::onlyTrashed()->where('email', $params['customer']['email'])->get()->first();

            /* customer is  registred in our system but added in softdelete */
            if (! empty($checkEmailInTransh)) {
                return redirect()->route('admin.customer.create', updateUrlParams())->with('error', trans('customer::customer.messages.soft_delete_email_exist'));
            }
            if ($request->file('profile_picture')) {
                $imageUploadParams = [
                    'module_name' => strtolower(Config::get('customer.name')),
                    'dbfield' => 'profile_picture',
                    'thumbnail' => true,
                    'thumbnail_size' => 100,
                ];
                $imageParams = $this->customer->setUploadParams($imageUploadParams)->uploadImage($request);
                $params['customer']['profile_picture'] = $imageParams['profile_picture'];
            }
            if (isset($params['password']) && $params['password']) {
                $params['customer']['password'] = Hash::make($params['password']);
            }

            $params['customer']['status'] = (isset($params['customer']['status'])) ? config('core.enabled') : config('core.disabled');

            $customer = $this->customer->create($params['customer']);

            if (isset($customer) && ! empty($customer)) {
                $customerId = $customer->id;
                $customerAddress = new CustomerAddress;
                $addressArray = $params['address'];
                $addressArray['customer_id'] = $customerId;
                $addressArray['is_default_address'] = config('customer.is_default_address.yes');
                $customerAddress->create($addressArray);
            }

            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.customer.edit', updateUrlParams([$customer->id]))->with('success', trans('customer::customer.messages.created_success'));
            }

            return redirect()->route('admin.customer.index', updateUrlParams())->with('success', trans('customer::customer.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.create', updateUrlParams())->with('error', $e->getMessage());
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
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }
            $customer = $this->customer->find($id);

            if (! $customer) {
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }

            $image_extension = $this->getImageTypes();
            $image_max_size = $this->getMaxImageSize();
            $statusOptions = $this->customer->getStatusOptions();

            return view('customer::backend.edit', compact('customer', 'statusOptions', 'image_extension', 'image_max_size'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function saveAddress(CustomerAddressRequest $request)
    {
        $customerId = $request->get('customer_id');
        try {

            $customerAddress = new CustomerAddress;

            $addressArray = $request->get('address');
            $addressArray['customer_id'] = $customerId;
            $addressId = $request->get('address_id');

            if (isset($addressArray['is_default_address']) && ! empty($addressArray['is_default_address'])) {
                if ($addressArray['is_default_address'] == 'on') {
                    $customerAddress->where('customer_id', $customerId)->update(['is_default_address' => 0]);
                    $addressArray['is_default_address'] = config('customer.is_default_address.yes');
                } else {
                    $addressArray['is_default_address'] = config('customer.is_default_address.no');
                }
            } else {
                $addressArray['is_default_address'] = config('customer.is_default_address.no');
            }

            if (isset($addressId) && ! empty($addressId)) {
                $customerAddress = $customerAddress->find($addressId);
                $customerAddress->update($addressArray);
            } else {
                $customerAddress->create($addressArray);
            }

            return redirect()->route('admin.customer.edit', updateUrlParams([$customerId]))->withInput(['tab' => '#custom-tabs-three-address-tab'])->with('success', trans('customer::customer.messages.address_save'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.edit', updateUrlParams([$customerId]))->with('error', $e->getMessage());
        }
    }

    public function getAddress(Request $request)
    {
        try {
            $customerAddress = new CustomerAddress;
            $customerAddress = $customerAddress->find($request->get('id'));
            if ($customerAddress) {
                return response()->json([
                    'type' => 'success',
                    'data' => $customerAddress->toArray(),
                ]);
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

    public function deleteAddress(Request $request)
    {
        try {
            $id = $request->id;
            $customerAddress = (new CustomerAddress)->find($id);
            if (! $id || ! $customerAddress) {
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }
            $customerId = $customerAddress->customer_id;
            if ($customerAddress->is_default_address == config('customer.is_default_address.yes')) {
                return redirect()->route('admin.customer.edit', updateUrlParams([$customerId]))->withInput(['tab' => '#custom-tabs-three-address-tab'])->with('error', trans('customer::customer.messages.default_add_error'));
            }
            $customerAddress->delete();

            return redirect()->route('admin.customer.edit', updateUrlParams([$customerId]))->withInput(['tab' => '#custom-tabs-three-address-tab'])->with('success', trans('customer::customer.messages.address_remove'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
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
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }
            $params = $request->all();
            $checkEmailInTransh = Customer::onlyTrashed()->where('email', $params['customer']['email'])->get()->first();

            /* customer is  registred in our system but added in softdelete */
            if (! empty($checkEmailInTransh)) {
                return redirect()->route('admin.customer.edit', updateUrlParams([$id]))->with('error', trans('customer::customer.messages.soft_delete_email_exist'));
            }
            if (isset($params['password']) && $params['password']) {
                $params['customer']['password'] = Hash::make($params['password']);
            }
            $params['customer']['status'] = (isset($params['customer']['status'])) ? config('core.enabled') : config('core.disabled');

            $customer = $this->customer->find($id);
            if (! $customer) {
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }

            if (isset($params['remove_profile_picture']) && $params['remove_profile_picture']) {
                $imageRemoveParams = [
                    'module_name' => strtolower(Config::get('customer.name')),
                    'dbfield' => 'profile_picture',
                ];
                $this->customer->setUploadParams($imageRemoveParams)->setModel($customer)->removeFile($customer->profile_picture, strtolower(Config::get('customer.name')));
                $params['customer']['profile_picture'] = null;
            }
            $request->merge($params);
            if ($request->file('profile_picture')) {
                $imageUploadParams = [
                    'module_name' => strtolower(Config::get('customer.name')),
                    'dbfield' => 'profile_picture',
                    'thumbnail' => true,
                    'thumbnail_size' => 100,
                ];
                /* setModel() lets the repository clean up the previously uploaded file */
                $imageParams = $this->customer->setUploadParams($imageUploadParams)->setModel($customer)->uploadImage($request);
                $params['customer']['profile_picture'] = $imageParams['profile_picture'];
            }
            $this->customer->update($customer, $params['customer']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.customer.edit', updateUrlParams([$id]))->withInput(['tab' => '#custom-tabs-three-address-tab'])->with('success', trans('customer::customer.messages.updated_success'));
            }

            return redirect()->route('admin.customer.index', updateUrlParams())->with('success', trans('customer::customer.messages.updated_success'));
        } catch (\Throwable $e) {
            if (empty($id)) {
                return redirect()->route('admin.customer.index', updateUrlParams())->with('error', $e->getMessage());
            }

            return redirect()->route('admin.customer.edit', updateUrlParams([$id]))->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        try {
            $this->customer->deleteRecord($request);
            $this->customer->flushCache(config('customer.cache.deleted_customer_name'));

            return redirect()->route('admin.customer.index', updateUrlParams())->with('success', trans('customer::customer.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            /* soft delete only: the uploaded files are removed when the record is force deleted from the deleted customers grid */
            $this->customer->destroyMultiple($request);
            $this->customer->flushCache(config('customer.cache.deleted_customer_name'));

            return redirect()->route('admin.customer.index', updateUrlParams())->with('success', trans('customer::customer.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.customer.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * status change on grid page
     */
    public function updateStatus(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $status = $request->get('status');
            $customerRow = $this->customer->find($id);
            $status = ($status == config('core.enabled')) ? config('core.enabled') : config('core.disabled');
            $params = ['status' => $status];
            $this->customer->update($customerRow, $params);
        }
        $gridRequest = new Request;
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans('core::core.messages.status_change_success'),
        ]);

        return $this->filters($gridRequest);
    }
}
