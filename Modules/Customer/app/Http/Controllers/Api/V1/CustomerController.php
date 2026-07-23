<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Auth\Access\UnauthorizedException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\RefreshTokenRepository;
use Modules\Customer\Emails\CustomerEmailVerifyMail;
use Modules\Customer\Emails\CustomerRegistrationAdminMail;
use Modules\Customer\Http\Requests\CreateRequest;
use Modules\Customer\Http\Requests\CustomerAddressRequest;
use Modules\Customer\Http\Requests\UpdateRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Repositories\CustomerLoginLogRepository;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Menu\Models\Menu;

class CustomerController extends Controller
{
    protected $customerRepo;

    protected $customerLoginLogRepository;

    // Dependency injection: Laravel resolves the interface from your ServiceProvider
    public function __construct(CustomerRepository $customerRepo, CustomerLoginLogRepository $customerLoginLogRepository)
    {
        $this->customerRepo = $customerRepo;
        $this->customerLoginLogRepository = $customerLoginLogRepository;
    }

    public function signup(Request $request)
    {
        try {
            $emailVerification = settings('core', 'email_verification');

            // check if email verification is ON or Off.
            $mailSend = ($emailVerification == config('customer.email_verification.no')) ? false : true;
            $signupType = ($emailVerification == config('customer.email_verification.no')) ? config('customer.signup_type.auto_active.code') : config('customer.signup_type.email_verification.code');
            $status = ($emailVerification == config('customer.email_verification.no')) ? config('customer.enabled_status') : config('customer.disabled_status');
            $data = $request->all();

            // validation of data start
            $rules = [
                'email' => 'required|email|unique:customer',
                'password' => [
                    'required',
                    Password::min(5)
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ],
                'first_name' => 'required|min:3',
                'last_name' => 'required|min:3',
                'profile_picture' => 'mimes:'.(! empty(settings('customer', 'image_type')) ? settings('customer', 'image_type') : config('customer.default_image_type')).'|max:'.$this->getMaxImageSize() * 1024,
            ];
            $validator = Validator::make($data, $rules, [
                'required' => trans('customer::customer_api.messages.required'),
                'min' => trans('customer::customer_api.messages.password_length'),
                'name.min' => trans('customer::customer_api.messages.name_length'),
                'email.unique' => trans('customer::customer_api.messages.email_exists'),
                'email.email' => trans('customer::customer_api.messages.invalid_email'),
                'password.mixedCase' => trans('customer::customer_api.messages.password_regex'),
                'password.numbers' => trans('customer::customer_api.messages.password_regex'),
                'password.symbols' => trans('customer::customer_api.messages.password_regex'),
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()], 400);
            }
            // validation of  data end

            /* the status is driven by the email verification setting, never by the request payload */
            $data['status'] = $status;

            if ($request->file('profile_picture')) {
                $imageUploadParams = [
                    'module_name' => strtolower(config('customer.name')),
                    'dbfield' => 'profile_picture',
                    'thumbnail' => true,
                    'thumbnail_size' => 100,
                ];
                $imageParams = $this->customerRepo->setUploadParams($imageUploadParams)->uploadImage($request);
                $data['profile_picture'] = $imageParams['profile_picture'];
            }
            $customerData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make(encryptPassword($data['password'])),
                'status' => $data['status'],
                'profile_picture' => $data['profile_picture'] ?? null,
            ];

            $customer = Customer::create($customerData);
            if ($mailSend) {
                /* If Verfication On, then Send Verification link to Customer */
                Mail::send(new CustomerEmailVerifyMail($customer));
            }
            /* Notification Mail Send to Admin */
            Mail::send(new CustomerRegistrationAdminMail($customer));

