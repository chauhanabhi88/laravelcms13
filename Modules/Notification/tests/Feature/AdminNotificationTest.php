<?php

use Modules\Customer\Models\Customer;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Services\NotificationHub;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    config(['notification.quiet_hours' => '']);
    $this->sms = notificationFakeSms();
    notificationFakePush();
});

/**
 * @return array<string, mixed>
 */
function adminTemplatePayload(array $overrides = []): array
{
    return array_merge([
        'event_key' => 'order.placed',
        'channel' => 'whatsapp',
        'audience' => 'customer',
        'title' => '',
        'body' => 'Order {{order_no}} placed.',
        'provider_template_id' => 'order_placed_v1',
        'status' => config('core.enabled'),
    ], $overrides);
}

test('the template grid and forms render', function () {
    $template = notificationTemplate('order.shipped', 'sms');

    $this->actingAs(notificationAdmin())
        ->get(route('admin.notification_template.index', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee('order.shipped');

    $this->actingAs(notificationAdmin())
        ->get(route('admin.notification_template.create', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee('name="body"', false);

    $this->actingAs(notificationAdmin())
        ->get(route('admin.notification_template.edit', updateUrlParams([$template->id])))
        ->assertSuccessful()
        ->assertSee('{{order_no}}', false)
        ->assertSee(trans('notification::notification_template.titles.test_send'));
});

test('an admin creates and updates a template', function () {
    NotificationTemplate::query()->where('event_key', 'order.placed')->where('channel', 'whatsapp')->delete();

    $this->actingAs(notificationAdmin())
        ->post(route('admin.notification_template.store', updateUrlParams()), adminTemplatePayload())
        ->assertRedirect(route('admin.notification_template.index', updateUrlParams()))
        ->assertSessionHas('success');

    $template = NotificationTemplate::query()->where('event_key', 'order.placed')->where('channel', 'whatsapp')->firstOrFail();
    expect($template)->title->toBeNull()->provider_template_id->toBe('order_placed_v1');

    $this->actingAs(notificationAdmin())
        ->put(route('admin.notification_template.update', updateUrlParams([$template->id])), adminTemplatePayload(['body' => 'Your order {{order_no}} is confirmed.', 'status' => config('core.disabled')]))
        ->assertRedirect(route('admin.notification_template.index', updateUrlParams()));

    expect($template->fresh())->body->toBe('Your order {{order_no}} is confirmed.')->isEnabled()->toBeFalse();
});

test('templates must be unique per event, channel and audience and use catalogue events', function () {
    notificationTemplate('order.shipped', 'sms');

    $this->actingAs(notificationAdmin())
        ->post(route('admin.notification_template.store', updateUrlParams()), adminTemplatePayload(['event_key' => 'order.shipped', 'channel' => 'sms']))
        ->assertSessionHasErrors('audience');

    $this->actingAs(notificationAdmin())
        ->post(route('admin.notification_template.store', updateUrlParams()), adminTemplatePayload(['event_key' => 'order.teleported', 'channel' => 'mail', 'body' => '']))
        ->assertSessionHasErrors(['event_key', 'channel', 'body']);
});

test('the template screens need their permissions', function () {
    $this->actingAs(notificationAdmin(['admin.notification_log.index']))
        ->get(route('admin.notification_template.index', updateUrlParams()))
        ->assertForbidden();
});

test('test send renders sample values and logs the attempt', function () {
    $template = notificationTemplate('order.shipped', 'sms', 'customer', ['body' => 'Order {{order_no}} via {{courier}}', 'provider_template_id' => '1207000000000000009']);

    $this->actingAs(notificationAdmin())
        ->post(route('admin.notification_template.test_send', updateUrlParams([$template->id])), ['destination' => '98765 43210'])
        ->assertRedirect(route('admin.notification_template.edit', updateUrlParams([$template->id])))
        ->assertSessionHas('success');

    expect($this->sms->sent('+919876543210')[0])->toMatchArray([
        'template' => '1207000000000000009',
        'vars' => ['order_no' => '[order_no]', 'courier' => '[courier]', 'message' => 'Order [order_no] via [courier]'],
    ]);

    $log = NotificationLog::query()->latest('id')->first();
    expect($log)->status->toBe('sent')->notifiable_id->toBeNull()->and($log->payload['test'])->toBeTrue();

    $this->actingAs(notificationAdmin())
        ->post(route('admin.notification_template.test_send', updateUrlParams([$template->id])), ['destination' => '12345'])
        ->assertSessionHas('error', trans('notification::notification_template.messages.test_bad_phone'));
});

test('the delivery log lists, shows and retries deliveries', function () {
    $customer = notificationCustomer();
    $this->sms->failing();
    app(NotificationHub::class)->event('order.out_for_delivery', $customer, ['order_no' => 'ORD-LOG']);
    $failed = NotificationLog::query()->where('event_key', 'order.out_for_delivery')->where('channel', 'sms')->firstOrFail();
    expect($failed->status)->toBe('failed');

    $this->actingAs(notificationAdmin())
        ->get(route('admin.notification_log.index', updateUrlParams()))
        ->assertSuccessful()
        ->assertSee('order.out_for_delivery');

    $this->actingAs(notificationAdmin())
        ->get(route('admin.notification_log.show', updateUrlParams([$failed->id])))
        ->assertSuccessful()
        ->assertSee('Fake SMS gateway failure.')
        ->assertSee('ORD-LOG')
        ->assertSee(trans('notification::notification_log.labels.retry'));

    $this->sms->failing(false);

    $response = $this->actingAs(notificationAdmin())
        ->post(route('admin.notification_log.retry', updateUrlParams([$failed->id])));

    $retry = NotificationLog::query()->where('event_key', 'order.out_for_delivery')->where('channel', 'sms')->latest('id')->firstOrFail();
    $response->assertRedirect(route('admin.notification_log.show', updateUrlParams([$retry->id])))->assertSessionHas('success');

    expect($retry->id)->not->toBe($failed->id)
        ->and($retry->status)->toBe('sent')
        ->and($failed->fresh()->status)->toBe('failed');
    $this->sms->assertSentTo($customer->phone);
});

test('sent deliveries cannot be retried', function () {
    $customer = Customer::factory()->withPhone()->create();
    app(NotificationHub::class)->event('order.out_for_delivery', $customer, ['order_no' => 'ORD-OK'], ['sms']);
    $sent = NotificationLog::query()->where('event_key', 'order.out_for_delivery')->where('notifiable_id', $customer->id)->firstOrFail();

    $this->actingAs(notificationAdmin())
        ->post(route('admin.notification_log.retry', updateUrlParams([$sent->id])))
        ->assertSessionHas('error', trans('notification::notification_log.messages.not_retryable'));
});
