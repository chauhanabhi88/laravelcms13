<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Support\IntegrationHttp;

test('integration requests are logged with secrets redacted', function () {
    Http::fake(['api.example.test/*' => Http::response(['type' => 'success', 'access_token' => 'tok-123'])]);
    $logged = [];
    Log::shouldReceive('channel')->with('integrations')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context) use (&$logged) {
        $logged[] = compact('message', 'context');
    });

    $response = Http::integration('acme')->post('https://api.example.test/v1/send?api_key=k-1&page=2', [
        'password' => 'p@ss',
        'company' => 'Acme',
        'recipients' => [['mobiles' => '919876543210', 'otp' => '123456']],
    ]);

    expect($response->json('access_token'))->toBe('tok-123')->and($logged)->toHaveCount(1);

    $context = $logged[0]['context'];

    expect($logged[0]['message'])->toBe('acme POST 200')
        ->and($context['url'])->toBe('https://api.example.test/v1/send?api_key=%5BREDACTED%5D&page=2')
        ->and($context['request']['password'])->toBe('[REDACTED]')
        ->and($context['request']['company'])->toBe('Acme')
        ->and($context['request']['recipients'][0]['otp'])->toBe('[REDACTED]')
        ->and($context['request']['recipients'][0]['mobiles'])->toBe('919876543210')
        ->and($context['response']['access_token'])->toBe('[REDACTED]');
});

test('server errors are retried and client errors are not', function () {
    Http::fake([
        'api.example.test/flaky' => Http::sequence()->push('', 503)->push('', 503)->push(['ok' => true]),
        'api.example.test/bad' => Http::response(['error' => 'invalid'], 422),
    ]);
    config(['core.integrations.retry_delay_ms' => 0]);

    expect(Http::integration('acme')->get('https://api.example.test/flaky')->json('ok'))->toBeTrue();
    expect(Http::integration('acme')->get('https://api.example.test/bad')->status())->toBe(422);

    Http::assertSentCount(4);
});

test('secret key detection matches whole name segments only', function (string $key, bool $secret) {
    expect(IntegrationHttp::isSecretKey($key))->toBe($secret);
})->with([
    ['token', true],
    ['access_token', true],
    ['X-Api-Key', true],
    ['password_confirmation', true],
    ['company', false],
    ['pincode', false],
    ['expand', false],
]);
