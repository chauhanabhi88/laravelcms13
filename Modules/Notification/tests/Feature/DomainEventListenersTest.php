<?php

use Illuminate\Support\Facades\Mail;
use Modules\Customer\Events\CustomerRegistered;
use Modules\Customer\Models\Customer;
use Modules\Notification\Models\NotificationLog;
use Modules\Product\Events\ProductRejected;
use Modules\Product\Models\Product;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerSubmittedForVerification;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Seller/tests/Helpers.php';

beforeEach(function () {
    config(['notification.quiet_hours' => '']);
    Mail::fake();
    $this->sms = notificationFakeSms();
    notificationFakePush();
});

test('a new OTP customer gets the welcome message in the inbox', function () {
    $customer = Customer::factory()->otpOnly()->create();

    CustomerRegistered::dispatch($customer, 'sms');

    expect($customer->notifications()->value('type'))->toBe('customer.welcome')
        ->and(NotificationLog::query()->where('event_key', 'customer.welcome')->where('notifiable_id', $customer->id)->pluck('status', 'channel')->all())
        ->toEqual(['mail' => 'skipped', 'database' => 'sent']);
});

test('KYC approval reaches the seller owner by SMS and in the panel', function () {
    [$seller, $owner] = sellerWithOwner();

    SellerApproved::dispatch($seller);

    $this->sms->assertSentTo($owner->phone);
    expect($owner->notifications()->value('type'))->toBe('seller.kyc.approved');
});

test('a submitted application notifies the admins who review applications', function () {
    [$seller] = sellerWithOwner('pendingVerification');
    $reviewer = notificationAdmin(['admin.seller_application.index']);

    SellerSubmittedForVerification::dispatch($seller);

    expect($reviewer->notifications()->value('type'))->toBe('admin.seller.submitted');
});

test('a QC rejection tells the seller why', function () {
    [$seller, $owner] = sellerWithOwner();
    $product = Product::factory()->for($seller)->create();

    ProductRejected::dispatch($product, 'Images are blurry');

    $entry = $owner->notifications()->first();
    expect($entry->type)->toBe('seller.product.rejected')
        ->and($entry->data['body'])->toContain('Images are blurry');
});

test('a failing hub never breaks the domain action', function () {
    config(['notification.events' => []]);
    [$seller] = sellerWithOwner();

    SellerApproved::dispatch($seller);

    expect(NotificationLog::query()->where('event_key', 'seller.kyc.approved')->exists())->toBeFalse();
});
