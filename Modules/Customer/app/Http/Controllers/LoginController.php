<?php
namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Modules\Customer\Http\Requests\LoginRequest;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Customer\Models\Customer;
use Modules\Customer\Emails\CustomerEmailVerifyMail;
use Auth;
use Modules\Core\Handler\Log;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Customer\Repositories\CustomerLoginLogRepository;

class LoginController extends FrontendController
{
    protected $redirectTo = '';
    protected $maxAttempts = 5;
    protected $decayMinutes = 5;
    private $log;

    public function __construct(Log $log)
    {
        $this->log = $log;
        $this->redirectTo = route(config("customer.redirect_route_after_login"), updateUrlParams(['type' => config('core.route_type')]));
    }
    

    public function index()
    {
        \Log::info("12121");
        $bodyClassName = "header-br";
        return view('customer::frontend.auth.login', compact("bodyClassName"));
    }

    public function postLogin(LoginRequest $request)
    {
        try {
            $customer = Customer::where('email', $request->email)->first();
            /*customer is not registred in our system */
            // if(!$customer){
            //    return redirect()->route('customer.login',updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer.messages.customer_notfound_account"));
            // }

            $checkEmailInTransh = Customer::onlyTrashed()->where('email',$request->email)->get()->first();
            /*customer is not registred in our system */
            if(!$customer && empty($checkEmailInTransh)){
            return redirect()->route('customer.login',updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer.messages.customer_notfound_account"));
            }
            /*soft deleted customer */
            if(!$customer && !empty($checkEmailInTransh)){
            return redirect()->route('customer.login',updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer.messages.soft_delete_email_msg"));
            }

            $email_verification = settings('core','email_verification');
            $status = ($email_verification == config('customer.email_verification.no')) ? false : true;

            /* Email Verification On and Status is Disabled */
            if( $customer && $customer->status == config("core.disabled") && $status) {
                Mail::send(new CustomerEmailVerifyMail($customer));
                return redirect()->route('customer.login',updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer.messages.customer_email_verification"));
            }

            /* Email Verification Off and Status is Disabled */
            if( $customer && $customer->status == config("core.disabled") && (!$status)) {
                return redirect()->route('customer.login',updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer.messages.customer_disabled"));
            }
            
            // If the class is using the ThrottlesLogins trait, we can automatically throttle
            // the login attempts for this application. We'll key this by the username and
            // the IP address of the client making these requests into this application.
            // if (method_exists($this, 'hasTooManyLoginAttempts') &&
            // $this->hasTooManyLoginAttempts($request)) {
            //     $this->fireLockoutEvent($request);
                
            //     return $this->sendLockoutResponse($request);
            // }
            $credentials = $request->only($this->username(), 'password');
            
            if (Auth::guard('customers')->attempt($credentials)) {
                $request->session()->regenerate();
                
                // $this->clearLoginAttempts($request);
                
                $customerLoginLogArr = array(
                    'ip_address' => $request->ip(),
                    'action' => \Config::get('customer.customerLoginLogArr.login.action'),
                    'customer_id' => Auth::guard('customer')->id(),
                );
                app(CustomerLoginLogRepository::class)->create($customerLoginLogArr);
                return $this->authenticated($request, Auth::guard('customer')->user())
                ?: redirect()->intended(route(config("customer.redirect_route_after_login"), updateUrlParams(['type' => config('core.route_type')])));
            }
            // If the login attempt was unsuccessful we will increment the number of attempts
            // to login and redirect the customer back to the login form. Of course, when this
            // customer surpasses their maximum number of attempts they will get locked out.
            // $this->incrementLoginAttempts($request);

            return redirect()->route('customer.login',updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer.messages.wrong_credentials"));
        } catch (\Throwable $e) {
            $this->log->generateLog('error', "LoginController_postLogin: " . $e->getMessage(), ['error' => $e->getTraceAsString()], \config("customer.customer_general_front"));
            return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with("error", $e->getMessage());
        }
    }

    /**
     * The Customer has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $customer
     * @return mixed
     */
    protected function authenticated(LoginRequest $request, $customer)
    {
        //
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $customerLoginLogArr = array(
            'ip_address' => $request->ip(),
            'action' => config('customer.customerLoginLogArr.logout.action'),
            'customer_id' => Auth::guard('customer')->id(),
        );
        app(CustomerLoginLogRepository::class)->create($customerLoginLogArr);
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route(config("customer.redirect_route_not_logged_in"),updateUrlParams(['type' => config('core.route_type')]));
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return config("customer.login_column");
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }
}