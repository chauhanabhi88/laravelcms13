<?php

use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerDeviceToken;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Services\InboxService;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Seller/tests/Helpers.php';

/**
 * Write an in-app entry the way InAppChannel does.
 *
 * @param  array<string, mixed>  $data
 */
function inboxEntry(object $notifiable, array $data = [], bool $read = false): string
{
    $id = (string) Str::uuid();
    $notifiable->notifications()->create([
        'id' => $id,
        'type' => 'order.shipped',
        'data' => array_merge(['event_key' => 'order.shipped', 'title' => 'Order ORD-1 shipped', 'body' => 'On its way', 'url' => '/customer/orders', 'icon' => 'truck'], $data),
        'read_at' => $read ? now() : null,
    ]);
    app(InboxService::class)->forgetUnreadCount($notifiable);

    return $id;
}

test('the customer inbox lists notifications with the unread count', function () {
    $customer = Customer::factory()->create();
    inboxEntry($customer, ['title' => 'Order ORD-11 shipped']);
    inboxEntry($customer, ['title' => 'Refund processed'], read: true);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.notifications.index'))
        ->assertSuccessful()
        ->assertSee('Order ORD-11 shipped')
        ->assertSee('Refund processed')
        ->assertSee(trans_choice('notification::front.inbox.unread', 1, ['count' => 1]))
        ->assertSee('header-notification-bell');
});

test('the inbox shows an empty state', function () {
    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(frontRoute('customer.notifications.index'))
        ->assertSuccessful()
        ->assertSee(trans('notification::front.inbox.empty_title'));
});

test('guests are sent to the login page', function () {
    $this->get(frontRoute('customer.notifications.index'))->assertRedirect();
    $this->getJson(frontRoute('customer.notifications.feed'))->assertUnauthorized();
});

test('the bell feed returns the latest entries and the unread count', function () {
    $customer = Customer::factory()->create();
    foreach (range(1, 12) as $number) {
        inboxEntry($customer, ['title' => 'Update '.$number]);
    }

    $this->actingAs($customer, 'customer')
        ->getJson(frontRoute('customer.notifications.feed'))
        ->assertSuccessful()
        ->assertJsonPath('data.unread', 12)
        ->assertJsonCount(10, 'data.items')
        ->assertJsonStructure(['data' => ['items' => [['id', 'title', 'body', 'url', 'icon', 'read', 'time']]]]);
});

test('marking one as read updates the count; another customer gets a 404', function () {
    $customer = Customer::factory()->create();
    $id = inboxEntry($customer);
    $stranger = Customer::factory()->create();

    $this->actingAs($stranger, 'customer')
        ->postJson(frontRoute('customer.notifications.read', [$id]))
        ->assertNotFound();

    $this->actingAs($customer, 'customer')
        ->postJson(frontRoute('customer.notifications.read', [$id]))
        ->assertSuccessful()
        ->assertJsonPath('data.unread', 0);

    expect($customer->notifications()->first()->read_at)->not->toBeNull();
});

test('opening a notification without JavaScript marks it read and follows its link', function () {
    $customer = Customer::factory()->create();
    $id = inboxEntry($customer, ['url' => '/customer/wishlist']);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.notifications.read', [$id]))
        ->assertRedirect('/customer/wishlist');
});

test('unsafe links in notification data are dropped', function () {
    $customer = Customer::factory()->create();
    inboxEntry($customer, ['url' => 'javascript:alert(1)']);

    expect(app(InboxService::class)->latest($customer)[0]['url'])->toBe('');
});

test('mark all as read clears the unread count', function () {
    $customer = Customer::factory()->create();
    inboxEntry($customer);
    inboxEntry($customer);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.notifications.read_all'))
        ->assertRedirect(frontRoute('customer.notifications.index'))
        ->assertSessionHas('success');

    expect($customer->unreadNotifications()->count())->toBe(0)
        ->and(app(InboxService::class)->unreadCount($customer))->toBe(0);
});

test('the storefront header shows the bell to signed-in customers only', function () {
    $customer = Customer::factory()->create();
    inboxEntry($customer);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.notification_preferences.edit'))
        ->assertSuccessful()
        ->assertSee('header-notification-bell')
        ->assertSee('notificationBell(', false);
});

test('guests see no bell', function () {
    $this->get(frontRoute('customer.login'))->assertSuccessful()->assertDontSee('header-notification-bell');
});

