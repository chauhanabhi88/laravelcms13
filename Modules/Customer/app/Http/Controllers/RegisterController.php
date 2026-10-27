<?php

namespace Modules\Customer\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Handler\Log;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Customer\Emails\CustomerEmailVerifyMail;
use Modules\Customer\Emails\CustomerRegistrationAdminMail;
use Modules\Customer\Http\Requests\RegisterRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Repositories\CustomerRepository;

class RegisterController extends FrontendController
{
    protected $customer = null;

    protected $customerEntity = null;

    protected $log = null;

    public function __construct(CustomerRepository $customer, Customer $customerEntity, Log $log)
    {
        parent::__construct();
        $this->customer = $customer;
        $this->customerEntity = $customerEntity;
        $this->log = $log;
    }

    /**
     * this function is used for show registration page .
     *
     * @var string
     */
    public function index()
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route(config('customer.redirect_route_after_login'), updateUrlParams(['type' => config('core.route_type')]));
        }

        return view('customer::frontend.auth.register');
    }

    /**
     * this function is for verification of customer email.
     *
     * @var string
     */
    public function emailVerification(Request $request)
    {
        try {
            $id = decrypt_It($request->route('id'));
            $customer = $this->customer->find($id);
            if (! $customer) {
                return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('warning', trans('customer::customer.messages.email_verification_fail'));
            }
            /* Already Verified Customer */
            if ($customer->status == config('customer.enabled_status') && ! empty($customer->email_verified_at)) {
                return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('success', trans('customer::customer.messages.email_verification_already_reg'));
            }

            $data['status'] = config('customer.enabled_status');
            $data['email_verified_at'] = Carbon::now();
            $this->customer->update($customer, $data);

            return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('success', trans('customer::customer.messages.email_verification_success'));
        } catch (\Throwable $e) {
            $this->log->generateLog('error', 'RegisterController_emailVerification: '.$e->getMessage(), ['error' => $e->getTraceAsString()], \config('customer.customer_general_front'));

            return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('error', $e->getMessage());
        }
    }

    /**
     * this function is store registration information .
     *
     * @var string
     */
    public function postRegister(RegisterRequest $request)
    {
        try {
            /* Email verification off: the account is active straight away. On: it stays disabled until the emailed link is used. */
            $mailSend = Customer::requiresEmailVerification();
            $status = $mailSend ? config('customer.disabled_status') : config('customer.enabled_status');

            // echo "<pre>";print_r(config('customer.signup_type'));
            $params = $request->all();
            $checkEmailInTransh = Customer::onlyTrashed()->where('email', $params['email'])->get()->first();

            /* customer is  registred in our system but added in softdelete */
            if (! empty($checkEmailInTransh)) {
                return redirect()->route('customer.signup', updateUrlParams(['type' => config('core.route_type')]))->with('error', trans('customer::customer.messages.soft_delete_email_msg'));
            }
            if (isset($params['password']) && $params['password']) {
                $params['password'] = Hash::make($params['password']);
            }
            $params = $request->only('first_name', 'last_name', 'email', 'contact_number') + [
                'password' => $params['password'],
                'status' => $status,
            ];
            $customer = $this->customer->create($params);
            // echo "<pre>";print_r($params);die;
            /* The account exists at this point - a mail failure must not surface as a failed signup */
            try {
                if ($mailSend) {
                    /* Verfication On then Send Verification link to Customer */
                    Mail::send(new CustomerEmailVerifyMail($customer));
                }
                /* Notification Mail Send to Admin */
                Mail::send(new CustomerRegistrationAdminMail($customer));
            } catch (\Throwable $mailException) {
                $this->log->generateLog('error', 'RegisterController_postRegister_mail: '.$mailException->getMessage(), ['error' => $mailException->getTraceAsString()], \config('customer.customer_general_front'));
            }

            return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))
                ->with('success', trans($mailSend ? 'customer::customer_front.messages.signup_verify' : 'customer::customer_front.messages.signup_success'));
        } catch (\Throwable $e) {
            $this->log->generateLog('error', 'RegisterController_postRegister: '.$e->getMessage(), ['error' => $e->getTraceAsString()], \config('customer.customer_general_front'));

            return redirect()->route('customer.signup', updateUrlParams(['type' => config('core.route_type')]))
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('error', trans('core::core.messages.unexpected_error'));
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
