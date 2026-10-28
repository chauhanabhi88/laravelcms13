<?php

use Modules\Payment\Support\RazorpaySignature;

test('a checkout callback signature is the HMAC of order_id|payment_id with the key secret', function () {
    $signature = hash_hmac('sha256', 'order_ABC|pay_XYZ', 'secret');

    expect(RazorpaySignature::forCallback('order_ABC', 'pay_XYZ', 'secret'))->toBe($signature)
        ->and(RazorpaySignature::verifyCallback('order_ABC', 'pay_XYZ', $signature, 'secret'))->toBeTrue();
});

test('a tampered or unsigned checkout callback does not verify', function (string $orderId, string $paymentId, string $signature, string $secret) {
    expect(RazorpaySignature::verifyCallback($orderId, $paymentId, $signature, $secret))->toBeFalse();
})->with([
    'other payment' => ['order_ABC', 'pay_OTHER', hash_hmac('sha256', 'order_ABC|pay_XYZ', 'secret'), 'secret'],
    'other order' => ['order_OTHER', 'pay_XYZ', hash_hmac('sha256', 'order_ABC|pay_XYZ', 'secret'), 'secret'],
    'wrong secret' => ['order_ABC', 'pay_XYZ', hash_hmac('sha256', 'order_ABC|pay_XYZ', 'other'), 'secret'],
    'empty signature' => ['order_ABC', 'pay_XYZ', '', 'secret'],
    'empty secret' => ['order_ABC', 'pay_XYZ', hash_hmac('sha256', 'order_ABC|pay_XYZ', ''), ''],
]);

test('a webhook signature is the HMAC of the raw body with the webhook secret', function () {
    $body = '{"event":"payment.captured"}';
    $signature = hash_hmac('sha256', $body, 'whsec');

    expect(RazorpaySignature::forWebhook($body, 'whsec'))->toBe($signature)
        ->and(RazorpaySignature::verifyWebhook($body, $signature, 'whsec'))->toBeTrue()
        ->and(RazorpaySignature::verifyWebhook($body.' ', $signature, 'whsec'))->toBeFalse()
        ->and(RazorpaySignature::verifyWebhook($body, $signature, 'other'))->toBeFalse()
        ->and(RazorpaySignature::verifyWebhook($body, $signature, ''))->toBeFalse();
});
