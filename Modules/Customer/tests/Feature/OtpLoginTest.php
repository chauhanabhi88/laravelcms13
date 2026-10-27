<?php

use Illuminate\Support\Facades\Event;
use Modules\Customer\Database\Factories\CustomerFactory;
use Modules\Customer\Events\CustomerLoggedIn;
use Modules\Customer\Events\CustomerRegistered;
use Modules\Customer\Http\Controllers\OtpLoginController;
use Modules\Customer\Models\Customer;

require_once __DIR__.'/../Helpers.php';

const OTP_LOGIN_PHONE = '+919812345678';

beforeEach(function () {
    $this->sms = customerFakeSms();
});

test('the login page is the OTP form with a link to the password form', function () {
    $this->get(frontRoute('customer.login'))
        ->assertSuccessful()
        ->assertSee('name="phone"', false)
        ->assertSee(frontRoute('customer.otp.send'), false)
        ->assertSee(frontRoute('customer.login.password'), false);

    $this->get(frontRoute('customer.login.password'))
        ->assertSuccessful()
        ->assertSee(frontRoute('customer.loginpost'), false);
});

test('sending a code texts the number and opens the verify step', function () {
    $this->post(frontRoute('customer.otp.send'), ['phone' => '98123 45678'])
        ->assertRedirect(frontRoute('customer.otp.verify.form'))
        ->assertSessionHas(OtpLoginController::SESSION_KEY.'.identifier', OTP_LOGIN_PHONE)
        ->assertSessionHas('success');

    $this->sms->assertSentTo(OTP_LOGIN_PHONE);
    expect($this->sms->lastOtpFor(OTP_LOGIN_PHONE))->toMatch('/^\d{6}$/');
});

test('the verify screen shows the masked number', function () {
    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->get(frontRoute('customer.otp.verify.form'))
        ->assertSuccessful()
        ->assertSee('+91 98XXX XX678')
        ->assertSee('autocomplete="one-time-code"', false);
});

test('the verify screen without a pending code goes back to the login page', function () {
    $this->get(frontRoute('customer.otp.verify.form'))->assertRedirect(frontRoute('customer.login'));
});

test('an invalid mobile number is rejected', function () {
    $this->from(frontRoute('customer.login'))
        ->post(frontRoute('customer.otp.send'), ['phone' => '1234567890'])
        ->assertRedirect(frontRoute('customer.login'))
        ->assertSessionHasErrors('phone');

    $this->sms->assertNothingSent();
});

test('a correct code logs in an existing customer with a fresh session', function () {
    Event::fake([CustomerLoggedIn::class, CustomerRegistered::class]);
    $customer = Customer::factory()->withPhone(OTP_LOGIN_PHONE)->create();
    $code = customerLoginCode(OTP_LOGIN_PHONE);

    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->post(frontRoute('customer.otp.verify'), ['code' => str_split($code)])
        ->assertRedirect(frontRoute('customer.myaccount'))
        ->assertSessionMissing(OtpLoginController::SESSION_KEY);

    $this->assertAuthenticatedAs($customer, 'customer');
    Event::assertDispatched(CustomerLoggedIn::class, fn (CustomerLoggedIn $event) => $event->customer->is($customer)
        && $event->previousSessionId !== null
        && $event->previousSessionId !== session()->getId()
        && $event->method === CustomerLoggedIn::METHOD_OTP_SMS);
    Event::assertNotDispatched(CustomerRegistered::class);
});

test('a new number is registered on first verification and asked to complete the profile', function () {
    Event::fake([CustomerLoggedIn::class, CustomerRegistered::class]);
    $code = customerLoginCode(OTP_LOGIN_PHONE);

    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->post(frontRoute('customer.otp.verify'), ['code' => $code])
        ->assertRedirect(frontRoute('customer.profile.complete'));

    $customer = Customer::query()->where('phone', OTP_LOGIN_PHONE)->sole();

    expect($customer->phone_verified_at)->not->toBeNull()
        ->and($customer->email)->toBeNull()
        ->and($customer->hasPassword())->toBeFalse()
        ->and($customer->isEnabled())->toBeTrue()
        ->and($customer->isReseller())->toBeFalse();
    $this->assertAuthenticatedAs($customer, 'customer');
    Event::assertDispatched(CustomerRegistered::class);
});

