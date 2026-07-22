<?php

namespace Modules\User\Http\Controllers;

use Illuminate\Http\Request;
use Modules\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\User\Http\Requests\LoginRequest;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $redirectTo = '';
    protected $maxAttempts = 5;
    protected $decayMinutes = 5;

    public function __construct()
    {
        $this->redirectTo = route(config("user.redirect_route_after_login"), updateUrlParams());
    }

    public function index()
    {
        return view('user::auth.login');
    }

    public function postLogin(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();
        
        if ($user && empty($user->role_id)) {
            throw ValidationException::withMessages([$this->username() => __(trans("user::user.messages.user_not_any_role"))]);
        }
        if ($user && $user->status == config("core.disabled")) {
            throw ValidationException::withMessages([$this->username() => __(trans("user::user.messages.user_disabled"))]);
        }

        $credentials = $request->only($this->username(), 'password');
        
        if ($this->guard()->attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            // $this->clearLoginAttempts($request);

            return $this->authenticated($request, $this->guard()->user())
                ?: redirect()->intended(route(config("user.redirect_route_after_login"), updateUrlParams()));
        }
        

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        // $this->incrementLoginAttempts($request);

        throw ValidationException::withMessages([
            $this->username() => [trans('user::user.messages.wrong_credentials')],
        ]);
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(LoginRequest $request, $user)
    {
        //
    }

    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route(config("user.redirect_route_not_logged_in"), updateUrlParams());
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return config("user.login_column");
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