            return response()->json(['success' => true, 'message' => trans('customer::customer_api.messages.signup_success')]);

        } catch (\Throwable $th) {
            Log::error($th);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
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
            try {
                return $this->manageMultiDeviceLogin($request);
            } catch (\Throwable $th) {
                return response()->json(['success' => false, 'message' => $th->getMessage()], 400);
            }
        } catch (\Throwable $th) {
            Log::error($th);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
    }

    /**
     * Manage Multi Device Login
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws UnauthorizedException
     * @throws ValidationException
     */
    public function manageMultiDeviceLogin(Request $request)
    {
        $device = $request->header('Device-Name');
        $appVersion = $request->app_version;
        if (empty($appVersion)) {
            $appVersion = $request->header('App-Version');
        }
        $customer = $this->customerRepo->where('email', $request->email)->latest()->first();
        if (empty($customer)) {
            $customer = $this->customerRepo->where('email', $request->email)->withTrashed()->latest()->first();
        }
        if (! empty($customer)) {

            // If Customer is deleted then show this message
            if ($customer->trashed()) {
                throw new \Exception(trans('customer::front_customer.messages.deleted_customer'));
            }

            // if multiple device login is not allowed, then inactivate previous session
            $allowMultipleLogin = settings('customer', 'multi_device_login');
            if (! $allowMultipleLogin) {
                $refreshTokenRepository = app(RefreshTokenRepository::class);
                $clientId = $request->client_id;
                $customerInfor = Customer::where('id', $customer->id)->with('tokens', function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                })->first();
                if (! empty($customerInfor) && ! empty($customerInfor->tokens)) {
                    foreach ($customerInfor->tokens as $token) {
                        $token->revoke();
                        $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($token->id);
                    }
                }
            }
            $responseLogin = Http::asForm()->post(URL::to('oauth/token'), [
                'grant_type' => 'password',
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'username' => $request->email,
                'password' => $request->password,
                'scope' => 'customer',
            ]);
            if ($responseLogin->failed() || ! $responseLogin->json('access_token')) {
                return response()->json(['message' => trans('customer::customer_api.messages.wrong_credentials')], 400);
            }
            $response = $responseLogin->json();
            if ($response && empty($response['error'])) {
                // update login log
                $logParam = [];
                $logParam['customer_id'] = $customer->id;
                $logParam['is_loggedin'] = config('core.yes');
                $logParam['device'] = $device;
                $logParam['app_version'] = $appVersion;

                // if multiple device login is not allowed, then inactivate previous session(customer_login_log)
                if (! $allowMultipleLogin) {
                    $this->customerLoginLogRepository->inActivePreviousSession($logParam);
                }
                $this->customerLoginLogRepository->setParam($logParam);

                return $response;
            } else {
                $message = (isset($response['error_description'])) ? $response['error_description'] : (isset($response['message']) ? $response['message'] : '');
                throw new \Exception($message, $responseLogin->getStatusCode());
            }
        }
    }

    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('customer.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => 4]);
            }
            $statusOptions = $this->customerRepo->getStatusOptions(true);

            $collection = $this->customerRepo->pagination($request);
            $collection = $this->updateImageUrl($collection);
            $filters = $this->customerRepo->getFilters($request, $statusOptions);
            // $columns = $this->customerRepo->sortColumns($request);
            $routeName = $request->route()?->getName();
            // $menuId = Menu::where('link',$routeName)->first()->id;
            $columns = getColumnObject()->getColumns(29);

            return response()->json(compact('request', 'collection', 'columns', 'filters', 'statusOptions'));
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')]);
        }
    }

    public function updateImageUrl($collection)
    {
        $collection->getCollection()->transform(function ($customer) {
            $og_image_param = [
                'module' => config('customer.name'),
                'image' => $customer->profile_picture,
            ];
            $thumbnail_image_param = [
                'image-type' => 'thumbnail',
                'module' => config('customer.name'),
                'image' => $customer->profile_picture,
                'defualt-image' => true,
            ];
            $customer->profile_picture = getImageUrl($og_image_param) ? getImageUrl($og_image_param) : getImageUrl($thumbnail_image_param);

            return $customer;
        });

        return $collection;
    }

    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('customer.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => 4]);
            }
            setFilterSession(config('customer.cache.name'), $request);
            $statusOptions = $this->customerRepo->getStatusOptions(true);
            $filters = $this->customerRepo->getFilters($request, $statusOptions);
            $collection = $this->customerRepo->pagination($request);
            $collection = $this->updateImageUrl($collection);
            // $columns = $this->customerRepo->sortColumns($request);
            $activeMenuId = $request->get('active_menu_id');
            $columns = getColumnObject()->getColumns($activeMenuId);

            return response()->json(compact('request', 'collection', 'columns', 'filters', 'statusOptions'));

        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => trans('core::core.messages.unexpected_error'),
            ], 500);
        }
    }

    public function edit(Request $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }
            $customer = $this->customerRepo->find($id);

            if (! $customer) {
                throw new \Exception(trans('customer::customer.messages.data_invalid'));
            }
            $og_image_param = [
                'module' => config('customer.name'),
                'image' => $customer->profile_picture,
            ];
            $addresses = $customer->address;
            $customer->address = $addresses;
            $customer->profile_picture = getImageUrl($og_image_param) ? getImageUrl($og_image_param) : null;
            $image_extension = (! empty(settings('customer', 'image_type'))) ? settings('customer', 'image_type') : config('customer.default_image_type');
            $image_max_size = $this->getMaxImageSize();
            $statusOptions = $this->customerRepo->getStatusOptions();

            return response()->json(compact('customer', 'statusOptions', 'image_extension', 'image_max_size'));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * image max upload size.
     *
     * @return Response
     */
    private function getMaxImageSize()
    {
        $maxUploadSize = (! empty(settings('customer', 'max_upload_size'))) ? settings('customer', 'max_upload_size') : config('customer.default_image_size');
        $maxUploadServer = (int) (ini_get('upload_max_filesize')) > (int) (ini_get('post_max_size')) ? (int) (ini_get('post_max_size')) : (int) (ini_get('upload_max_filesize'));

        return $maxUploadSize ? (($maxUploadSize > $maxUploadServer) ? $maxUploadServer : $maxUploadSize) : $maxUploadServer;
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     *
     * @throws \Throwable
     */
    public function update(UpdateRequest $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('banner::banner_group.messages.data_invalid'));
            }
            $params = $request->all();
            $checkEmailInTransh = Customer::onlyTrashed()->where('email', $params['customer']['email'])->get()->first();

            /* customer is  registred in our system but added in softdelete */
            if (! empty($checkEmailInTransh)) {
                return response()->json(['success' => false, 'message' => trans('customer::customer.messages.soft_delete_email_exist')], 400);
            }
            if (isset($params['password']) && $params['password']) {
                $params['customer']['password'] = Hash::make($params['password']);
            }
            $params['customer']['status'] = (isset($params['customer']['status'])) ? config('core.enabled') : config('core.disabled');

            $customer = $this->customerRepo->find($id);
            if (! $customer) {
                return response()->json(['success' => false, 'message' => trans('customer::customer.messages.data_invalid')], 400);
            }

            if (isset($params['remove_profile_picture']) && $params['remove_profile_picture']) {
                $imageRemoveParams = [
                    'module_name' => strtolower(config('customer.name')),
                    'dbfield' => 'profile_picture',
                ];
                $this->customerRepo->setUploadParams($imageRemoveParams)->setModel($customer)->removeFile($customer->profile_picture, strtolower(config('customer.name')));
                $params['customer']['profile_picture'] = null;
            }
            $request->merge($params);
            if ($request->file('profile_picture')) {
                $imageUploadParams = [
                    'module_name' => strtolower(config('customer.name')),
                    'dbfield' => 'profile_picture',
                    'thumbnail' => true,
                    'thumbnail_size' => 100,
                ];
                /* setModel() lets the repository clean up the previously uploaded file */
                $imageParams = $this->customerRepo->setUploadParams($imageUploadParams)->setModel($customer)->uploadImage($request);
                $params['customer']['profile_picture'] = $imageParams['profile_picture'];
            }
            $this->customerRepo->update($customer, $params['customer']);

            return response()->json([
                'success' => true,
                'message' => trans('customer::customer.messages.updated_success'),
            ]);
            // if (isset($params['snc']) && $params['snc']) {
            //     return redirect()->route('admin.customer.edit', updateUrlParams([$id]))->withInput(['tab' => '#custom-tabs-three-address-tab'])->with('success', trans("customer::customer.messages.updated_success"));
            // }
            // return redirect()->route('admin.customer.index', updateUrlParams())->with("success", trans("customer::customer.messages.updated_success"));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveAddress(CustomerAddressRequest $request)
    {
        try {

            $customerAddress = new CustomerAddress;

            $addressArray = $request->get('address');
            $customerId = $request->get('customer_id');
            $addressArray['customer_id'] = $customerId;
            $addressId = $request->get('address_id');

            if (isset($addressArray['is_default_address']) && ! empty($addressArray['is_default_address'])) {
                if ($addressArray['is_default_address'] == 1) {
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

            return response()->json([
                'success' => true,
                'message' => trans('customer::customer.messages.address_save'),
            ]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => trans('core::core.messages.unexpected_error'),
            ], 500);
        }
    }

    public function deleteAddress(Request $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new \Exception(trans('cuatomer::cuatomer.messages.data_invalid'));
            }
            $customerAddress = new CustomerAddress;
            $customerAddress = $customerAddress->find($id);
            if ($customerAddress->is_default_address == config('customer.is_default_address.yes')) {
                return response()->json(['success' => false, 'message' => trans('customer::customer.messages.default_add_error')]);
            }
            $customerAddress->delete();

            return response()->json([
                'success' => true,
                'message' => trans('customer::customer.messages.address_delete'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

    }

    public function store(CreateRequest $request)
    {
        try {
            $params = $request->all();
            $checkEmailInTransh = Customer::onlyTrashed()->where('email', $params['customer']['email'])->get()->first();

            /* customer is  registred in our system but added in softdelete */
            if (! empty($checkEmailInTransh)) {
                return response()->json(['success' => false, 'message' => trans('customer::customer.messages.soft_delete_email_exist')], 400);
            }
            if ($request->file('profile_picture')) {
                $imageUploadParams = [
                    'module_name' => strtolower(config('customer.name')),
                    'dbfield' => 'profile_picture',
                    'thumbnail' => true,
                    'thumbnail_size' => 100,
                ];
                $imageParams = $this->customerRepo->setUploadParams($imageUploadParams)->uploadImage($request);
                $params['customer']['profile_picture'] = $imageParams['profile_picture'];
            }
            if (isset($params['password']) && $params['password']) {
                $params['customer']['password'] = Hash::make($params['password']);
            }
            $params['customer']['signup_type'] = config('customer.signup_type.admin.code');

            $params['customer']['status'] = (isset($params['customer']['status'])) ? config('core.enabled') : config('core.disabled');

            $customer = $this->customerRepo->create($params['customer']);

            if (isset($customer) && ! empty($customer)) {
                $customerId = $customer->id;
                $customerAddress = new CustomerAddress;
                $addressArray = $params['address'];
                $addressArray['customer_id'] = $customerId;
                $addressArray['is_default_address'] = config('customer.is_default_address.yes');
                $customerAddress->create($addressArray);
            }

            return response()->json(['success' => true, 'message' => trans('customer::customer.messages.created_success')]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => trans('core::core.messages.unexpected_error'),
            ], 500);
        }
    }

    /**
     * Delete the specified customer.
     *
     * @return Response
     *
     * @throws \Throwable
     */
    public function destroy(Request $request)
    {
        try {
            $this->customerRepo->deleteRecord($request);
            $this->customerRepo->flushCache(config('customer.cache.deleted_customer_name'));

            return response()->json(['success' => true, 'message' => trans('customer::customer.messages.deleted_success')]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => trans('core::core.messages.unexpected_error'),
            ], 500);
        }
    }

    /**
     * status change on grid page
     */
    public function updateStatus(Request $request)
    {
        try {
            if ($request->get('id')) {
                $id = $request->get('id');
                $status = $request->get('update_status');
                $customerRow = $this->customerRepo->find($id);
                $status = ($status == 1) ? 1 : 2;
                $params = ['status' => $status];
                $this->customerRepo->update($customerRow, $params);
            }
            $request = new Request;

            return response()->json(['success' => true, 'message' => trans('core::core.messages.status_change_success')]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => trans('core::core.messages.unexpected_error'),
            ], 500);
        }

    }

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
            $collection = $this->customerRepo->filter($request)->limit($limit);
            $ids = [];
            if ($isSelectAll) {
                $ids = $collection->pluck('id')->toArray();
            } else {
                $collection = $collection->whereIn('id', $selectedIds)->get();
                $ids = $collection->pluck('id')->toArray();
            }
            if (! empty($ids)) {
                $this->customerRepo->whereIn('id', $ids)->delete();
            }
            $this->customerRepo->flushCache(config('customer.cache.name'));
            $this->customerRepo->flushCache(config('customer.cache.deleted_customer_name'));

            return response()->json(['success' => true, 'message' => trans('core::core.messages.mass_delete_success')]);
        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => trans('core::core.messages.unexpected_error'),
            ], 500);
        }

    }
}
