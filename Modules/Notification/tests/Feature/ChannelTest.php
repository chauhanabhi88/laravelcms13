<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Customer\Models\CustomerDeviceToken;
use Modules\Notification\Contracts\PushDriver;
use Modules\Notification\Drivers\Fcm\FcmAccessToken;
use Modules\Notification\Drivers\Fcm\FcmPushDriver;
use Modules\Notification\Drivers\PushMessage;
use Modules\Notification\Mail\TemplateMailable;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Services\NotificationHub;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    config(['notification.quiet_hours' => '']);
    $this->hub = app(NotificationHub::class);
});

function channelLog(string $eventKey, string $channel): ?NotificationLog
{
    return NotificationLog::query()->where('event_key', $eventKey)->where('channel', $channel)->latest('id')->first();
}

// Mail

test('email uses the Mail module template of the event slug with escaped values', function () {
    Mail::fake();
    notificationFakeSms();
    notificationFakePush();
    notificationMailTemplate('order-shipped-customer', 'Order ##order_no## shipped', '<p>Hi ##recipient_name##, ##order_no## is with ##courier##.</p>');
    $customer = notificationCustomer(['first_name' => 'Meera <b>', 'email' => 'meera@example.com']);

    $this->hub->event('order.shipped', $customer, ['order_no' => 'ORD-7', 'courier' => 'Swift & Co']);

    Mail::assertSent(TemplateMailable::class, function (TemplateMailable $mail) {
        $html = $mail->render();

        return $mail->hasTo('meera@example.com')
            && $mail->subject === 'Order ORD-7 shipped'
            && str_contains($html, 'Hi Meera &lt;b&gt; K, ORD-7 is with Swift &amp; Co.');
    });
    expect(channelLog('order.shipped', 'mail'))->status->toBe('sent')->destination->toBe('me***@example.com');
});

test('email is skipped when the event has no active Mail template', function () {
    Mail::fake();
    notificationFakeSms();
    notificationFakePush();
    DB::table('mail_templates')->where('slug', 'order-delivered-customer')->delete();

    $this->hub->event('order.delivered', notificationCustomer(), ['order_no' => 'ORD-7']);

    Mail::assertNothingSent();
    expect(channelLog('order.delivered', 'mail'))->status->toBe('skipped')->error->toBe('no_template');
});

// SMS

test('SMS go to the driver with the DLT template id and the template variables', function () {
    $sms = notificationFakeSms();
    notificationFakePush();
    notificationTemplate('order.out_for_delivery', 'sms', 'customer', [
        'body' => 'Order {{order_no}} arrives today. Pay Rs {{amount_due}}.',
        'provider_template_id' => '1207160000000000001',
    ]);
    $customer = notificationCustomer();

    $this->hub->event('order.out_for_delivery', $customer, ['order_no' => 'ORD-8', 'amount_due' => '349.00', 'secret' => 'x']);

    expect($sms->sent($customer->phone))->toHaveCount(1)
        ->and($sms->sent($customer->phone)[0])->toMatchArray([
            'template' => '1207160000000000001',
            'vars' => ['order_no' => 'ORD-8', 'amount_due' => '349.00', 'message' => 'Order ORD-8 arrives today. Pay Rs 349.00.'],
        ])
        ->and(channelLog('order.out_for_delivery', 'sms'))->status->toBe('sent')->provider_message_id->toBe('fake-1');
});

test('without a provider template id the event key is the SMS template key', function () {
    $sms = notificationFakeSms();
    notificationFakePush();
    notificationTemplate('order.out_for_delivery', 'sms', 'customer', ['provider_template_id' => null]);
    $customer = notificationCustomer();

    $this->hub->event('order.out_for_delivery', $customer, ['order_no' => 'ORD-8']);

    expect($sms->sent($customer->phone)[0]['template'])->toBe('order.out_for_delivery');
});

test('a gateway failure is logged as failed with the provider error', function () {
    notificationFakeSms()->failing();
    notificationFakePush();

    $this->hub->event('order.out_for_delivery', notificationCustomer(), ['order_no' => 'ORD-8']);

    expect(channelLog('order.out_for_delivery', 'sms'))->status->toBe('failed')->error->toBe('Fake SMS gateway failure.');
});

test('long SMS bodies are cut to two segments', function () {
    $sms = notificationFakeSms();
    notificationFakePush();
    notificationTemplate('order.out_for_delivery', 'sms', 'customer', ['body' => '{{note}}']);
    $customer = notificationCustomer();

    $this->hub->event('order.out_for_delivery', $customer, ['note' => str_repeat('x', 500)]);

    expect(strlen($sms->sent($customer->phone)[0]['vars']['message']))->toBe(306);
});

// Push

