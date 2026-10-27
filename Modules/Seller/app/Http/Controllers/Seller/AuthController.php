<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Modules\Core\Services\Otp\OtpSendStatus;
use Modules\Core\Services\Otp\OtpService;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Support\Phone;

/**
 * Supplier sign-in with email or mobile + password, or mobile + OTP (purpose
 * "seller_login"). Failed attempts are rate-limited per identifier and IP.
 */
class AuthController extends SellerPanelController
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly OtpService $otp) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('seller')->check()) {
            return redirect()->to(frontRoute('seller.dashboard'));
        }

        return view('seller::seller.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (Auth::guard('seller')->check()) {
            return redirect()->to(frontRoute('seller.dashboard'));
        }

        $usesOtp = $request->input('method') === 'otp';

        $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => [$usesOtp ? 'nullable' : 'required', 'string', 'max:191'],
            'otp' => [$usesOtp ? 'required' : 'nullable', 'digits:'.config('core.otp.length', 6)],
        ]);

        $throttleKey = 'seller-login:'.sha1(strtolower((string) $request->input('login')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return $this->failed($request, trans('seller::auth.messages.throttled', ['seconds' => RateLimiter::availableIn($throttleKey)]));
        }

        $user = $this->findUser((string) $request->input('login'));
        $valid = $user !== null && ($usesOtp
            ? $this->otp->verify($user->phone, 'seller_login', (string) $request->input('otp'))
            : Hash::check((string) $request->input('password'), $user->password));

        if (! $valid) {
            RateLimiter::hit($throttleKey, 300);

            return $this->failed($request, trans($usesOtp ? 'seller::auth.messages.invalid_otp' : 'seller::auth.messages.invalid_credentials'));
        }

        if (! $user->isEnabled()) {
            return $this->failed($request, trans('seller::auth.messages.account_disabled'));
        }

        RateLimiter::clear($throttleKey);
        Auth::guard('seller')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => Carbon::now()])->save();
        $this->current()->refresh();

        return redirect()->intended(frontRoute('seller.dashboard'));
    }

    /**
     * Send a sign-in code to a registered mobile. The answer is the same for unknown numbers.
     */
    public function sendLoginOtp(Request $request): RedirectResponse
    {
        $request->validate(['login' => ['required', 'string', 'max:20']]);

        $phone = Phone::normalise($request->input('login'));

        if ($phone === null) {
            return back()->withInput(['login' => $request->input('login'), 'method' => 'otp'])
                ->withErrors(['login' => trans('seller::validation.phone.invalid')]);
        }

        $user = SellerUser::query()->where('phone', $phone)->first();

        if ($user !== null && $user->isEnabled()) {
            $result = $this->otp->send(OtpService::CHANNEL_SMS, $phone, 'seller_login', $request->ip());

            if (in_array($result->status, [OtpSendStatus::Cooldown, OtpSendStatus::Throttled], true)) {
                return back()->withInput(['login' => $request->input('login'), 'method' => 'otp'])
                    ->with('error', trans('seller::auth.messages.otp_wait', ['seconds' => $result->retryAfter]));
            }
        }

        return back()->withInput(['login' => $request->input('login'), 'method' => 'otp', 'otp_sent' => 1])
            ->with('success', trans('seller::auth.messages.login_otp_sent'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('seller')->logout();
        $request->session()->regenerateToken();
        $this->current()->refresh();

        return redirect()->to(frontRoute('seller.login'))->with('success', trans('seller::auth.messages.logged_out'));
    }

    private function findUser(string $login): ?SellerUser
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return SellerUser::query()->with('seller')->where('email', strtolower($login))->first();
        }

        $phone = Phone::normalise($login);

        return $phone === null ? null : SellerUser::query()->with('seller')->where('phone', $phone)->first();
    }

    private function failed(Request $request, string $message): RedirectResponse
    {
        return redirect()->to(frontRoute('seller.login'))
            ->withInput($request->only('login', 'remember', 'method'))
            ->withErrors(['login' => $message]);
    }
}
