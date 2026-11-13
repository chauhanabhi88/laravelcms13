<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminIdleTimeout;
use Modules\User\Models\User;

require_once __DIR__.'/../../../Catalog/tests/Helpers.php';

beforeEach(function () {
    Route::middleware('web')->get('/_test/page', fn () => '<html><body>page</body></html>');
    Route::middleware('web')->get('/_test/returns-photo', fn () => '<html><body>photo</body></html>')->name('customer.returns._test');
    Route::middleware(['web', 'auth'])->get('/_test/admin-area', fn () => 'admin area')->name('admin._test');
    app('router')->getRoutes()->refreshNameLookups();
});

test('web pages carry the secure headers', function () {
    $this->get('/_test/page')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), usb=()')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('hsts is sent only over https', function () {
    $this->get('https://localhost/_test/page')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('the csp starts in report-only mode with a nonce that matches the page scripts', function () {
    $response = $this->get(frontRoute('homepage'))->assertOk();

    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($policy)->toContain("default-src 'self'")
        ->toContain('https://checkout.razorpay.com')
        ->toContain("frame-ancestors 'self'")
        ->toContain('report-uri '.route('core.csp_report'));

    preg_match("/'nonce-([^']+)'/", $policy, $match);

    expect($match[1] ?? null)->not->toBeNull()
        ->and($response->getContent())->toContain('<script nonce="'.$match[1].'"');
});

test('the csp can be enforced or switched off', function () {
    config(['core.security.csp.mode' => 'enforce']);
    $this->get('/_test/page')->assertHeader('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');

    config(['core.security.csp.mode' => 'off']);
    $this->get('/_test/page')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

test('api and admin responses get the headers but no csp', function () {
    $this->getJson(url('api/v1/app/config'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');

    $this->get(route('admin._test'))
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

test('the camera is allowed only on the return and review photo pages', function () {
    $this->get(route('customer.returns._test'))
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), usb=()');
});

test('the security headers can be switched off', function () {
    config(['core.security.headers' => false]);

    $this->get('/_test/page')->assertHeaderMissing('X-Frame-Options')->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

test('csp violation reports are logged to the security channel', function () {
    $logger = Mockery::mock();
    $logger->shouldReceive('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'CSP violation'
        && $context['directive'] === 'script-src-elem'
        && $context['blocked'] === 'https://evil.example/x.js');
    Log::shouldReceive('channel')->with('security')->once()->andReturn($logger);

    $this->call('POST', route('core.csp_report'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode([
        'csp-report' => [
            'document-uri' => 'http://localhost/',
            'effective-directive' => 'script-src-elem',
            'blocked-uri' => 'https://evil.example/x.js',
        ],
    ]))->assertNoContent();
});

test('a body that is not a csp report is ignored', function () {
    Log::shouldReceive('channel')->never();

    $this->call('POST', route('core.csp_report'), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"hello":"world"}')
        ->assertNoContent();
});

test('an idle admin is logged out after the timeout', function () {
    $admin = catalogAdmin([]);

    $this->actingAs($admin, 'web')
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(31)->getTimestamp()])
        ->get(route('admin._test'))
        ->assertRedirect(route('backend_login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest('web');
});

test('an active admin stays logged in and the activity clock moves on', function () {
    $this->actingAs(catalogAdmin([]), 'web')
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(10)->getTimestamp()])
        ->get(route('admin._test'))
        ->assertOk()
        ->assertSessionHas(AdminIdleTimeout::SESSION_KEY, now()->getTimestamp());
});

test('the idle timeout can be disabled', function () {
    config(['core.security.admin_idle_minutes' => 0]);

    $this->actingAs(catalogAdmin([]), 'web')
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subDay()->getTimestamp()])
        ->get(route('admin._test'))
        ->assertOk();
});

test('the backend ip allowlist blocks other networks but not the storefront', function () {
    config(['core.security.backend_ip_allowlist' => ['10.0.0.0/8']]);

    $this->get(route('admin._test'))->assertForbidden();
    $this->get(route('backend_login'))->assertForbidden();
    $this->get('/_test/page')->assertOk();

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
        ->get(route('admin._test'))
        ->assertRedirect(route('backend_login'));
});

test('an empty allowlist lets every admin network in', function () {
    expect(config('core.security.backend_ip_allowlist'))->toBe([]);

    $this->actingAs(User::query()->forceCreate([
        'name' => 'Ops',
        'email' => uniqid('ops-').'@example.test',
        'password' => bcrypt('Secret#123'),
        'role_id' => null,
        'status' => 1,
    ]), 'web')->get(route('admin._test'))->assertOk();
});
