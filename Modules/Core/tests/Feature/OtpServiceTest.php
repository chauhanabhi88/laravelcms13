<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Contracts\SmsDriver;
use Modules\Core\Drivers\Sms\FakeSmsDriver;
use Modules\Core\Models\OtpCode;
use Modules\Core\Services\Otp\OtpSendStatus;
use Modules\Core\Services\Otp\OtpService;

const OTP_PHONE = '+919876543210';

beforeEach(function () {
    $this->sms = new FakeSmsDriver;
    $this->app->instance(SmsDriver::class, $this->sms);
    $this->otp = app(OtpService::class);
});

test('a sent code verifies once and is stored only as a hash', function () {
    $result = $this->otp->send('sms', '98765 43210', 'customer_login', '10.0.0.1');

    expect($result->successful())->toBeTrue()->and($result->identifier)->toBe(OTP_PHONE);
    $this->sms->assertSentTo(OTP_PHONE);

    $code = $this->sms->lastOtpFor(OTP_PHONE);
    $row = OtpCode::query()->where('identifier', OTP_PHONE)->sole();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($row->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $row->code_hash))->toBeTrue()
        ->and($row->ip)->toBe('10.0.0.1');

    expect($this->otp->verify(OTP_PHONE, 'customer_login', $code))->toBeTrue()
        ->and($this->otp->verify(OTP_PHONE, 'customer_login', $code))->toBeFalse();
});

test('the purpose must match', function () {
    $this->otp->send('sms', OTP_PHONE, 'customer_login');

    expect($this->otp->verify(OTP_PHONE, 'seller_login', $this->sms->lastOtpFor(OTP_PHONE)))->toBeFalse();
});

test('a wrong code counts as an attempt and the sixth attempt fails even with the right code', function () {
    $this->otp->send('sms', OTP_PHONE, 'customer_login');
    $code = $this->sms->lastOtpFor(OTP_PHONE);
    $wrong = $code === '000000' ? '111111' : '000000';

    expect($this->otp->verify(OTP_PHONE, 'customer_login', $wrong))->toBeFalse();
    expect(OtpCode::query()->sole()->attempts)->toBe(1);

    foreach (range(2, 5) as $ignored) {
        $this->otp->verify(OTP_PHONE, 'customer_login', $wrong);
    }

    expect($this->otp->verify(OTP_PHONE, 'customer_login', $code))->toBeFalse();
});

test('an expired code fails', function () {
    $this->otp->send('sms', OTP_PHONE, 'customer_login');
    $code = $this->sms->lastOtpFor(OTP_PHONE);

    $this->travel(6)->minutes();

    expect($this->otp->verify(OTP_PHONE, 'customer_login', $code))->toBeFalse();
});

test('a resend inside the cooldown is refused', function () {
    $this->otp->send('sms', OTP_PHONE, 'customer_login');

    $this->travel(10)->seconds();
    $result = $this->otp->send('sms', OTP_PHONE, 'customer_login');

    expect($result->status)->toBe(OtpSendStatus::Cooldown)
        ->and($result->retryAfter)->toBeGreaterThan(0)->toBeLessThanOrEqual(30);
    $this->sms->assertSentTo(OTP_PHONE, 1);
});

test('a new send invalidates the earlier code', function () {
    $this->otp->send('sms', OTP_PHONE, 'customer_login');
    $first = $this->sms->lastOtpFor(OTP_PHONE);

    $this->travel(31)->seconds();
    $this->otp->send('sms', OTP_PHONE, 'customer_login');
    $second = $this->sms->lastOtpFor(OTP_PHONE);

    if ($first !== $second) {
        expect($this->otp->verify(OTP_PHONE, 'customer_login', $first))->toBeFalse();
    }

    expect($this->otp->verify(OTP_PHONE, 'customer_login', $second))->toBeTrue();
});

test('sends are capped per number per hour', function () {
    foreach (range(1, 5) as $ignored) {
        expect($this->otp->send('sms', OTP_PHONE, 'customer_login')->successful())->toBeTrue();
        $this->travel(31)->seconds();
    }

    expect($this->otp->send('sms', OTP_PHONE, 'customer_login')->status)->toBe(OtpSendStatus::Throttled);

    $this->travel(1)->hours();

    expect($this->otp->send('sms', OTP_PHONE, 'customer_login')->successful())->toBeTrue();
});

test('sends are capped per IP per hour', function () {
    config(['core.otp.max_sends_per_ip_per_hour' => 2]);

    $this->otp->send('sms', '9876500001', 'customer_login', '10.0.0.9');
    $this->otp->send('sms', '9876500002', 'customer_login', '10.0.0.9');

    expect($this->otp->send('sms', '9876500003', 'customer_login', '10.0.0.9')->status)->toBe(OtpSendStatus::Throttled)
        ->and($this->otp->send('sms', '9876500003', 'customer_login', '10.0.0.10')->successful())->toBeTrue();
});

test('a gateway failure is reported and the code is unusable', function () {
    $this->sms->failing();

    expect($this->otp->send('sms', OTP_PHONE, 'customer_login')->status)->toBe(OtpSendStatus::Failed)
        ->and(OtpCode::query()->usable()->count())->toBe(0);
});

test('email codes are mailed', function () {
    Mail::fake();

    expect($this->otp->send('email', ' Buyer@Example.TEST ', 'customer_login')->identifier)->toBe('buyer@example.test');
    $this->sms->assertNothingSent();
});

test('identifiers are normalised to E.164 and bad numbers refused', function (string $input, ?string $expected) {
    if ($expected === null) {
        expect(fn () => OtpService::normaliseIdentifier('sms', $input))->toThrow(InvalidArgumentException::class);

        return;
    }

    expect(OtpService::normaliseIdentifier('sms', $input))->toBe($expected);
})->with([
    ['9876543210', OTP_PHONE],
    ['098765 43210', OTP_PHONE],
    ['919876543210', OTP_PHONE],
    ['+91 98765-43210', OTP_PHONE],
    ['+14155550100', '+14155550100'],
    ['12345', null],
    ['5876543210', null],
]);

test('old codes are pruned', function () {
    $this->otp->send('sms', OTP_PHONE, 'customer_login');
    $this->travel(25)->hours();
    $this->otp->send('sms', '9876500001', 'customer_login');

    $this->artisan('core:prune-otp-codes')->assertSuccessful();

    expect(OtpCode::query()->pluck('identifier')->all())->toBe(['+919876500001']);
});