test('push goes to every device and unregistered tokens are deleted', function () {
    notificationFakeSms();
    $push = notificationFakePush(['dead-token-bbbbbbbbbbbbbbbbbbbbbbbb']);
    $customer = notificationCustomer();
    CustomerDeviceToken::query()->create(['customer_id' => $customer->id, 'device_token' => 'dead-token-bbbbbbbbbbbbbbbbbbbbbbbb', 'device_type' => 'ios']);

    $this->hub->event('order.out_for_delivery', $customer, ['order_no' => 'ORD-8']);

    expect($push->sent[0]['tokens'])->toBe(['device-token-aaaaaaaaaaaaaaaaaaaa'])
        ->and(CustomerDeviceToken::query()->where('customer_id', $customer->id)->pluck('device_token')->all())->toBe(['device-token-aaaaaaaaaaaaaaaaaaaa'])
        ->and(channelLog('order.out_for_delivery', 'push'))->status->toBe('sent');
});

test('the FCM driver posts HTTP v1 messages and reports UNREGISTERED tokens', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->body(), 'gone-token')) {
            return Http::response(['error' => [
                'code' => 404,
                'message' => 'Requested entity was not found.',
                'status' => 'NOT_FOUND',
                'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']],
            ]], 404);
        }

        return Http::response(['name' => 'projects/demo-project/messages/0:123'], 200);
    });

    $token = new class([]) extends FcmAccessToken
    {
        public function get(): string
        {
            return 'test-access-token';
        }
    };
    $driver = new FcmPushDriver(['project_id' => 'demo-project'], $token);

    $result = $driver->send(['live-token-cccccccccccccccc', 'gone-token-dddddddddddddddd'], new PushMessage('Hi', 'Body', ['url' => '/x']));

    expect($result->sentCount)->toBe(1)
        ->and($result->messageIds)->toBe(['projects/demo-project/messages/0:123'])
        ->and($result->invalidTokens)->toBe(['gone-token-dddddddddddddddd']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/demo-project/messages:send'
        && $request->hasHeader('Authorization', 'Bearer test-access-token')
        && $request['message']['token'] === 'live-token-cccccccccccccccc'
        && $request['message']['notification'] === ['title' => 'Hi', 'body' => 'Body']
        && $request['message']['data'] === ['url' => '/x']);
});

test('the FCM driver deletes unregistered tokens through the channel', function () {
    notificationFakeSms();
    Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)]);
    $token = new class([]) extends FcmAccessToken
    {
        public function get(): string
        {
            return 'test-access-token';
        }
    };
    app()->instance(PushDriver::class, new FcmPushDriver(['project_id' => 'demo-project'], $token));
    $customer = notificationCustomer();

    $this->hub->event('order.out_for_delivery', $customer, ['order_no' => 'ORD-8'], ['push']);

    expect(CustomerDeviceToken::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and(channelLog('order.out_for_delivery', 'push'))->status->toBe('failed');
});

test('the FCM access token is a signed service-account JWT exchanged once and cached', function () {
    // Windows PHP builds need their bundled openssl.cnf to generate keys.
    $bundledConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    $keyOptions = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + (is_file($bundledConfig) ? ['config' => $bundledConfig] : []);
    $key = @openssl_pkey_new($keyOptions);

    if ($key === false) {
        $this->markTestSkipped('OpenSSL cannot generate keys on this machine.');
    }

    openssl_pkey_export($key, $privateKey, null, $keyOptions);
    $publicKey = openssl_pkey_get_details($key)['key'];
    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode(['client_email' => 'push@demo-project.iam.gserviceaccount.com', 'private_key' => $privateKey, 'project_id' => 'demo-project']));
    Cache::forget('notification.fcm.access_token');
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599])]);

    $accessToken = new FcmAccessToken(['credentials_path' => $path, 'token_uri' => 'https://oauth2.googleapis.com/token']);

    expect($accessToken->get())->toBe('ya29.test')
        ->and($accessToken->get())->toBe('ya29.test')
        ->and($accessToken->projectId())->toBe('demo-project');
    Http::assertSentCount(1);

    [$header, $claims, $signature] = explode('.', $accessToken->assertion(1_800_000_000));
    $decode = fn (string $part): string => base64_decode(strtr($part, '-_', '+/'));

    expect(json_decode($decode($claims), true))->toMatchArray([
        'iss' => 'push@demo-project.iam.gserviceaccount.com',
        'scope' => FcmAccessToken::SCOPE,
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => 1_800_000_000,
        'exp' => 1_800_003_600,
    ])->and(openssl_verify($header.'.'.$claims, $decode($signature), $publicKey, OPENSSL_ALGO_SHA256))->toBe(1);

    @unlink($path);
    Cache::forget('notification.fcm.access_token');
});

// WhatsApp

test('the WhatsApp stub logs and reports the message as sent', function () {
    notificationTemplate('order.placed', 'whatsapp', 'customer', ['body' => 'Order {{order_no}} placed', 'provider_template_id' => 'order_placed_v1']);

    $this->hub->event('order.placed', notificationCustomer(), ['order_no' => 'ORD-1'], ['whatsapp']);

    expect(channelLog('order.placed', 'whatsapp'))->status->toBe('sent')->provider_message_id->not->toBeNull();
});