test('a wrong code keeps the visitor signed out', function () {
    customerLoginCode(OTP_LOGIN_PHONE, '111111');

    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->post(frontRoute('customer.otp.verify'), ['code' => '222222'])
        ->assertRedirect(frontRoute('customer.otp.verify.form'))
        ->assertSessionHasErrors('code');

    $this->assertGuest('customer');
    expect(Customer::query()->where('phone', OTP_LOGIN_PHONE)->exists())->toBeFalse();
});

test('a code that is not 6 digits is a validation error', function () {
    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->post(frontRoute('customer.otp.verify'), ['code' => '12ab'])
        ->assertSessionHasErrors('code');
});

test('sending is throttled once the hourly limit for a number is used up', function () {
    foreach (range(1, (int) config('core.otp.max_sends_per_identifier_per_hour')) as $attempt) {
        $this->post(frontRoute('customer.otp.send'), ['phone' => OTP_LOGIN_PHONE])->assertRedirect(frontRoute('customer.otp.verify.form'));
        $this->travel(61)->seconds();
    }

    $this->post(frontRoute('customer.otp.send'), ['phone' => OTP_LOGIN_PHONE])
        ->assertRedirect(frontRoute('customer.login'))
        ->assertSessionHasErrors('phone');

    expect(session('errors')->first('phone'))->toStartWith('Too many attempts. Try again in');

    $this->sms->assertSentTo(OTP_LOGIN_PHONE, (int) config('core.otp.max_sends_per_identifier_per_hour'));
});

test('asking again within the cooldown does not send a second code', function () {
    $this->post(frontRoute('customer.otp.send'), ['phone' => OTP_LOGIN_PHONE]);
    $this->post(frontRoute('customer.otp.send'), ['phone' => OTP_LOGIN_PHONE])
        ->assertRedirect(frontRoute('customer.otp.verify.form'))
        ->assertSessionHas('warning');

    $this->sms->assertSentTo(OTP_LOGIN_PHONE, 1);
});

test('after login the customer goes back to the page they wanted', function () {
    Customer::factory()->withPhone(OTP_LOGIN_PHONE)->create();
    $code = customerLoginCode(OTP_LOGIN_PHONE);

    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE) + ['url.intended' => frontRoute('customer.addresses.index')])
        ->post(frontRoute('customer.otp.verify'), ['code' => $code])
        ->assertRedirect(frontRoute('customer.addresses.index'));
});

test('a disabled account cannot sign in by OTP', function () {
    Customer::factory()->withPhone(OTP_LOGIN_PHONE)->disabled()->create();
    $code = customerLoginCode(OTP_LOGIN_PHONE);

    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->post(frontRoute('customer.otp.verify'), ['code' => $code])
        ->assertRedirect(frontRoute('customer.login'))
        ->assertSessionHas('error', trans('customer::customer_front.otp.account_disabled'));

    $this->assertGuest('customer');
});

test('a deleted account cannot be re-registered by OTP', function () {
    Customer::factory()->withPhone(OTP_LOGIN_PHONE)->create()->delete();
    $code = customerLoginCode(OTP_LOGIN_PHONE);

    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->post(frontRoute('customer.otp.verify'), ['code' => $code])
        ->assertSessionHas('error', trans('customer::customer_front.otp.account_deleted'));

    $this->assertGuest('customer');
    expect(Customer::withTrashed()->where('phone', OTP_LOGIN_PHONE)->count())->toBe(1);
});

