<?php

use Modules\Customer\Events\CustomerRegistered;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\Referral;
use Modules\Promotion\Services\ReferralService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    promotionSettings(['referral_enabled' => 1, 'referral_reward_amount' => 150, 'referral_reward_min_cart' => 299, 'referral_reward_validity_days' => 30]);
});

test('a friend who joins through the code and gets a first order delivered earns the referrer a coupon', function () {
    $referrer = orderCustomer();
    $code = app(ReferralService::class)->codeFor($referrer);
    $friend = orderCustomer();

    $referral = app(ReferralService::class)->attach($friend, strtolower($code->code));

    expect($referral->status)->toBe(Referral::STATUS_PENDING)
        ->and($referral->referrer_customer_id)->toBe($referrer->id);

    [$order] = deliveredWithCoupon($friend, [cartVariant()], null);
    $referral->refresh();
    $reward = Coupon::query()->findOrFail($referral->reward_coupon_id);

    expect($referral->status)->toBe(Referral::STATUS_REWARDED)
        ->and($referral->qualified_order_id)->toBe($order->id)
        ->and($referral->reward_amount->toDecimal())->toBe('150.00')
        ->and($reward->customer_id)->toBe($referrer->id)
        ->and($reward->value->toDecimal())->toBe('150.00')
        ->and($reward->min_cart_value->toDecimal())->toBe('299.00')
        ->and($reward->usage_limit_total)->toBe(1);

    // A second delivery rewards nothing more.
    deliveredWithCoupon($friend, [cartVariant()], null);
    expect(Coupon::query()->where('customer_id', $referrer->id)->count())->toBe(1);
});

test('own codes, unknown codes, repeat referrals and switched-off referrals are ignored', function () {
    $referrer = orderCustomer();
    $code = app(ReferralService::class)->codeFor($referrer)->code;
    $friend = orderCustomer();
    $service = app(ReferralService::class);

    expect($service->attach($referrer, $code))->toBeNull()
        ->and($service->attach($friend, 'NOPE1234'))->toBeNull()
        ->and($service->attach($friend, $code))->not->toBeNull()
        ->and($service->attach($friend, $code))->toBeNull()
        ->and($service->codeFor($referrer)->code)->toBe($code);

    promotionSettings(['referral_enabled' => 2]);

    expect($service->attach(orderCustomer(), $code))->toBeNull();
});

test('the share link remembers the code and sign-up attaches the referral', function () {
    $referrer = orderCustomer();
    $code = app(ReferralService::class)->codeFor($referrer)->code;

    $this->get(frontRoute('referral.landing', [$code]))
        ->assertRedirect()
        ->assertCookie((string) config('promotion.referral_cookie'), $code);

    $friend = orderCustomer();
    $this->app['request']->headers->set('X-Referral-Code', $code);
    CustomerRegistered::dispatch($friend, 'otp');

    expect(Referral::query()->where('referred_customer_id', $friend->id)->value('referrer_customer_id'))->toBe($referrer->id);
});

test('the referral page shows the share link', function () {
    $customer = orderCustomer();

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.referral.index'))
        ->assertOk()
        ->assertSee(app(ReferralService::class)->codeFor($customer)->code)
        ->assertSee('Refer &amp; earn', false);
});
