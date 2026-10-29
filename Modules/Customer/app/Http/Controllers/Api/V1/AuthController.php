<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\AccessToken;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Core\Services\Otp\OtpSendStatus;
use Modules\Core\Services\Otp\OtpService;
use Modules\Core\Support\Api\ApiCodes;
use Modules\Customer\Exceptions\CustomerAuthException;
use Modules\Customer\Http\Requests\Api\VerifyOtpRequest;
use Modules\Customer\Http\Requests\SendOtpRequest;
use Modules\Customer\Models\CustomerDeviceToken;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Customer\Support\PhoneNumber;
use Modules\Customer\Transformers\CustomerResource;
use Throwable;

/**
 * Mobile OTP sign-in (plan M08 §5.8): POST otp/send → POST otp/verify issues a personal
 * access token with the "customer" scope; POST logout revokes it.
 */
class AuthController extends ApiController
{
    public function __construct(private readonly CustomerAuthService $auth) {}

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $channel = $request->channel();
        $identifier = $request->identifier();
        $result = $this->auth->sendLoginCode($channel, $identifier, $request->ip());

        if ($result->status === OtpSendStatus::Throttled) {
            return $this->error(trans('customer::customer_front.otp.throttled', ['minutes' => max(1, (int) ceil($result->retryAfter / 60))]), 429, ['retry_after' => $result->retryAfter]);
        }

        if ($result->status === OtpSendStatus::Failed) {
            return $this->error(trans('customer::customer_front.otp.send_failed'), 503);
        }

        $masked = $channel === OtpService::CHANNEL_EMAIL ? PhoneNumber::maskEmail($identifier) : PhoneNumber::mask($identifier);

        return $this->ok(
            [
                'channel' => $channel,
                'masked' => $masked,
                'resend_in' => $result->status === OtpSendStatus::Sent ? (int) config('core.otp.resend_cooldown_seconds', 30) : $result->retryAfter,
                'expires_in' => (int) config('core.otp.expires_minutes', 5) * 60,
            ],
            message: $result->status === OtpSendStatus::Sent
                ? trans('customer::customer_front.otp.sent', ['to' => $masked])
                : trans('customer::customer_front.otp.wait', ['seconds' => $result->retryAfter]),
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $channel = $request->channel();
        $identifier = $request->identifier();

        if (! $this->auth->verifyLoginCode($identifier, (string) $request->validated('code'))) {
            return $this->error(trans('customer::customer_front.otp.invalid_code'), 422, errors: ['code' => [trans('customer::customer_front.otp.invalid_code')]]);
        }

        try {
            $customer = $this->auth->findOrRegister($channel, $identifier);
        } catch (CustomerAuthException $e) {
            return $this->error($e->getMessage(), 403);
        }

        try {
            $token = $this->auth->issueApiToken($customer, (string) $request->validated('device_name'));
        } catch (Throwable $e) {
            // Missing personal access client or signing keys (php artisan passport:keys).
            Log::error('Customer API token could not be issued: '.$e->getMessage());

            return $this->error(trans('core::core.messages.unexpected_error'), 500);
        }

        $this->rememberDevice($customer->id, $request->device());

        $expiresAt = $token->getToken()?->expires_at;

        return $this->ok(
            [
                'token' => $token->accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $expiresAt instanceof Carbon ? max(0, (int) Carbon::now()->diffInSeconds($expiresAt)) : null,
                'new_customer' => $customer->wasRecentlyCreated,
                'customer' => new CustomerResource($customer),
            ],
            message: trans('customer::customer_front.otp.logged_in'),
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user('api')?->token();

        if ($token instanceof AccessToken) {
            $token->revoke();
        }

        return $this->ok(message: trans('customer::customer_front.otp.logged_out'));
    }

    /**
     * @param  array{device_token: string, device_type: string|null}|null  $device
     */
    protected function rememberDevice(int $customerId, ?array $device): void
    {
        if ($device === null || ! Schema::hasTable((new CustomerDeviceToken)->getTable())) {
            return;
        }

        CustomerDeviceToken::query()->updateOrCreate(
            ['device_token' => $device['device_token']],
            ['customer_id' => $customerId, 'device_type' => $device['device_type']],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $errors
     */
    protected function error(string $message, int $status, array $data = [], array $errors = []): JsonResponse
    {
        return $this->fail($status === 429 ? ApiCodes::OTP_RATE_LIMITED : $status, $message, $errors, $status, $data === [] ? [] : ['data' => $data]);
    }
}