test('the login drawer works over JSON', function () {
    Customer::factory()->withPhone(OTP_LOGIN_PHONE)->create();

    $this->postJson(frontRoute('customer.otp.send'), ['channel' => 'sms', 'phone' => '9812345678'])
        ->assertOk()
        ->assertJson(['success' => true, 'masked' => '+91 98XXX XX678', 'resend_in' => 30]);

    $code = customerLoginCode(OTP_LOGIN_PHONE, '135790');

    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->postJson(frontRoute('customer.otp.verify'), ['code' => $code])
        ->assertOk()
        ->assertJson(['success' => true, 'needs_profile' => false, 'redirect' => frontRoute('customer.myaccount')]);

    $this->assertAuthenticated('customer');
});

test('JSON errors carry field messages for the drawer', function () {
    $this->postJson(frontRoute('customer.otp.send'), ['phone' => '12'])->assertStatus(422)->assertJsonValidationErrors('phone');

    customerLoginCode(OTP_LOGIN_PHONE, '111111');
    $this->withSession(customerOtpSession(OTP_LOGIN_PHONE))
        ->postJson(frontRoute('customer.otp.verify'), ['code' => '999999'])
        ->assertStatus(422)
        ->assertJsonPath('errors.code.0', trans('customer::customer_front.otp.invalid_code'));
});

test('customers can sign in with an email code', function () {
    $customer = Customer::factory()->create(['email' => 'asha@example.com']);

    $this->post(frontRoute('customer.otp.send'), ['channel' => 'email', 'email' => 'Asha@Example.com'])
        ->assertRedirect(frontRoute('customer.otp.verify.form'))
        ->assertSessionHas(OtpLoginController::SESSION_KEY.'.identifier', 'asha@example.com');

    $code = customerLoginCode('asha@example.com', '864200');

    $this->withSession(customerOtpSession('asha@example.com', 'email'))
        ->post(frontRoute('customer.otp.verify'), ['code' => $code])
        ->assertRedirect(frontRoute('customer.myaccount'));

    $this->assertAuthenticatedAs($customer, 'customer');
});

test('email codes can be switched off in settings', function () {
    customerSettings(['enable_email_otp' => config('core.no')]);

    $this->post(frontRoute('customer.otp.send'), ['channel' => 'email', 'email' => 'asha@example.com'])
        ->assertSessionHasErrors('channel');

    $this->get(frontRoute('customer.login'))->assertDontSee(trans('customer::customer_front.otp.use_email'));
});

test('the password form can be switched off in settings', function () {
    customerSettings(['enable_password_login' => config('core.no')]);
    $customer = Customer::factory()->create();

    $this->get(frontRoute('customer.login.password'))->assertRedirect(frontRoute('customer.login'));
    $this->post(frontRoute('customer.loginpost'), ['email' => $customer->email, 'password' => CustomerFactory::PASSWORD])
        ->assertRedirect(frontRoute('customer.login'));

    $this->assertGuest('customer');
});

test('password login still works and announces the login for the cart merge', function () {
    Event::fake([CustomerLoggedIn::class]);
    $customer = Customer::factory()->create();

    $this->post(frontRoute('customer.loginpost'), ['email' => $customer->email, 'password' => CustomerFactory::PASSWORD])
        ->assertRedirect(frontRoute('customer.myaccount'));

    $this->assertAuthenticatedAs($customer, 'customer');
    Event::assertDispatched(CustomerLoggedIn::class, fn (CustomerLoggedIn $event) => $event->method === CustomerLoggedIn::METHOD_PASSWORD);
});

test('a signed-in customer is sent away from the OTP pages', function () {
    $this->actingAs(Customer::factory()->create(), 'customer');

    $this->get(frontRoute('customer.login'))->assertRedirect(frontRoute('customer.myaccount'));
    $this->get(frontRoute('customer.otp.verify.form'))->assertRedirect(frontRoute('customer.myaccount'));
});

test('guests get the login drawer on storefront pages', function () {
    $this->get(frontRoute('customer.login'))->assertSee('window.__customerLoginDrawer = true', false);

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(frontRoute('customer.myaccount'))
        ->assertDontSee('window.__customerLoginDrawer = true', false);
});
