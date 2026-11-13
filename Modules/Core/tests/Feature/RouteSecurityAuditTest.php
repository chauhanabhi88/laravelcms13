<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;

require_once __DIR__.'/../../../Catalog/tests/Helpers.php';

/*
| Plan M21 S3-S5: route-table audits. A new route that forgets its guard, permission or
| throttle fails here instead of in a pen test.
*/

/**
 * The route's middleware both as declared ("throttle:30,1", "auth:seller") and as resolved
 * ("Modules\Core\Http\Middleware\Authenticate:seller").
 *
 * @return list<string>
 */
function auditRouteMiddleware(RoutingRoute $route): array
{
    return array_values(array_map('strval', array_merge($route->gatherMiddleware(), app('router')->gatherRouteMiddleware($route))));
}

function auditRouteHas(RoutingRoute $route, string $needle): bool
{
    foreach (auditRouteMiddleware($route) as $middleware) {
        if (stripos($middleware, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<RoutingRoute>
 */
function auditRoutes(callable $filter): array
{
    return array_values(array_filter(app('router')->getRoutes()->getRoutes(), $filter));
}

test('every supplier panel route except the guest screens requires the seller guard', function () {
    $guest = ['seller.landing', 'seller.login', 'seller.login.*', 'seller.password.*', 'seller.register', 'seller.register.*'];

    $unguarded = collect(auditRoutes(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'seller.')
        && ! $route->named(...$guest)))
        ->reject(fn (RoutingRoute $route): bool => auditRouteHas($route, 'Authenticate:seller'))
        ->map(fn (RoutingRoute $route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

test('every admin route requires an admin login or a permission', function () {
    // The admin API sign-in is public by nature (throttled, checked below).
    $guest = ['admin.login'];

    $open = collect(auditRoutes(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'admin.') && ! $route->named(...$guest)))
        ->reject(fn (RoutingRoute $route): bool => auditRouteHas($route, 'Authorization:')
            || auditRouteHas($route, 'Modules\User\Http\Middleware\Backend'))
        ->map(fn (RoutingRoute $route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($open)->toBe([]);
});

test('every customer account route except the auth screens requires the customer guard', function () {
    $guest = [
        'customer.login', 'customer.loginpost', 'customer.login.password', 'customer.otp.*', 'customer.signup', 'customer.signuppost',
        'customer.forgot', 'customer.forgot.post', 'customer.reset', 'customer.reset.post', 'customer.email.verification',
        'customer.logout', 'customer.customer_online_offline_log',
    ];

    $unguarded = collect(auditRoutes(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'customer/') && ! $route->named(...$guest)))
        ->reject(fn (RoutingRoute $route): bool => auditRouteHas($route, 'Authenticate:customer'))
        ->map(fn (RoutingRoute $route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

test('every customer api route behind the api guard is token protected', function () {
    $unguarded = collect(auditRoutes(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/orders')
        || str_starts_with($route->uri(), 'api/v1/returns')
        || str_starts_with($route->uri(), 'api/v1/customer/')
        || $route->uri() === 'api/v1/reviews'))
        ->reject(fn (RoutingRoute $route): bool => $route->named('customer_api.otp.*') || auditRouteHas($route, ':api'))
        ->map(fn (RoutingRoute $route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

test('login, otp, cart, checkout, review and upload endpoints are rate limited', function (string $name) {
    $route = app('router')->getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and(auditRouteHas($route, 'throttle'))->toBeTrue();
})->with([
    'customer.otp.send', 'customer.otp.verify', 'customer.loginpost', 'customer.signuppost', 'customer.forgot.post',
    'seller.login.attempt', 'seller.login.otp', 'seller.register.store', 'seller.password.email',
    'reset.post', 'reset.complete.post',
    'cart.add', 'cart.coupon.apply', 'checkout.place', 'payment.cod_otp.verify', 'storefront.delivery_check',
    'customer.reviews.store', 'review.report', 'customer.returns.submit', 'contact.store',
    'storefront_api.autocomplete', 'customer_api.otp.send', 'customer_api.otp.verify', 'order_api.checkout.place', 'cart_api.items.store',
    'admin.summernote.image_upload_temp', 'core.csp_report', 'admin.login',
]);

test('the admin login throttles failed attempts in its controller', function () {
    expect(config('user.login_throttle.max_attempts'))->toBeGreaterThan(0);
});

test('provider webhooks are not rate limited', function (string $name) {
    $route = app('router')->getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and(auditRouteHas($route, 'throttle'))->toBeFalse();
})->with(['webhook.razorpay', 'webhook.razorpayx', 'webhook.courier_tracking']);

test('an anonymous visitor cannot upload editor images', function () {
    $this->post(route('admin.summernote.image_upload_temp'))->assertRedirect(route('backend_login'));
});

test('the editor image upload refuses anything but images', function () {
    $this->actingAs(catalogAdmin([]), 'web')
        ->post(route('admin.summernote.image_upload_temp'), ['image' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php')])
        ->assertStatus(422)
        ->assertJsonPath('type', 'error');
});