test('customers can switch promotional channels off and on', function () {
    $customer = Customer::factory()->create();
    $this->actingAs($customer, 'customer');

    $this->get(frontRoute('customer.notification_preferences.edit'))
        ->assertSuccessful()
        ->assertSee(trans('notification::front.preferences.toggles.push.promotional.label'));

    $this->post(frontRoute('customer.notification_preferences.update'), ['enabled' => ['sms.promotional']])
        ->assertRedirect(frontRoute('customer.notification_preferences.edit'))
        ->assertSessionHas('success', trans('notification::front.preferences.saved'));

    $saved = NotificationPreference::query()->where('notifiable_id', $customer->id)->where('notifiable_type', $customer->getMorphClass())->pluck('enabled', 'channel')->all();
    expect($saved)->toEqual(['push' => 2, 'sms' => 1, 'mail' => 2, 'whatsapp' => 2]);
});

// Seller

test('the seller inbox, feed and topbar bell work per login', function () {
    [, $owner] = sellerWithOwner();
    $id = inboxEntry($owner, ['title' => 'New order ORD-21', 'url' => '/supplier']);

    $this->actingAs($owner, 'seller')
        ->get(frontRoute('seller.notifications.index'))
        ->assertSuccessful()
        ->assertSee('New order ORD-21')
        ->assertSee('seller-notification-bell');

    $this->actingAs($owner, 'seller')
        ->getJson(frontRoute('seller.notifications.feed'))
        ->assertSuccessful()
        ->assertJsonPath('data.unread', 1);

    [, $otherOwner] = sellerWithOwner();
    $this->actingAs($otherOwner, 'seller')->postJson(frontRoute('seller.notifications.read', [$id]))->assertNotFound();

    $this->actingAs($owner, 'seller')->postJson(frontRoute('seller.notifications.read', [$id]))->assertSuccessful();
    expect($owner->unreadNotifications()->count())->toBe(0);
});

test('a suspended seller can still read and clear notifications', function () {
    [, $owner] = sellerWithOwner('suspended');
    inboxEntry($owner);

    $this->actingAs($owner, 'seller')->get(frontRoute('seller.notifications.index'))->assertSuccessful();
    $this->actingAs($owner, 'seller')->postJson(frontRoute('seller.notifications.read_all'))->assertSuccessful();
});

test('sellers save their notification settings', function () {
    [, $owner] = sellerWithOwner();

    $this->actingAs($owner, 'seller')
        ->get(frontRoute('seller.notification_preferences.edit'))
        ->assertSuccessful()
        ->assertSee(trans('notification::front.preferences.toggles.sms.order_alerts.label'));

    $this->actingAs($owner, 'seller')
        ->put(frontRoute('seller.notification_preferences.update'), ['enabled' => ['mail.digest']])
        ->assertRedirect(frontRoute('seller.notification_preferences.edit'));

    expect(NotificationPreference::query()->where('notifiable_id', $owner->id)->where('notifiable_type', $owner->getMorphClass())->pluck('enabled', 'channel')->all())
        ->toEqual(['sms' => 2, 'mail' => 1, 'database' => 2]);
});

// API

test('the API lists notifications and marks them read', function () {
    $customer = Customer::factory()->create();
    $id = inboxEntry($customer);
    inboxEntry(Customer::factory()->create());
    Passport::actingAs($customer, ['customer'], 'api');

    $this->getJson('/api/v1/customer/notifications')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('meta.unread', 1);

    $this->postJson("/api/v1/customer/notifications/{$id}/read")
        ->assertSuccessful()
        ->assertJsonPath('data.read', true)
        ->assertJsonPath('meta.unread', 0);

    $this->postJson('/api/v1/customer/notifications/'.Str::uuid().'/read')->assertNotFound();
});

test('the API registers device tokens idempotently and moves them between customers', function () {
    $customer = Customer::factory()->create();
    Passport::actingAs($customer, ['customer'], 'api');
    $token = 'fcm-token-'.str_repeat('z', 30);

    $this->postJson('/api/v1/customer/device-tokens', ['device_token' => $token, 'device_type' => 'android'])->assertCreated();
    $this->postJson('/api/v1/customer/device-tokens', ['device_token' => $token, 'device_type' => 'android'])->assertSuccessful();
    $this->postJson('/api/v1/customer/device-tokens', ['device_token' => 'short', 'device_type' => 'fridge'])->assertUnprocessable()->assertJsonValidationErrors(['device_token', 'device_type']);

    expect(CustomerDeviceToken::query()->where('device_token', $token)->count())->toBe(1);

    $other = Customer::factory()->create();
    Passport::actingAs($other, ['customer'], 'api');
    $this->postJson('/api/v1/customer/device-tokens', ['device_token' => $token, 'device_type' => 'ios'])->assertSuccessful();

    expect(CustomerDeviceToken::query()->where('device_token', $token)->value('customer_id'))->toBe($other->id);

    $this->deleteJson('/api/v1/customer/device-tokens', ['device_token' => $token])->assertSuccessful();
    expect(CustomerDeviceToken::query()->where('device_token', $token)->exists())->toBeFalse();
});

test('the notification API needs a customer token', function () {
    $this->getJson('/api/v1/customer/notifications')->assertUnauthorized();
});
