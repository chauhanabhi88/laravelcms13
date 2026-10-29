<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\CustomerAuthService;

/*
| Shared helpers for the M20 API contract tests (require_once'd: Pest test files with same-named
| global functions would collide). They build on the module helpers so the contract tests run the
| same offline setup the module tests use.
*/

require_once __DIR__.'/../../../Modules/Order/tests/Helpers.php';
require_once __DIR__.'/../../../Modules/Customer/tests/Helpers.php';

if (! function_exists('apiSetup')) {
    /**
     * Offline payments and SMS, free delivery, no throttling noise from earlier tests.
     */
    function apiSetup(): void
    {
        orderSetup();
        customerFakeSms();
    }

    /**
     * A signed-in customer (a transient Passport token with the "customer" scope).
     */
    function apiCustomer(?Customer $customer = null, array $scopes = [CustomerAuthService::API_SCOPE]): Customer
    {
        $customer ??= orderCustomer();
        Passport::actingAs($customer, $scopes, 'api');

        return $customer;
    }

    /**
     * Personal access tokens need signing keys and a personal access client for the "customer"
     * provider (created by the M08 migration; recreated here for an empty schema).
     */
    function apiPassportReady(): void
    {
        if (! is_file(storage_path('oauth-private.key'))) {
            Artisan::call('passport:keys');
        }

        if (! DB::table('oauth_clients')->where('provider', 'customer')->where('revoked', 0)->exists()) {
            $columns = DB::getSchemaBuilder()->getColumnListing('oauth_clients');
            $row = ['id' => (string) Str::orderedUuid(), 'name' => 'Customer test client', 'secret' => Hash::make('secret'), 'provider' => 'customer', 'revoked' => 0, 'created_at' => now(), 'updated_at' => now()];
            $row += in_array('grant_types', $columns, true)
                ? ['grant_types' => json_encode(['personal_access']), 'redirect_uris' => '[]']
                : ['redirect' => '', 'personal_access_client' => 1, 'password_client' => 0];
            DB::table('oauth_clients')->insert(array_intersect_key($row, array_flip($columns)));
        }
    }

    /**
     * Sign in through the real OTP endpoints and return the bearer token.
     */
    function apiLogin(string $phone, string $deviceName): string
    {
        apiPassportReady();
        $sms = customerFakeSms();

        test()->postJson('/api/v1/customer/otp/send', ['phone' => $phone])->assertOk();

        return test()->postJson('/api/v1/customer/otp/verify', [
            'phone' => $phone,
            'code' => $sms->lastOtpFor('+91'.substr(preg_replace('/\D/', '', $phone), -10)),
            'device_name' => $deviceName,
        ])->assertOk()->json('data.token');
    }

    /**
     * A real bearer token for the customer, the way POST otp/verify issues one.
     */
    function apiToken(Customer $customer, string $deviceName): string
    {
        apiPassportReady();

        return app(CustomerAuthService::class)->issueApiToken($customer, $deviceName)->accessToken;
    }

    /**
     * Every value under a money key, as [path => value].
     *
     * @param  array<int|string, mixed>  $payload
     * @param  list<string>  $moneyKeys
     * @return array<string, mixed>
     */
    function apiMoneyValues(array $payload, array $moneyKeys, string $path = ''): array
    {
        $found = [];

        foreach ($payload as $key => $value) {
            $here = $path === '' ? (string) $key : $path.'.'.$key;

            if (is_string($key) && in_array($key, $moneyKeys, true) && ! is_array($value)) {
                $found[$here] = $value;
            }

            if (is_array($value)) {
                $found += apiMoneyValues($value, $moneyKeys, $here);
            }
        }

        return $found;
    }
}
