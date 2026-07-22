<?php

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Modules\Customer\Models\CustomerResetPassword;
use Modules\Customer\Repositories\CustomerRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */
    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(CustomerResetPassword $customerResetPwd, CustomerRepository $customer)
    {
        $this->customerResetPwd = $customerResetPwd;
        $this->customer = $customer;
    }

    public function showResetForm(Request $request)
    {
        try {
            $token = $request->token;
            $expireTime = config('auth.passwords.customers.expire');
            $expireTime = isset($expireTime) && !empty($expireTime) ? '+' . $expireTime . 'minutes' : '';
            $email = $request->email;
            $getTokenCreatedDate = $this->customerResetPwd->where('email', $request->email)->get()->first();
            if (empty($getTokenCreatedDate)) {
                return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer_front.messages.invalid_token"));
            }
            $tokenDate = date('Y-m-d H:i:s ', strtotime($expireTime, strtotime($getTokenCreatedDate->created_at)));
            $currentTime = date('Y-m-d H:i:s');
            if ($currentTime > $tokenDate) {
                $token = $email = '';
                return redirect()->route('customer.login', updateUrlParams(['type' => config('core.route_type')]))->with('error', trans("customer::customer_front.messages.expire_token"));
            }
            $getCustomerInfo = $this->customer->getLoginUserInfo();
            return view('customer::frontend.auth.reset_password', compact('getCustomerInfo'))->with(
                ['token' => $token, 'email' => $email]
            );
        } catch(\Exception $e) {
            return redirect('customer.login', updateUrlParams());
        }
    }

    public function reset(Request $request)
    {
        $request->validate($this->rules());

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = $this->broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {

                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route("customer.login", updateUrlParams(['type' => config('core.route_type')]))->with('status', __($status))
                    : back()->withInput($request->only('email'))
                            ->withErrors(['email' => __($status)]);
    }

    protected function rules()
    {
        $minPasswordLength = settings('customer', 'min_password_length');
        return [
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'confirmed',
                'min:' . $minPasswordLength,             // must be at least 10 characters in length
                'max:' .  settings('customer', 'max_password_length'),
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:' . config('core.special_character_regex_server'), // must contain a special character
            ],
        ];
    }

    public function validationErrorMessages()
    {
        return [
            'password.min' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.regex' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.max' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
        ];
    }

    /**
     * password broker for customer guard.
     *
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker()
    {
        return Password::broker('customers');
    }

    /**
     * Get the guard to be used during authentication
     * after password reset.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    /* public function guard()
    {
        return Auth::guard('customer');
    } */
}
