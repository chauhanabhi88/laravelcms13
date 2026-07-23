<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\User\Http\Requests\LoginRequest;
use Modules\User\Models\User;

class AuthController extends Controller
{
    protected $redirectTo = '';

    public function __construct()
    {
        $this->redirectTo = route(config('user.redirect_route_after_login'), updateUrlParams());
    }

    public function index()
    {
        return view('user::auth.login');
    }

    public function postLogin(LoginRequest $request)
    {
        $this->ensureIsNotRateLimited($request);

        $user = User::where('email', $request->email)->first();

        if ($user && empty($user->role_id)) {
            $this->recordFailedAttempt($request);
            throw ValidationException::withMessages([$this->username() => __(trans('user::user.messages.user_not_any_role'))]);
        }
        if ($user && $user->status == config('core.disabled')) {
            $this->recordFailedAttempt($request);
            throw ValidationException::withMessages([$this->username() => __(trans('user::user.messages.user_disabled'))]);
        }

        $credentials = $request->only($this->username(), 'password');

        if ($this->guard()->attempt($credentials, $request->filled('remember'))) {
            RateLimiter::clear($this->throttleKey($request));

            $request->session()->regenerate();

            return $this->authenticated($request, $this->guard()->user())
                ?: redirect()->intended(route(config('user.redirect_route_after_login'), updateUrlParams()));
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->recordFailedAttempt($request);

        throw ValidationException::withMessages([
            $this->username() => [trans('user::user.messages.wrong_credentials')],
        ]);
    }

    /**
     * Reject the request when this IP has burned through its failed login attempts.
     *
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, (int) config('user.login_throttle.max_attempts'))) {
            return;
        }

        throw ValidationException::withMessages([
            $this->username() => [trans('user::user.messages.throttled', ['seconds' => RateLimiter::availableIn($key)])],
        ]);
    }

    /**
     * Count a rejected login against this IP. Covers the disabled/no-role branches
     * too, so they cannot be used as an unmetered account probe.
     */
    protected function recordFailedAttempt(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), (int) config('user.login_throttle.decay_seconds'));
    }

    /**
     * Rate limiter key for the login form, keyed on the client IP.
     */
    protected function throttleKey(Request $request): string
    {
        return 'login:'.$request->ip();
    }

    /**
     * The user has been authenticated.
     *
     * @param  Request  $request
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

        return redirect()->route(config('user.redirect_route_not_logged_in'), updateUrlParams());
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return config('user.login_column');
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }
}
