<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\Customer\Models\Customer;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Notifications\MarketplaceNotification;
use Modules\Notification\Services\NotificationHub;
use Modules\Notification\Services\PreferenceService;
use Modules\Seller\Models\SellerUser;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Seller/tests/Helpers.php';

beforeEach(function () {
    config(['notification.quiet_hours' => '']);
    Mail::fake();
    $this->sms = notificationFakeSms();
    $this->push = notificationFakePush();
    $this->hub = app(NotificationHub::class);
});

/**
 * @return array<string, string> channel => status
 */
function hubLogStatuses(string $eventKey, object $notifiable): array
{
    return NotificationLog::query()
        ->where('event_key', $eventKey)
        ->where('notifiable_type', $notifiable->getMorphClass())
        ->where('notifiable_id', $notifiable->getKey())
        ->pluck('status', 'channel')
        ->all();
}

test('an event goes out on every catalogue channel the recipient can be reached on', function () {
    $customer = notificationCustomer();
    notificationMailTemplate('order-placed-customer');
    foreach (['sms', 'push', 'database'] as $channel) {
        notificationTemplate('order.placed', $channel, 'customer', ['body' => 'Order {{order_no}} placed for Rs {{amount}}.']);
    }

    $statuses = $this->hub->event('order.placed', $customer, ['order_no' => 'ORD-2611-000001', 'amount' => '499.00', 'url' => '/customer/orders']);

    expect($statuses)->toBe(['mail' => 'queued', 'sms' => 'queued', 'push' => 'queued', 'database' => 'queued'])
        ->and(hubLogStatuses('order.placed', $customer))->toBe(['mail' => 'sent', 'sms' => 'sent', 'push' => 'sent', 'database' => 'sent']);

    $this->sms->assertSentTo($customer->phone);
    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['message']->body)->toBe('Order ORD-2611-000001 placed for Rs 499.00.')
        ->and($customer->notifications()->first()->data)->toMatchArray(['event_key' => 'order.placed', 'url' => '/customer/orders', 'icon' => 'package']);

    $log = NotificationLog::query()->where('event_key', 'order.placed')->where('channel', 'sms')->first();
    expect($log->destination)->toBe('********'.substr($customer->phone, -4))
        ->and($log->sent_at)->not->toBeNull()
        ->and($log->notification_id)->not->toBeNull()
        ->and($log->data())->toMatchArray(['order_no' => 'ORD-2611-000001']);
});

test('channels without a route are skipped and logged with the reason', function () {
    $customer = Customer::factory()->otpOnly()->create();

    $statuses = $this->hub->event('order.shipped', $customer, ['order_no' => 'ORD-1']);

    expect($statuses)->toMatchArray(['sms' => 'queued', 'database' => 'queued', 'push' => 'skipped', 'mail' => 'skipped'])
        ->and(NotificationLog::query()->where('event_key', 'order.shipped')->where('channel', 'mail')->value('error'))->toBe('no_route');
});

test('a missing or inactive template skips the channel', function () {
    $customer = notificationCustomer();
    DB::table('notification_template')->where('event_key', 'order.out_for_delivery')->where('channel', 'push')->delete();
    notificationTemplate('order.out_for_delivery', 'sms', 'customer', ['status' => config('core.disabled')]);

    $this->hub->event('order.out_for_delivery', $customer, ['order_no' => 'ORD-1']);

    expect(hubLogStatuses('order.out_for_delivery', $customer))->toBe(['sms' => 'skipped', 'push' => 'skipped'])
        ->and(NotificationLog::query()->where('event_key', 'order.out_for_delivery')->pluck('error')->unique()->all())->toBe(['no_template']);
    $this->sms->assertNothingSent();
});

test('opting out of promotional push stops review requests but not order updates', function () {
    $customer = notificationCustomer();
    notificationTemplate('review.request', 'push', 'customer');
    notificationTemplate('order.shipped', 'push', 'customer');
    app(PreferenceService::class)->save($customer, 'customer', ['sms.promotional', 'mail.promotional']);

    expect(NotificationPreference::query()->where('notifiable_id', $customer->id)->where('channel', 'push')->value('enabled'))->toBe(2);

    $this->hub->event('review.request', $customer, ['product_name' => 'Cotton Kurti']);
    $this->hub->event('order.shipped', $customer, ['order_no' => 'ORD-1']);

    expect(hubLogStatuses('review.request', $customer)['push'])->toBe('skipped')
        ->and(NotificationLog::query()->where('event_key', 'review.request')->where('channel', 'push')->value('error'))->toBe('opted_out')
        ->and(hubLogStatuses('order.shipped', $customer)['push'])->toBe('sent')
        ->and($this->push->sent)->toHaveCount(1);
});

