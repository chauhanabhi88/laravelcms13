<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Modules\Core\Contracts\SmsDriver;
use Modules\Core\Drivers\Sms\FakeSmsDriver;
use Modules\Customer\Models\Customer;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Notifications\SellerResetPassword;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->sms = new FakeSmsDriver;
    $this->app->instance(SmsDriver::class, $this->sms);
});

function sellerRegistrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Kavya Iyer',
        'email' => 'kavya@example.test',
        'phone' => '98450 12345',
        'password' => 'Weave#2026',
        'password_confirmation' => 'Weave#2026',
        'accept_terms' => '1',
    ], $overrides);
}

test('a supplier registers with a mobile OTP and lands in the wizard', function () {
    $this->get(route('seller.register'))->assertSuccessful()->assertSee(trans('seller::auth.register.title'));

    $this->post(route('seller.register.store'), sellerRegistrationData())
        ->assertRedirect(route('seller.register.verify'));

    $this->sms->assertSentTo('+919845012345');
    expect(SellerUser::query()->where('email', 'kavya@example.test')->exists())->toBeFalse();

    $this->get(route('seller.register.verify'))->assertSuccessful()->assertSee('98450 12345');

    $this->post(route('seller.register.otp'), ['otp' => $this->sms->lastOtpFor('+919845012345')])
        ->assertRedirect(route('seller.onboarding.show', [1]));

    $user = SellerUser::query()->with('seller')->where('email', 'kavya@example.test')->sole();

    expect($user)
        ->role->toBe(SellerUser::ROLE_OWNER)
        ->phone->toBe('+919845012345')
        ->phone_verified_at->not->toBeNull()
        ->and($user->seller->status)->toBe(SellerStatus::Onboarding)
        ->and($user->seller->code)->toMatch('/^S\d{6}$/')
        ->and(Auth::guard('seller')->id())->toBe($user->id)
        ->and(Auth::guard('customer')->check())->toBeFalse();
});

test('a wrong registration code creates nothing', function () {
    $this->post(route('seller.register.store'), sellerRegistrationData());
    $code = $this->sms->lastOtpFor('+919845012345');

    $this->post(route('seller.register.otp'), ['otp' => $code === '000000' ? '111111' : '000000'])
        ->assertSessionHasErrors('otp');

    expect(Seller::query()->where('email', 'kavya@example.test')->exists())->toBeFalse();
    $this->assertGuest('seller');
});

test('registration validates the details', function () {
    SellerUser::factory()->create(['email' => 'taken@example.test', 'phone' => '+919845012345']);

    $this->post(route('seller.register.store'), sellerRegistrationData([
        'email' => 'taken@example.test',
        'password' => 'short',
        'password_confirmation' => 'short',
        'accept_terms' => null,
    ]))->assertSessionHasErrors(['email', 'phone_e164', 'password', 'accept_terms']);

    $this->post(route('seller.register.store'), sellerRegistrationData(['email' => 'new@example.test', 'phone' => '12345']))
        ->assertSessionHasErrors('phone_e164');

    $this->sms->assertNothingSent();
});

test('a supplier signs in with email or mobile and a password, then signs out', function () {
    [, $owner] = sellerWithOwner('active', ['email' => 'owner@example.test', 'phone' => '+919900112233']);

    $this->get(route('seller.login'))->assertSuccessful();

    $this->post(route('seller.login.attempt'), ['login' => 'owner@example.test', 'password' => 'Secret#123'])
        ->assertRedirect(route('seller.dashboard'));
    $this->assertAuthenticatedAs($owner, 'seller');
    expect($owner->fresh()->last_login_at)->not->toBeNull();

    $this->post(route('seller.logout'))->assertRedirect(route('seller.login'));
    $this->assertGuest('seller');

    $this->post(route('seller.login.attempt'), ['login' => '99001 12233', 'password' => 'Secret#123'])
        ->assertRedirect(route('seller.dashboard'));
    $this->assertAuthenticatedAs($owner, 'seller');
});

test('a wrong password is refused', function () {
    sellerWithOwner('active', ['email' => 'owner@example.test']);

    $this->post(route('seller.login.attempt'), ['login' => 'owner@example.test', 'password' => 'Wrong#123'])
        ->assertRedirect(route('seller.login'))
        ->assertSessionHasErrors('login');

    $this->assertGuest('seller');
});

test('a supplier signs in with a one-time code', function () {
    [, $owner] = sellerWithOwner('active', ['phone' => '+919900112233']);

    $this->post(route('seller.login.otp'), ['login' => '9900112233'])->assertSessionHas('success');
    $code = $this->sms->lastOtpFor('+919900112233');

    $this->post(route('seller.login.attempt'), ['login' => '9900112233', 'method' => 'otp', 'otp' => $code])
        ->assertRedirect(route('seller.dashboard'));

    $this->assertAuthenticatedAs($owner, 'seller');
});

test('an OTP request for an unknown number sends nothing but answers the same', function () {
    $this->post(route('seller.login.otp'), ['login' => '9900112299'])->assertSessionHas('success');

    $this->sms->assertNothingSent();
});

test('a disabled staff login cannot sign in', function () {
    [$seller] = sellerWithOwner();
    SellerUser::factory()->for($seller)->role('orders')->disabled()->create(['email' => 'staff@example.test']);

    $this->post(route('seller.login.attempt'), ['login' => 'staff@example.test', 'password' => 'Secret#123'])
        ->assertSessionHasErrors('login');

    $this->assertGuest('seller');
});

test('guards are isolated: customers and admins cannot open the supplier panel', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('seller.dashboard'))
        ->assertRedirect(route('seller.login'));

    $this->actingAs(sellerAdmin(), 'web')
        ->get(route('seller.dashboard'))
        ->assertRedirect(route('seller.login'));
});

test('a seller session does not sign in to the customer area or the admin', function () {
    [, $owner] = sellerWithOwner();

    $this->actingAs($owner, 'seller')->get(route('seller.dashboard'))->assertSuccessful();

    expect(Auth::guard('customer')->check())->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse();
});

test('a password reset link is sent and resets the password', function () {
    Notification::fake();
    [, $owner] = sellerWithOwner('active', ['email' => 'owner@example.test']);

    $this->post(route('seller.password.email'), ['email' => 'owner@example.test'])->assertSessionHas('success');
    $this->post(route('seller.password.email'), ['email' => 'nobody@example.test'])->assertSessionHas('success');

    $token = null;
    Notification::assertSentTo($owner, SellerResetPassword::class, function (SellerResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->get(route('seller.password.reset', [$token, 'email' => 'owner@example.test']))->assertSuccessful();

    $this->post(route('seller.password.update'), [
        'token' => $token,
        'email' => 'owner@example.test',
        'password' => 'Fresh#Start9',
        'password_confirmation' => 'Fresh#Start9',
    ])->assertRedirect(route('seller.login'));

    $this->post(route('seller.login.attempt'), ['login' => 'owner@example.test', 'password' => 'Fresh#Start9'])
        ->assertRedirect(route('seller.dashboard'));

    expect(Password::broker('sellers')->getRepository()->recentlyCreatedToken($owner))->toBeFalse();
});
