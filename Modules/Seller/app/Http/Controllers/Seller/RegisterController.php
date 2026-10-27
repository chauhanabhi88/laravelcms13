<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Modules\Core\Services\Otp\OtpSendResult;
use Modules\Core\Services\Otp\OtpSendStatus;
use Modules\Core\Services\Otp\OtpService;
use Modules\Seller\Http\Requests\Seller\RegisterRequest;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Services\SellerRegistrationService;
use Modules\Seller\Support\Phone;

/**
 * Supplier registration: details → OTP to the mobile (purpose "seller_register") →
 * seller in "onboarding" with its owner login → signed in → wizard.
 *
 * Until the OTP is verified the details wait in the session (password already hashed).
 */
class RegisterController extends SellerPanelController
{
    private const SESSION_KEY = 'seller.registration';

    private const PENDING_MINUTES = 30;

    public function __construct(
        private readonly OtpService $otp,
        private readonly SellerRegistrationService $registration,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::guard('seller')->check()) {
            return redirect()->to(frontRoute('seller.dashboard'));
        }

        return view('seller::seller.auth.register');
    }

    public function start(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $phone = (string) $validated['phone_e164'];

        $result = $this->otp->send(OtpService::CHANNEL_SMS, $phone, 'seller_register', $request->ip());

        if (! $result->successful() && $result->status !== OtpSendStatus::Cooldown) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', $this->sendError($result));
        }

        $request->session()->put(self::SESSION_KEY, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $phone,
            'password_hash' => Hash::make((string) $validated['password']),
            'expires_at' => now()->addMinutes(self::PENDING_MINUTES)->getTimestamp(),
        ]);

        return redirect()->to(frontRoute('seller.register.verify'))
            ->with('success', trans('seller::auth.messages.otp_sent', ['phone' => Phone::display($phone)]));
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->to(frontRoute('seller.register'))->with('error', trans('seller::auth.messages.registration_expired'));
        }

        return view('seller::seller.auth.verify', [
            'phone' => Phone::display($pending['phone']),
            'cooldown' => (int) config('core.otp.resend_cooldown_seconds', 30),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->to(frontRoute('seller.register'))->with('error', trans('seller::auth.messages.registration_expired'));
        }

        $request->validate(['otp' => ['required', 'digits:'.config('core.otp.length', 6)]]);

        if (! $this->otp->verify($pending['phone'], 'seller_register', (string) $request->input('otp'))) {
            return back()->withErrors(['otp' => trans('seller::auth.messages.invalid_otp')]);
        }

        // Someone may have registered the same email or mobile while the code was in flight.
        $taken = SellerUser::query()->where('email', $pending['email'])->orWhere('phone', $pending['phone'])->exists();
        if ($taken) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->to(frontRoute('seller.login'))->with('error', trans('seller::auth.messages.already_registered'));
        }

        $user = $this->registration->register($pending);
        $request->session()->forget(self::SESSION_KEY);

        Auth::guard('seller')->login($user);
        $request->session()->regenerate();
        $this->current()->refresh();

        return redirect()->to(frontRoute('seller.onboarding.show', [1]))
            ->with('success', trans('seller::auth.messages.registered'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->to(frontRoute('seller.register'))->with('error', trans('seller::auth.messages.registration_expired'));
        }

        $result = $this->otp->send(OtpService::CHANNEL_SMS, $pending['phone'], 'seller_register', $request->ip());

        return $result->successful()
            ? back()->with('success', trans('seller::auth.messages.otp_sent', ['phone' => Phone::display($pending['phone'])]))
            : back()->with('error', $this->sendError($result));
    }

    /**
     * @return array{name: string, email: string, phone: string, password_hash: string, expires_at: int}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $pending;
    }

    private function sendError(OtpSendResult $result): string
    {
        return match ($result->status) {
            OtpSendStatus::Cooldown, OtpSendStatus::Throttled => trans('seller::auth.messages.otp_wait', ['seconds' => $result->retryAfter]),
            default => trans('seller::auth.messages.otp_failed'),
        };
    }
}
