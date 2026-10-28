<?php

use Illuminate\Support\Facades\DB;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Services\NotificationHub;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    config(['notification.quiet_hours' => '']);
    $this->sms = notificationFakeSms();
    $this->push = notificationFakePush();
});

test('nothing is sent or logged when the surrounding transaction rolls back', function () {
    $customer = notificationCustomer();

    try {
        DB::transaction(function () use ($customer) {
            app(NotificationHub::class)->event('order.cancelled', $customer, ['order_no' => 'ORD-ROLLBACK']);

            throw new RuntimeException('Order placement failed.');
        });
    } catch (RuntimeException) {
        // expected
    }

    $this->sms->assertNothingSent();
    expect($customer->notifications()->count())->toBe(0)
        ->and(NotificationLog::query()->where('event_key', 'order.cancelled')->where('notifiable_id', $customer->id)->count())->toBe(0);
});

test('a committed transaction sends once it commits', function () {
    $customer = notificationCustomer();

    DB::transaction(function () use ($customer) {
        app(NotificationHub::class)->event('order.cancelled', $customer, ['order_no' => 'ORD-COMMIT']);

        $this->sms->assertNothingSent();
    });

    $this->sms->assertSentTo($customer->phone);
    expect($customer->notifications()->count())->toBe(1);
});
