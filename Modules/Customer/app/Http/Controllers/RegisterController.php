<?php
namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Customer\Models\Customer;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Customer\Http\Requests\RegisterRequest;
use Modules\Customer\Emails\CustomerRegistrationAdminMail;
use Modules\Customer\Emails\CustomerEmailVerifyMail;

class RegisterController extends FrontendController
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
     * this function is used for show registration page .
     *
     * @var string
     */
    public function index()
    {
        return view('customer::frontend.auth.register', compact("bodyClassName"));
    }

    /**
     * this function is for verification of customer email.
     *
     * @var string
    */
    public function emailVerification($locale, $encryptid)
    {
        try {
            $id = decrypt_It($encryptid);
            $customer = $this->customer->find($id);
            if(!$customer) {
                return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('warning', trans("customer::customer.messages.email_verification_fail"));
            }
            $is_new = $customer->is_new;
            $status = $customer->status;
            $email_verified_at = $customer->email_verified_at;

            /* Already Verified Customer */
            if($is_new == config('customer.is_new.no') && $status == config('customer.enabled_status') && !empty($email_verified_at)){
                return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('success', trans("customer::customer.messages.email_verification_already_reg"));
            }

            $data['is_new'] = config('customer.is_new.no');
            $data['status'] = config('customer.enabled_status');
            $data['email_verified_at'] = Carbon::now();
            $this->customer->update($customer, $data);
            return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('success', trans("customer::customer.messages.email_verification_success"));
        } catch (\Throwable $e) {
            $this->log->generateLog('error', "RegisterController_emailVerification: " . $e->getMessage(), ['error' => $e->getTraceAsString()], \config("customer.customer_general_front"));
            return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with("error", $e->getMessage());
        }
    }

    /**
     * this function is store registration information .
     *
     * @var string
    */
    public function postRegister(RegisterRequest $request) {
        try {
            $email_verification = settings('core','email_verification');

            /* In backend Email Verification is Setting is off then We will Change Status to Enable  and Is New Off*/
            $status = ($email_verification == config('customer.email_verification.no')) ? config('customer.enabled_status') : config('customer.disabled_status');
            $isNew = ($email_verification == config('customer.email_verification.no')) ? config('customer.is_new.no') : config('customer.is_new.yes');
            $mailSend = ($email_verification == config('customer.email_verification.no')) ? false : true;
            $signupType = ($email_verification == config('customer.email_verification.no')) ? config('customer.signup_type.auto_active.code') : config('customer.signup_type.email_verification.code');

           // echo "<pre>";print_r(config('customer.signup_type'));
            $params = $request->all();
            $checkEmailInTransh = Customer::onlyTrashed()->where('email',$params['email'])->get()->first();

            /*customer is  registred in our system but added in softdelete*/
            if( !empty($checkEmailInTransh)){
                return redirect()->route('customer.signup', updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer.messages.soft_delete_email_msg"));
            }
            if(isset($params['password']) && $params['password']) {
                $params['password'] = Hash::make($params['password']);
            }
            $params['status'] = $status;
            $params['is_new'] = $isNew;
            $params['signup_type'] = $signupType;
            $params['contact_number'] = $request->get('contact_number');
            $customer = $this->customer->create($params);
            //echo "<pre>";print_r($params);die;
            if($mailSend){
                /* Verfication On then Send Verification link to Customer */
                Mail::send(new CustomerEmailVerifyMail($customer));
            }
            /* Notification Mail Send to Admin*/
            Mail::send(new CustomerRegistrationAdminMail($customer));
           return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]));
        }catch (\Throwable $e) {
            $this->log->generateLog('error', "RegisterController_postRegister: " . $e->getMessage(), ['error' => $e->getTraceAsString()], \config("customer.customer_general_front"));
            return redirect()->route('customer.signup', updateUrlParams(['type' => config('core.route_type')]))->with("error", $e->getMessage());
        }
    }
}

/* Logic Of Signup Customer:

    Signup
    - is email veri. on
        - send mail to user
            - email_verified_at
            - status active
            - is new off
        - send mail to admin
        - status inactive
        - is New on
    - is email veri. off
        - send mail to admin
        - status active
        - is New off
        - user will manually verify email
*/
