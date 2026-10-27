<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Contracts\SmsDriver;
use Modules\Core\Drivers\Sms\FakeSmsDriver;
use Modules\Core\Models\OtpCode;
use Modules\Customer\Http\Controllers\OtpLoginController;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Directory\Models\DirectoryPincode;

/*
| Shared helpers for the Customer module tests (require_once'd: Pest test files with
| same-named global functions would collide).
*/

if (! function_exists('customerFakeSms')) {
    /**
     * Swap the SMS gateway for the in-memory fake (OtpService is resolved per use).
     */
    function customerFakeSms(): FakeSmsDriver
    {
        $sms = new FakeSmsDriver;
        app()->instance(SmsDriver::class, $sms);

        return $sms;
    }

    /**
     * A live login code for an identifier, without sending anything.
     */
    function customerLoginCode(string $identifier, string $code = '246810', string $purpose = CustomerAuthService::PURPOSE_LOGIN): string
    {
        OtpCode::query()->create([
            'channel' => str_contains($identifier, '@') ? 'email' : 'sms',
            'identifier' => $identifier,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        return $code;
    }

    /**
     * Session state the verify step expects after a code was sent.
     *
     * @return array<string, mixed>
     */
    function customerOtpSession(string $identifier, string $channel = 'sms'): array
    {
        return [OtpLoginController::SESSION_KEY => ['channel' => $channel, 'identifier' => $identifier, 'resend_at' => time() + 30]];
    }

    function customerPincode(string $pincode = '560001', bool $serviceable = true): DirectoryPincode
    {
        $factory = DirectoryPincode::factory();

        return ($serviceable ? $factory : $factory->unserviceable())->create(['pincode' => $pincode, 'district' => 'Bengaluru Urban']);
    }

    /**
     * Merge values into the "customer" settings group.
     *
     * @param  array<string, mixed>  $values
     */
    function customerSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'customer')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'customer'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * A valid address payload for the storefront form / API.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function customerAddressPayload(array $overrides = []): array
    {
        return array_merge([
            'pincode' => '560001',
            'name' => 'Asha Rao',
            'phone' => '9876543210',
            'address_line1' => 'Flat 4B, Lake View Apartments',
            'address_line2' => 'MG Road',
            'landmark' => 'Opposite the metro station',
            'tag' => 'home',
        ], $overrides);
    }
}
