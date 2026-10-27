<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Services\Otp\OtpSendResult;
use Modules\Core\Services\Otp\OtpSendStatus;
use Modules\Core\Services\Otp\OtpService;
use Modules\Customer\Exceptions\CustomerAuthException;
use Modules\Customer\Http\Requests\SendOtpRequest;
use Modules\Customer\Http\Requests\VerifyOtpRequest;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Customer\Support\CustomerSettings;
use Modules\Customer\Support\PhoneNumber;

/**
 * "Login or sign up" with a mobile (or email) OTP (FR-CUS-001, plan M08 §5.1):
 * identifier → code sent → 6-digit code → signed in (new numbers are registered).
 * Every action also answers JSON, which the login drawer uses.
 */
class OtpLoginController extends Controller
{
    public const SESSION_KEY = 'customer_otp';

    public function __construct(private readonly CustomerAuthService $auth) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->to($this->homeUrl());
        }

        $this->rememberIntended($request);

        $emailOtpEnabled = CustomerSettings::emailOtpEnabled();

        return view('customer::frontend.auth.otp-login', [
            'emailOtpEnabled' => $emailOtpEnabled,
            'passwordLoginEnabled' => CustomerSettings::passwordLoginEnabled(),
            'channel' => old('channel', $emailOtpEnabled && $request->query('channel') === OtpService::CHANNEL_EMAIL ? OtpService::CHANNEL_EMAIL : OtpService::CHANNEL_SMS),
        ]);
    }

    public function send(SendOtpRequest $request): RedirectResponse|JsonResponse
    {
        $channel = $request->channel();
        $identifier = $request->identifier();
        $result = $this->auth->sendLoginCode($channel, $identifier, $request->ip());

        if ($result->status === OtpSendStatus::Throttled || $result->status === OtpSendStatus::Failed) {
            return $this->sendFailed($request, $result, $channel);
        }

        $resendIn = $result->status === OtpSendStatus::Sent ? $this->cooldown() : $result->retryAfter;

        $request->session()->put(self::SESSION_KEY, [
            'channel' => $channel,
            'identifier' => $identifier,
            'resend_at' => time() + $resendIn,
        ]);

        $message = $result->status === OtpSendStatus::Sent
            ? trans('customer::customer_front.otp.sent', ['to' => $this->mask($channel, $identifier)])
            : trans('customer::customer_front.otp.wait', ['seconds' => $result->retryAfter]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'channel' => $channel,
                'masked' => $this->mask($channel, $identifier),
                'resend_in' => $resendIn,
            ]);
        }

        return redirect()->to(frontRoute('customer.otp.verify.form'))
            ->with($result->status === OtpSendStatus::Sent ? 'success' : 'warning', $message);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->to($this->homeUrl());
        }

        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->to(frontRoute('customer.login'));
        }

        return view('customer::frontend.auth.otp-verify', [
            'channel' => $pending['channel'],
            'masked' => $this->mask($pending['channel'], $pending['identifier']),
            'resendValue' => $pending['channel'] === OtpService::CHANNEL_SMS ? PhoneNumber::national($pending['identifier']) : $pending['identifier'],
            'resendIn' => max(0, (int) $pending['resend_at'] - time()),
            'codeLength' => (int) config('core.otp.length', 6),
        ]);
    }

    public function verify(VerifyOtpRequest $request): RedirectResponse|JsonResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return $this->verifyFailed($request, trans('customer::customer_front.otp.session_expired'), restart: true);
        }

        if (! $this->auth->verifyLoginCode($pending['identifier'], (string) $request->validated('code'))) {
            return $this->verifyFailed($request, trans('customer::customer_front.otp.invalid_code'));
        }

        $request->session()->forget(self::SESSION_KEY);

        try {
            $customer = $this->auth->findOrRegister($pending['channel'], $pending['identifier']);
        } catch (CustomerAuthException $e) {
            return $this->verifyFailed($request, $e->getMessage(), restart: true);
        }

        $this->auth->login($customer, $request, $this->auth->methodFor($pending['channel']));

        $needsProfile = $customer->needsProfileCompletion();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => trans('customer::customer_front.otp.logged_in'),
                'new_customer' => $customer->wasRecentlyCreated,
                'needs_profile' => $needsProfile,
                'redirect' => $needsProfile ? frontRoute('customer.profile.complete') : $request->session()->pull('url.intended', $this->homeUrl()),
            ]);
        }

        if ($needsProfile) {
            return redirect()->to(frontRoute('customer.profile.complete'));
        }

        return redirect()->intended($this->homeUrl());
    }

    /**
     * ?redirect= lets pages (e.g. blog comments) send guests back after login - same host only.
     */
    protected function rememberIntended(Request $request): void
    {
        $target = (string) $request->query('redirect');

        if ($target !== '' && parse_url($target, PHP_URL_HOST) === $request->getHost()) {
            $request->session()->put('url.intended', $target);
        }
    }

    /**
     * @return array{channel: string, identifier: string, resend_at: int}|null
     */
    protected function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return is_array($pending) && isset($pending['channel'], $pending['identifier'], $pending['resend_at']) ? $pending : null;
    }

    protected function sendFailed(SendOtpRequest $request, OtpSendResult $result, string $channel): RedirectResponse|JsonResponse
    {
        $throttled = $result->status === OtpSendStatus::Throttled;
        $message = $throttled
            ? trans('customer::customer_front.otp.throttled', ['minutes' => max(1, (int) ceil($result->retryAfter / 60))])
            : trans('customer::customer_front.otp.send_failed');
        $field = $channel === OtpService::CHANNEL_EMAIL ? 'email' : 'phone';

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => [$field => [$message]]], $throttled ? 429 : 503);
        }

        return redirect()->to(frontRoute('customer.login'))
            ->withInput($request->only('channel', 'phone', 'email'))
            ->withErrors([$field => $message]);
    }

    protected function verifyFailed(Request $request, string $message, bool $restart = false): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'restart' => $restart, 'errors' => ['code' => [$message]]], 422);
        }

        if ($restart) {
            return redirect()->to(frontRoute('customer.login'))->with('error', $message);
        }

        return redirect()->to(frontRoute('customer.otp.verify.form'))->withErrors(['code' => $message]);
    }

    protected function mask(string $channel, string $identifier): string
    {
        return $channel === OtpService::CHANNEL_EMAIL ? PhoneNumber::maskEmail($identifier) : PhoneNumber::mask($identifier);
    }

    protected function cooldown(): int
    {
        return (int) config('core.otp.resend_cooldown_seconds', 30);
    }

    protected function homeUrl(): string
    {
        return frontRoute(config('customer.redirect_route_after_login'));
    }
}
