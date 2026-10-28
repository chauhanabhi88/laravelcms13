<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\SmsDriver;
use Modules\Core\Drivers\Sms\FakeSmsDriver;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerDeviceToken;
use Modules\Notification\Contracts\PushDriver;
use Modules\Notification\Drivers\FakePushDriver;
use Modules\Notification\Models\NotificationTemplate;
use Modules\User\Models\User;

/*
| Shared helpers for the Notification module tests (require_once'd: Pest test files with
| same-named global functions would collide).
*/

if (! function_exists('notificationFakeSms')) {
    function notificationFakeSms(): FakeSmsDriver
    {
        $sms = new FakeSmsDriver;
        app()->instance(SmsDriver::class, $sms);

        return $sms;
    }

    /**
     * @param  list<string>  $unregistered  tokens the fake reports as no longer registered
     */
    function notificationFakePush(array $unregistered = []): FakePushDriver
    {
        $push = new FakePushDriver($unregistered);
        app()->instance(PushDriver::class, $push);

        return $push;
    }

    /**
     * Create or replace the template of an event / channel / audience (the migration seeds
     * defaults into the testing schema too).
     *
     * @param  array<string, mixed>  $attributes
     */
    function notificationTemplate(string $eventKey, string $channel, string $audience = 'customer', array $attributes = []): NotificationTemplate
    {
        return NotificationTemplate::query()->updateOrCreate(
            ['event_key' => $eventKey, 'channel' => $channel, 'audience' => $audience],
            array_merge([
                'title' => 'Title for '.$eventKey,
                'body' => 'Body for {{order_no}}',
                'provider_template_id' => null,
                'status' => (int) config('core.enabled'),
            ], $attributes),
        );
    }

    function notificationMailTemplate(string $slug, string $subject = 'Order ##order_no## update', string $body = '<p>Hi ##recipient_name##, order ##order_no##.</p>'): void
    {
        DB::table('mail_templates')->where('slug', $slug)->delete();
        DB::table('mail_templates')->insert([
            'name' => 'Test '.$slug,
            'subject' => $subject,
            'slug' => $slug,
            'body' => $body,
            'status' => (int) config('core.enabled'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A customer reachable on every channel: email, verified mobile and one app token.
     *
     * @param  array<string, mixed>  $attributes
     */
    function notificationCustomer(array $attributes = [], ?string $deviceToken = 'device-token-aaaaaaaaaaaaaaaaaaaa'): Customer
    {
        $customer = Customer::factory()->withPhone()->create(array_merge(['first_name' => 'Meera', 'last_name' => 'K'], $attributes));

        if ($deviceToken !== null) {
            CustomerDeviceToken::query()->create(['customer_id' => $customer->id, 'device_token' => $deviceToken, 'device_type' => 'android']);
        }

        return $customer;
    }

    /**
     * Merge values into the "notification" settings group.
     *
     * @param  array<string, mixed>  $values
     */
    function notificationSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'notification')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'notification'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * An admin user whose role holds $permissions (null = every Notification permission).
     *
     * @param  list<string>|null  $permissions
     */
    function notificationAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Notification/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Notification Admin',
            'slug' => 'notification_admin_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Notification Admin',
            'email' => uniqid('notification-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
