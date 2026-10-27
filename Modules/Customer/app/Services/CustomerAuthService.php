<?php

declare(strict_types=1);

namespace Modules\Customer\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Passport\PersonalAccessTokenResult;
use Modules\Core\Services\Otp\OtpSendResult;
use Modules\Core\Services\Otp\OtpService;
use Modules\Customer\Events\CustomerLoggedIn;
use Modules\Customer\Events\CustomerRegistered;
use Modules\Customer\Exceptions\CustomerAuthException;
use Modules\Customer\Models\Customer;
use Modules\Customer\Repositories\CustomerLoginLogRepository;
use Modules\Customer\Support\PhoneNumber;
use Throwable;

/**
 * Phone-first customer sign-in (FR-CUS-001): send and check the OTP (purpose
 * "customer_login", rules from Core's OtpService), find the account or create it on first
 * verification, then log in on the session guard or issue an API token.
 */
class CustomerAuthService
{
    public const PURPOSE_LOGIN = 'customer_login';

    public const API_SCOPE = 'customer';

    public function __construct(private readonly OtpService $otp) {}

    /**
     * Canonical identifier for a channel: E.164 for sms (Indian mobiles only), lower case for email.
     *
     * @throws InvalidArgumentException
     */
    public function normaliseIdentifier(string $channel, string $identifier): string
    {
        if ($channel === OtpService::CHANNEL_SMS) {
            return PhoneNumber::normalise($identifier) ?? throw new InvalidArgumentException('Invalid mobile number.');
        }

        return OtpService::normaliseIdentifier($channel, $identifier);
    }

    public function sendLoginCode(string $channel, string $identifier, ?string $ip): OtpSendResult
    {
        return $this->otp->send($channel, $identifier, self::PURPOSE_LOGIN, $ip);
    }

    public function verifyLoginCode(string $identifier, string $code): bool
    {
        return $this->otp->verify($identifier, self::PURPOSE_LOGIN, $code);
    }

    /**
     * The account behind a verified identifier, created on first sign-in. A new account has
     * wasRecentlyCreated = true and fires CustomerRegistered.
     *
     * @throws CustomerAuthException when the account is disabled or deleted
     */
    public function findOrRegister(string $channel, string $identifier): Customer
    {
        $column = $channel === OtpService::CHANNEL_SMS ? 'phone' : 'email';
        $customer = Customer::query()->where($column, $identifier)->first();

        if ($customer === null) {
            if (Customer::onlyTrashed()->where($column, $identifier)->exists()) {
                throw new CustomerAuthException(CustomerAuthException::DELETED);
            }

            $customer = $this->register($column, $identifier);
        }

        if (! $customer->isEnabled()) {
            throw new CustomerAuthException(CustomerAuthException::DISABLED);
        }

        $verifiedColumn = $column.'_verified_at';

        if ($customer->{$verifiedColumn} === null) {
            $customer->forceFill([$verifiedColumn => Carbon::now()])->save();
        }

        return $customer;
    }

    /**
     * Session sign-in: remember-me cookie, fresh session id, login log, then the events.
     */
    public function login(Customer $customer, Request $request, string $method, bool $remember = true): void
    {
        $previousSessionId = $request->hasSession() ? $request->session()->getId() : null;

        Auth::guard('customer')->login($customer, $remember);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $this->recordLogin($customer, $request);
        $this->dispatchEvents($customer, $previousSessionId, $method);
    }

    /**
     * Personal access token with the "customer" scope for the mobile API.
     */
    public function issueApiToken(Customer $customer, string $deviceName): PersonalAccessTokenResult
    {
        $token = $customer->createToken(mb_substr($deviceName, 0, 191), [self::API_SCOPE]);

        $this->dispatchEvents($customer, null, CustomerLoggedIn::METHOD_API);

        return $token;
    }

    public function methodFor(string $channel): string
    {
        return $channel === OtpService::CHANNEL_EMAIL ? CustomerLoggedIn::METHOD_OTP_EMAIL : CustomerLoggedIn::METHOD_OTP_SMS;
    }

    private function register(string $column, string $identifier): Customer
    {
        try {
            return DB::transaction(function () use ($column, $identifier): Customer {
                $customer = new Customer;
                $customer->forceFill([
                    $column => $identifier,
                    $column.'_verified_at' => Carbon::now(),
                    'status' => config('customer.enabled_status'),
                ])->save();

                return $customer;
            });
        } catch (UniqueConstraintViolationException) {
            // Two verifications for the same new number raced; the other one created it.
            return Customer::query()->where($column, $identifier)->firstOrFail();
        }
    }

    private function dispatchEvents(Customer $customer, ?string $previousSessionId, string $method): void
    {
        if ($customer->wasRecentlyCreated) {
            CustomerRegistered::dispatch($customer, $method);
        }

        CustomerLoggedIn::dispatch($customer, $previousSessionId, $method);
    }

    private function recordLogin(Customer $customer, Request $request): void
    {
        try {
            app(CustomerLoginLogRepository::class)->create([
                'ip_address' => $request->ip(),
                'action' => config('customer.customerLoginLogArr.login.action'),
                'customer_id' => $customer->id,
            ]);
        } catch (Throwable $e) {
            // The audit row must never block a sign-in.
            Log::warning('Customer login log failed: '.$e->getMessage());
        }
    }
}
