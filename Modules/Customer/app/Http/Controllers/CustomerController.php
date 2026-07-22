<?php
namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Customer\Models\Customer;

use Illuminate\Routing\Controller;
use Illuminate\Http\Response;
use Modules\Customer\Http\Requests\ProfileRequest;
use Modules\Customer\Http\Requests\UpdateRequest;
use Modules\Customer\Http\Requests\ChangePasswordRequest;
use Modules\Customer\Repositories\CustomerRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Modules\Core\Handler\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache as FacadesCache;
use Modules\Core\Cache\FileStore; 
use Illuminate\Support\Facades\Auth;

class CustomerController extends FrontendController
{
    private $log;
	
    public function __construct(Log $log, CustomerRepository $customer, Customer $customerEntity)
    {
        parent::__construct();
        $this->customer = $customer;
        $this->customerEntity = $customerEntity;
        $this->log = $log;
    }

    public function checkOnlineOfflineLog()
    {
        if (Auth::guard('customer')->check()) {
            $fileStore = new FileStore(FacadesCache::getFilesystem(), storage_path('framework/cache/customer'));
            $expireSeconds = (int)settings('customer', 'ajax_call_after_seconds');
            $fileStore->put('customer-is-online-' . Auth::guard('customer')->user()->id, true, now()->addSeconds($expireSeconds));
            return response()->json(['isLogin' => 1 ,'afterSeconds' => ($expireSeconds * 1000)]);
        }
    }

    /**
     * this function is used for show account information.
     *
     * @var string
     */
    public function myaccount()
    {
    	$getCustomerInfo = $this->customer->getLoginUserInfo();
      return view('customer::frontend.account.myaccount', compact('getCustomerInfo'));
    }
    /**
     * this function is used for show user profile  information.
     *
     * @var string
     */
    public function profile($locale)
    {
        try {
            $getLoginCustomerInfo = $this->customer->getLoginUserInfo();
            if(empty($getLoginCustomerInfo)){
                throw new \Exception(trans("customer::customer.messages.data_invalid"));
            }else{
                $id = $getLoginCustomerInfo->id;
            }
            $getCustomerInfo = $this->customer->find($id);
            return view('customer::frontend.account.editprofile', compact('getCustomerInfo'));
        } catch (\Throwable $e) {
            $this->log->generateLog('error', "CustomerController_profile: ".$e->getMessage() , ['error' => $e->getTraceAsString()], \config("customer.customer_general_front"));
            return redirect()->route('homepage', updateUrlParams(['type' => config('core.route_type')]))->with("error", $e->getMessage());
        }
    }

    /**
     * Update the user profile value
     * @param Request $request
     * @param int $id
     * @return Response
     */
   public function update(ProfileRequest $request, $locale, $id)
    {
       try
        {
            $getCustomerInfo = $this->customer->getLoginUserInfo();
            if(empty($getCustomerInfo)){
                throw new \Exception(trans("customer::customer.messages.data_invalid"));
            }else{
                $id = $getCustomerInfo->id;
            }
            $params = $request->all();
            $customer = $this->customer->find($id);
            // if(!$customer) {
            //     throw new \Exception(trans("customer::customer.messages.data_invalid"));
            // }
            $checkEmailInTransh = Customer::onlyTrashed()->where('email',$request->email)->get()->first();
            /*customer is not registred in our system */
            if(!$customer && empty($checkEmailInTransh)){
               return redirect()->route('customer.login',updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer.messages.customer_notfound_account"));
            }
            if(!empty($customer) && !empty($checkEmailInTransh)){
                return redirect()->route('customer.profile.edit', updateUrlParams(['type' => config('core.route_type')]))->with("error",  trans("customer::customer.messages.soft_delete_email_msg"));
            }
           /* if(isset($params['remove_profile_picture']) && $params['remove_profile_picture'])
            {
                $imageRemoveParams = array(
                    'module_name' => \Config::get('customer.name'),
                    'dbfield' => 'profile_picture'
                );
                $this->customer->setUploadParams($imageRemoveParams)->setModel($customer)->removeFile();
                $params['profile_picture'] = null;
            }
            */

            $request->merge($params);
            if($request->file('profile_picture'))
            {
                $imageUploadParams = array(
                    'module_name' => \Config::get('customer.name'),
                    'dbfield' => 'profile_picture',
                    'thumbnail' => true,
                    'thumbnail_size' => 100
                );
                $params = $this->customer->setUploadParams($imageUploadParams)->uploadImage($request);
                $params['profile_picture'] = $params['profile_picture'];
            }
            $this->customer->update($customer, $params);
            return redirect()->route('customer.myaccount', updateUrlParams(['type' => config('core.route_type')]))->with("success", trans("customer::customer.messages.updated_success"));
        } catch (\Throwable $e) {
            $this->log->generateLog('error', "CustomerController_update: " . $e->getMessage(), ['error' => $e->getTraceAsString()], \config("customer.customer_general_front"));
            return redirect()->route('customer.profile.edit', updateUrlParams(['type' => config('core.route_type')]))->with("error", $e->getMessage());
        }
    }
    public function changePassword($local) {
        return view('customer::frontend.account.changepassword');
    }

    public function updatePassword(ChangePasswordRequest $request) {
       if(!empty($request->input('user_id'))) {
            $id = decrypt_It($request->input('user_id'));
        }
        try{
            $getCustomerInfo = $this->customer->getLoginUserInfo();
            if(!empty($getCustomerInfo)) {
                $id = $getCustomerInfo->id;
            }
            $params = $request->all();
            $customer = $this->customer->find($id);
            if(!$customer) {
                throw new \Exception(trans("customer::customer.messages.data_invalid"));
            }

            $password = hash_hmac("sha256", $params['password'], \Config::get('core.encrypt.password'));
            if(isset($params['old_password']) && $params['old_password']) {
                $oldPassword = Hash::make($params['old_password']);
            }
            if(password_verify($params['old_password'], $customer->password) ) {
                if(password_verify($params['password'], $customer->password)){
                     return redirect()->route('customer.change-password', updateUrlParams(['type' => config('core.route_type')]))->with("error",trans('customer::customer.messages.password_exist'));
                }else{
                    if(isset($params['password']) && $params['password']) {
                        $params['password'] = Hash::make($params['password']);
                    }
                    $this->customer->update($customer, $params);
                     return redirect()->route('customer.change-password', updateUrlParams(['type' => config('core.route_type')]))->with("success",trans('customer::customer.messages.password_update'));
                }

            }else{
                 return redirect()->route('customer.change-password', updateUrlParams(['type' => config('core.route_type')]))->with("error",trans('customer::customer.messages.old_password_invalid'));
            }
        }catch(\Throwable $e) {
            $this->log->generateLog('error', "CustomerController_updatePassword: " . $e->getMessage(), ['error' => $e->getTraceAsString()], \config("customer.customer_general_front"));
            return redirect()->route('customer.change-password', updateUrlParams(['type' => config('core.route_type')]))->with("error", $e->getMessage());
        }
    }
    public function stripePayment(){
        return view('customer::frontend.account.payment-form');
    }
}