test('customers who declined marketing at sign-up get no promotional messages by default', function () {
    $customer = notificationCustomer(['marketing_opt_in' => config('core.no')]);

    $statuses = $this->hub->event('cart.abandoned', $customer, ['item_count' => 2]);

    expect($statuses)->toBe(['push' => 'skipped']);
});

test('disabled accounts are not notified', function () {
    $customer = notificationCustomer(['status' => 2]);

    expect($this->hub->event('order.cancelled', $customer, ['order_no' => 'ORD-1']))
        ->toBe(['sms' => 'skipped', 'mail' => 'skipped', 'database' => 'skipped']);
});

test('the push and transactional SMS switches in settings turn those channels off', function () {
    notificationSettings(['enable_push' => config('core.no'), 'enable_sms_transactional' => config('core.no')]);
    $customer = notificationCustomer();

    $statuses = $this->hub->event('order.out_for_delivery', $customer, ['order_no' => 'ORD-1']);

    expect($statuses)->toBe(['sms' => 'skipped', 'push' => 'skipped'])
        ->and(NotificationLog::query()->where('event_key', 'order.out_for_delivery')->pluck('error')->unique()->all())->toBe(['disabled']);
});

test('an explicit channel list narrows the catalogue channels', function () {
    $customer = notificationCustomer();

    expect($this->hub->event('order.placed', $customer, ['order_no' => 'ORD-1'], ['database', 'fax']))
        ->toBe(['database' => 'queued']);
});

test('unknown events are rejected', function () {
    $this->hub->event('order.teleported', notificationCustomer());
})->throws(InvalidArgumentException::class);

test('hub notifications are queued on the notifications queue after commit', function () {
    Notification::fake();
    $customer = notificationCustomer();

    $this->hub->event('order.delivered', $customer, ['order_no' => 'ORD-1']);

    Notification::assertSentTo($customer, MarketplaceNotification::class, function (MarketplaceNotification $notification, array $channels) {
        return $notification->eventKey === 'order.delivered'
            && $notification->queue === 'notifications'
            && $notification->afterCommit === true
            && count($channels) === 3
            && $notification->logIdFor('database') !== null;
    });
    expect(hubLogStatuses('order.delivered', $customer))->toBe(['push' => 'queued', 'database' => 'queued', 'mail' => 'queued']);
});

test('promotional messages wait for the end of quiet hours', function () {
    config(['notification.quiet_hours' => '22:00-08:00']);
    $this->travelTo(now()->setTime(23, 30));

    $notification = new MarketplaceNotification('review.request', [], ['push']);
    $transactional = new MarketplaceNotification('order.shipped', [], ['push']);

    expect($notification->withDelay(new Customer, 'push')?->format('H:i'))->toBe('08:00')
        ->and($notification->withDelay(new Customer, 'push')?->isTomorrow())->toBeTrue()
        ->and($transactional->withDelay(new Customer, 'push'))->toBeNull();

    $this->travelTo(now()->setTime(12, 0));
    expect($notification->withDelay(new Customer, 'push'))->toBeNull();
});

test('seller events reach the owner and the staff roles the catalogue names', function () {
    [$seller, $owner] = sellerWithOwner();
    $ordersStaff = SellerUser::factory()->for($seller)->role('orders')->create();
    $catalogStaff = SellerUser::factory()->for($seller)->role('catalog')->create();

    $count = $this->hub->eventForSeller('seller.order.new', $seller, ['order_no' => 'ORD-9', 'item_count' => 2, 'dispatch_by' => 'Thu, 30 Oct']);

    expect($count)->toBe(2)
        ->and($owner->notifications()->count())->toBe(1)
        ->and($ordersStaff->notifications()->count())->toBe(1)
        ->and($catalogStaff->notifications()->count())->toBe(0);
    $this->sms->assertSentTo($owner->phone);
});

test('sellers can switch off new-order SMS without losing the in-app alert', function () {
    [$seller, $owner] = sellerWithOwner();
    app(PreferenceService::class)->save($owner, 'seller', ['mail.digest', 'database.digest']);

    $this->hub->eventForSeller('seller.order.new', $seller, ['order_no' => 'ORD-9']);

    expect(hubLogStatuses('seller.order.new', $owner))->toMatchArray(['sms' => 'skipped', 'database' => 'sent']);
    $this->sms->assertNothingSent();
});

test('admin events reach only admins whose role grants the permission', function () {
    $reviewer = notificationAdmin(['admin.seller_application.index']);
    $other = notificationAdmin(['admin.notification_template.index']);

    $count = $this->hub->eventForAdmins('admin.seller.submitted', ['store' => 'Asha Prints', 'seller_code' => 'S0001']);

    expect($count)->toBeGreaterThanOrEqual(1)
        ->and($reviewer->notifications()->count())->toBe(1)
        ->and($other->notifications()->count())->toBe(0)
        ->and(hubLogStatuses('admin.seller.submitted', $reviewer))->toMatchArray(['mail' => 'sent', 'database' => 'sent']);
});
